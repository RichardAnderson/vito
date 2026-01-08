import { CellContext } from '@tanstack/react-table';
import { TableColumnConfig } from '@/types/table';
import { Badge } from '@/components/ui/badge';

type BadgeVariant = 'default' | 'success' | 'info' | 'warning' | 'danger' | 'gray' | 'outline';

export default function StatusCell<T extends Record<string, unknown>>({ row }: CellContext<T, unknown>, config: TableColumnConfig) {
  const value = row.original[config.accessor];
  const color = config.colorAccessor ? (row.original[config.colorAccessor] as BadgeVariant) : 'default';

  return <Badge variant={color}>{String(value ?? '')}</Badge>;
}
