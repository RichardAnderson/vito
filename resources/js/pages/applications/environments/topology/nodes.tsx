import { memo } from 'react';
import { Handle, Position } from '@xyflow/react';
import { useDimmed } from './focus';
import { Badge } from '@/components/ui/badge';
import { CornerUpRightIcon, GlobeIcon, LockIcon, NetworkIcon, ServerIcon } from 'lucide-react';

type NodeProps<T> = { id: string; data: T; selected?: boolean };

function NetworkGroupNodeImpl({ data }: NodeProps<{ label: string }>) {
  return (
    <div className="border-border bg-surface-group h-full w-full rounded-xl border border-dashed">
      <div className="text-muted-foreground px-4 py-3 text-xs font-medium tracking-wide uppercase">{data.label}</div>
    </div>
  );
}

function ServerGroupNodeImpl({ id, data }: NodeProps<{ label: string; ip: string | null; privateIp: string | null }>) {
  const dimmed = useDimmed(id);

  return (
    <div
      className="border-border bg-surface-node h-full w-full rounded-lg border transition-opacity"
      style={{ opacity: dimmed ? 0.25 : 1 }}
    >
      <div className="flex items-center gap-2 px-3 py-3">
        <ServerIcon className="text-muted-foreground size-4" />
        <span className="text-sm font-medium">{data.label}</span>
        <span className="text-muted-foreground ml-auto flex items-center gap-2 text-[11px]">
          {data.ip && <span title="Public IP">{data.ip}</span>}
          {data.privateIp && (
            <span title="Private network IP" className="flex items-center gap-1">
              <NetworkIcon className="size-3" />
              {data.privateIp}
            </span>
          )}
        </span>
      </div>
    </div>
  );
}

function ComponentNodeImpl({
  id,
  data,
  selected,
}: NodeProps<{
  label: string;
  type: string;
  status: string;
  functions: string[];
  needsRedeploy: boolean;
}>) {
  const dimmed = useDimmed(id);

  return (
    <div
      className={`bg-surface-node-2 h-full w-full rounded-md border px-3 py-2 transition-opacity ${
        selected ? 'border-primary' : 'border-border'
      }`}
      style={{ opacity: dimmed ? 0.25 : 1 }}
    >
      <Handle type="target" position={Position.Left} className="!bg-muted-foreground !size-1.5 !border-0" />
      <div className="flex items-center gap-2">
        <span className="text-sm font-medium">{data.label}</span>
        <Badge variant="outline" className="ml-auto text-[10px]">
          {data.status}
        </Badge>
      </div>
      <div className="text-muted-foreground mt-1 text-[11px]">
        {data.functions.length > 0 ? data.functions.join(' · ') : data.type}
        {data.needsRedeploy && ' · needs redeploy'}
      </div>
      <Handle type="source" position={Position.Right} className="!bg-muted-foreground !size-1.5 !border-0" />
    </div>
  );
}

function DomainNodeImpl({ id, data }: NodeProps<{ label: string; ssl: boolean; status: string; redirects: string[] }>) {
  const dimmed = useDimmed(id);

  return (
    <div
      className="border-border bg-surface-node h-full w-full rounded-md border px-3 py-2 transition-opacity"
      style={{ opacity: dimmed ? 0.25 : 1 }}
    >
      <div className="flex items-center gap-2">
        {data.ssl ? <LockIcon className="size-3.5 text-lime-600" /> : <GlobeIcon className="text-muted-foreground size-3.5" />}
        <span className="truncate text-sm">{data.label}</span>
      </div>
      <div className="mt-1 flex flex-wrap items-center gap-1">
        <span className="text-muted-foreground text-[11px]">{data.status}</span>
        {data.redirects.map((host) => (
          <Badge key={host} variant="outline" className="gap-1 px-1 py-0 text-[10px]">
            <CornerUpRightIcon className="size-2.5" />
            {host}
          </Badge>
        ))}
      </div>
      <Handle type="source" position={Position.Right} className="!bg-muted-foreground !size-1.5 !border-0" />
    </div>
  );
}

function ResourceNodeImpl({ id, data }: NodeProps<{ label: string; type: string; engine: string | null; alias: string | null }>) {
  const dimmed = useDimmed(id);

  return (
    <div
      className="border-border bg-surface-node-2 h-full w-full rounded-md border px-3 py-2 transition-opacity"
      style={{ opacity: dimmed ? 0.25 : 1 }}
    >
      <Handle type="target" position={Position.Left} className="!bg-muted-foreground !size-1.5 !border-0" />
      <div className="text-muted-foreground truncate text-[11px]">{[data.type, data.engine].filter(Boolean).join(' - ')}</div>
      <div className="mt-1 truncate text-sm font-medium">{data.alias ? `${data.label} (${data.alias})` : data.label}</div>
    </div>
  );
}

export const NetworkGroupNode = memo(NetworkGroupNodeImpl);
export const ServerGroupNode = memo(ServerGroupNodeImpl);
export const ComponentNode = memo(ComponentNodeImpl);
export const DomainNode = memo(DomainNodeImpl);
export const ResourceNode = memo(ResourceNodeImpl);
