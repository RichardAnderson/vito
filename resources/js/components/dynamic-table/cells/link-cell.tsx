import { CellContext } from '@tanstack/react-table';
import { TableColumnConfig } from '@/types/table';
import { Link } from '@inertiajs/react';

function resolveRouteParams<T extends Record<string, unknown>>(params: Record<string, string>, row: T): Record<string, unknown> {
  const resolved: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value.startsWith(':')) {
      const accessor = value.slice(1);
      resolved[key] = row[accessor];
    } else {
      resolved[key] = value;
    }
  }
  return resolved;
}

export default function LinkCell<T extends Record<string, unknown>>({ row }: CellContext<T, unknown>, config: TableColumnConfig) {
  const value = row.original[config.accessor];

  if (!config.linkRoute) {
    return <span>{String(value ?? '')}</span>;
  }

  const params = resolveRouteParams(config.linkParams, row.original);

  return (
    <Link className="hover:underline" href={route(config.linkRoute, params)} prefetch>
      {String(value ?? '')}
    </Link>
  );
}
