import { CellContext } from '@tanstack/react-table';
import { TableColumnConfig } from '@/types/table';

export default function TextCell<T extends Record<string, unknown>>({ row }: CellContext<T, unknown>, config: TableColumnConfig) {
  const value = row.original[config.accessor];
  return <span>{String(value ?? '')}</span>;
}
