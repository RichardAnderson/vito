/**
 * The graph is a fixed pipeline, not an arbitrary DAG, so nested-flexbox arithmetic replaces a
 * layout engine at zero bytes. Ported from the React Flow prototype unchanged.
 */
export const S = {
  compW: 254,
  compH: 64,
  gap: 12,
  srvTop: 50,
  srvX: 12,
  srvBottom: 12,
  netTop: 44,
  netX: 22,
  netBottom: 40,
  colGap: 64,
  rowGap: 22,
  domW: 210,
  domH: 56,
  domGap: 12,
  ingressGap: 70,
  resH: 64,
  resW: 200,
  resGap: 72,
} as const;

export type TopologyComponent = {
  id: number;
  name: string;
  type: string;
  status: string;
  status_color: string;
  functions: string[];
  server_id: number;
  needs_redeploy: boolean;
};

export type TopologyServer = {
  id: number;
  name: string;
  tier: number;
  ip: string | null;
  private_ip: string | null;
};

export type TopologyDomain = {
  id: number;
  host: string;
  component_id: number | null;
  ssl: boolean;
  status: string;
  redirects: string[];
};

export type TopologyResource = {
  id: number;
  name: string;
  type: string;
  engine: string | null;
  alias: string | null;
  server_id: number | null;
};

export type Topology = {
  networked: boolean;
  network_name: string | null;
  servers: TopologyServer[];
  components: TopologyComponent[];
  domains: TopologyDomain[];
  resources: TopologyResource[];
};

export type Geometry = {
  box: Record<number, { w: number; h: number }>;
  kids: Record<number, TopologyComponent[]>;
  resKids: Record<number, TopologyResource[]>;
  orphanResources: TopologyResource[];
  srvPos: Record<number, { x: number; y: number }>;
  netW: number;
  netH: number;
  netX: number;
  domY: number;
};

export function layout(graph: Topology): Geometry {
  const kids: Geometry['kids'] = {};
  const resKids: Geometry['resKids'] = {};
  const box: Geometry['box'] = {};
  const serverIds = new Set(graph.servers.map((server) => server.id));

  // A resource lives on a server, so it is drawn inside that server's box alongside the
  // components. Anything whose server is not part of this environment has nowhere to sit and
  // falls back to the column on the right.
  const orphanResources = graph.resources.filter((resource) => resource.server_id === null || !serverIds.has(resource.server_id));

  graph.servers.forEach((server) => {
    kids[server.id] = graph.components.filter((component) => component.server_id === server.id);
    resKids[server.id] = graph.resources.filter((resource) => resource.server_id === server.id);

    const count = Math.max(kids[server.id].length, 1);
    const componentBlock = count * (S.compH + S.gap) - S.gap;
    const resourceCount = resKids[server.id].length;
    const resourceBlock = resourceCount > 0 ? resourceCount * (S.resH + S.gap) - S.gap : 0;

    // Resources sit in a second column beside the components, so the box widens only when the
    // server actually holds one.
    box[server.id] = {
      w: S.compW + S.srvX * 2 + (resourceCount > 0 ? S.resGap + S.resW : 0),
      h: S.srvTop + Math.max(componentBlock, resourceBlock) + S.srvBottom,
    };
  });

  const tiers = [...new Set(graph.servers.map((server) => server.tier))].sort((a, b) => a - b);
  const cols = tiers.map((tier) => graph.servers.filter((server) => server.tier === tier));
  const colW = cols.map((list) => Math.max(...list.map((server) => box[server.id].w)));
  const colH = cols.map((list) => list.reduce((total, server) => total + box[server.id].h, 0) + (list.length - 1) * S.rowGap);
  const maxH = Math.max(...colH, 0);

  let x = 0;
  const colX: number[] = [];
  colW.forEach((width) => {
    colX.push(x);
    x += width + S.colGap;
  });
  const innerW = x - S.colGap;

  const srvPos: Geometry['srvPos'] = {};
  cols.forEach((list, index) => {
    let y = (maxH - colH[index]) / 2;
    list.forEach((server) => {
      srvPos[server.id] = { x: S.netX + colX[index], y: S.netTop + y };
      y += box[server.id].h + S.rowGap;
    });
  });

  const netW = innerW + S.netX * 2;
  const netH = maxH + S.netTop + S.netBottom;
  const netX = S.domW + S.ingressGap;

  // Domains read as the way in, so the list starts level with the top of the highest server box
  // rather than floating in the middle of the canvas.
  const domY = Math.min(...graph.servers.map((server) => srvPos[server.id].y), S.netTop);

  return { box, kids, resKids, orphanResources, srvPos, netW, netH, netX, domY };
}
