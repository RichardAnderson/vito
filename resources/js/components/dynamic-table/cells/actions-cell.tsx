import { CellContext } from '@tanstack/react-table';
import { TableColumnConfig } from '@/types/table';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { EyeIcon } from 'lucide-react';

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

export default function ActionsCell<T extends Record<string, unknown>>({ row }: CellContext<T, unknown>, config: TableColumnConfig) {
  if (!config.linkRoute) {
    return null;
  }

  const params = resolveRouteParams(config.linkParams, row.original);

  return (
    <div className="flex items-center justify-end">
      <Link href={route(config.linkRoute, params)} prefetch>
        <Button variant="outline" size="sm">
          <EyeIcon />
        </Button>
      </Link>
    </div>
  );
}
