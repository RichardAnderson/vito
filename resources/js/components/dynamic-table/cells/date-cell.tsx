import { CellContext } from '@tanstack/react-table';
import { TableColumnConfig } from '@/types/table';
import DateTime from '@/components/date-time';

export default function DateCell<T extends Record<string, unknown>>({ row }: CellContext<T, unknown>, config: TableColumnConfig) {
  const value = row.original[config.accessor] as string | undefined;
  return value ? <DateTime date={value} /> : null;
}
