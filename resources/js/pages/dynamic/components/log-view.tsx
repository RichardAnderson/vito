import { useQuery } from '@tanstack/react-query';
import axios from 'axios';
import type { LogViewNode } from '@/types/dynamic-page';
import type { SchemaComponentProps } from '../component-registry';
import { interpolateParams, usePageContext } from '../page-context';

export function LogView({ node, context }: { node: LogViewNode; context?: Record<string, unknown> }) {
  const { data } = usePageContext();
  const endpoint = node.endpoint ? data[node.endpoint] : undefined;
  const params = interpolateParams(node.params as Record<string, string>, context);

  const query = useQuery({
    queryKey: ['dynamic-log', endpoint?.url, params],
    queryFn: async () => {
      const response = await axios.get(endpoint!.url, { params });
      return response.data as { logs?: string } & Record<string, unknown>;
    },
    refetchInterval: node.interval,
    enabled: Boolean(endpoint),
  });

  const content = typeof query.data?.logs === 'string' ? query.data.logs : query.data ? JSON.stringify(query.data, null, 2) : '';

  return (
    <pre className="bg-muted max-h-[60vh] overflow-auto rounded-md p-4 font-mono text-xs whitespace-pre-wrap">
      {query.isLoading ? 'Loading…' : content}
    </pre>
  );
}

export default function LogViewComponent({ node }: SchemaComponentProps<LogViewNode>) {
  return <LogView node={node} />;
}
