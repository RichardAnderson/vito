import { useCallback, useMemo, useState } from 'react';
import { Background, Edge, MarkerType, Node, ReactFlow, ReactFlowProvider } from '@xyflow/react';
import '@xyflow/react/dist/base.css';
import { useAppearance } from '@/hooks/use-appearance';
import { ComponentNode, DomainNode, NetworkGroupNode, ResourceNode, ServerGroupNode } from './nodes';
import { Geometry, S, Topology, layout } from './layout';
import { TopologyFocusContext } from './focus';

const nodeTypes = {
  networkGroup: NetworkGroupNode,
  serverGroup: ServerGroupNode,
  component: ComponentNode,
  domain: DomainNode,
  resource: ResourceNode,
};

/**
 * A fixpoint walk from the focused node along the graph's edges, so hovering a domain lights the
 * whole request path and everything else dims.
 */
function litSet(focus: string | null, edges: Edge[]): Set<string> {
  if (focus === null) {
    return new Set();
  }

  const lit = new Set<string>([focus]);
  let changed = true;

  while (changed) {
    changed = false;
    edges.forEach((edge) => {
      if (lit.has(edge.source) && !lit.has(edge.target)) {
        lit.add(edge.target);
        changed = true;
      }
      if (lit.has(edge.target) && !lit.has(edge.source)) {
        lit.add(edge.source);
        changed = true;
      }
    });
  }

  return lit;
}

export default function TopologyCanvas({ topology }: { topology: Topology }) {
  const [focus, setFocus] = useState<string | null>(null);
  const { getActualAppearance } = useAppearance();

  const geometry: Geometry = useMemo(() => layout(topology), [topology]);

  const { nodes, edges } = useMemo(() => {
    const built: Node[] = [];
    const links: Edge[] = [];
    const networkId = 'net';

    // Parents are emitted before children; group size travels in `style`, never `node.width`.
    if (topology.networked) {
      built.push({
        id: networkId,
        type: 'networkGroup',
        position: { x: geometry.netX, y: 0 },
        data: { label: topology.network_name ?? 'Private network' },
        style: { width: geometry.netW, height: geometry.netH },
        selectable: false,
        draggable: false,
      });
    }

    topology.servers.forEach((server) => {
      const position = geometry.srvPos[server.id];
      built.push({
        id: `srv:${server.id}`,
        type: 'serverGroup',
        parentId: topology.networked ? networkId : undefined,
        extent: topology.networked ? 'parent' : undefined,
        position: topology.networked ? position : { x: geometry.netX + position.x, y: position.y },
        data: { label: server.name, ip: server.ip, privateIp: server.private_ip },
        style: { width: geometry.box[server.id].w, height: geometry.box[server.id].h },
        selectable: false,
        draggable: false,
      });

      geometry.resKids[server.id].forEach((resource, index) => {
        built.push({
          id: `res:${resource.id}`,
          type: 'resource',
          parentId: `srv:${server.id}`,
          extent: 'parent',
          position: { x: S.srvX + S.compW + S.resGap, y: S.srvTop + index * (S.resH + S.gap) },
          data: { label: resource.name, type: resource.type, engine: resource.engine, alias: resource.alias },
          style: { width: S.resW, height: S.resH },
          draggable: false,
        });
      });

      geometry.kids[server.id].forEach((component, index) => {
        built.push({
          id: `cmp:${component.id}`,
          type: 'component',
          parentId: `srv:${server.id}`,
          extent: 'parent',
          position: { x: S.srvX, y: S.srvTop + index * (S.compH + S.gap) },
          data: {
            label: component.name,
            type: component.type,
            status: component.status,
            functions: component.functions,
            needsRedeploy: component.needs_redeploy,
          },
          style: { width: S.compW, height: S.compH },
          draggable: false,
        });
      });
    });

    topology.domains.forEach((domain, index) => {
      const id = `dom:${domain.id}`;
      built.push({
        id,
        type: 'domain',
        position: { x: 0, y: geometry.domY + index * (S.domH + S.domGap) },
        data: { label: domain.host, ssl: domain.ssl, status: domain.status, redirects: domain.redirects ?? [] },
        style: { width: S.domW, height: S.domH },
        draggable: false,
      });

      if (domain.component_id !== null) {
        links.push({
          id: `${id}->cmp:${domain.component_id}`,
          source: id,
          target: `cmp:${domain.component_id}`,
          type: 'smoothstep',
          markerEnd: { type: MarkerType.ArrowClosed },
        });
      }
    });

    geometry.orphanResources.forEach((resource, index) => {
      built.push({
        id: `res:${resource.id}`,
        type: 'resource',
        position: { x: geometry.netX + geometry.netW + S.ingressGap, y: geometry.domY + index * (S.resH + S.domGap) },
        data: { label: resource.name, type: resource.type, engine: resource.engine, alias: resource.alias },
        style: { width: S.domW, height: S.resH },
        draggable: false,
      });
    });

    topology.resources.forEach((resource) => {
      topology.components.forEach((component) => {
        links.push({
          id: `cmp:${component.id}->res:${resource.id}`,
          source: `cmp:${component.id}`,
          target: `res:${resource.id}`,
          type: 'smoothstep',
          style: { strokeDasharray: '4 4' },
          markerEnd: { type: MarkerType.ArrowClosed },
        });
      });
    });

    return { nodes: built, edges: links };
  }, [topology, geometry]);

  const lit = useMemo(() => litSet(focus, edges), [focus, edges]);

  const decoratedEdges = useMemo(() => {
    if (lit.size === 0) {
      return edges;
    }

    return edges.map((edge) => {
      const opacity = lit.has(edge.source) && lit.has(edge.target) ? 1 : 0.25;

      return opacity === 1 ? edge : { ...edge, style: { ...edge.style, opacity } };
    });
  }, [edges, lit]);

  const onNodeMouseEnter = useCallback((_: unknown, node: Node) => setFocus(node.id), []);
  const onNodeMouseLeave = useCallback(() => setFocus(null), []);

  return (
    <ReactFlowProvider>
      <TopologyFocusContext value={lit}>
        <div className="bg-canvas border-border h-[640px] w-full rounded-xl border">
          <ReactFlow
            nodes={nodes}
            edges={decoratedEdges}
            nodeTypes={nodeTypes}
            colorMode={getActualAppearance()}
            onNodeMouseEnter={onNodeMouseEnter}
            onNodeMouseLeave={onNodeMouseLeave}
            fitView
            proOptions={{ hideAttribution: true }}
            nodesConnectable={false}
          >
            <Background />
          </ReactFlow>
        </div>
      </TopologyFocusContext>
    </ReactFlowProvider>
  );
}
