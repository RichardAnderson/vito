import { ColumnDef } from '@tanstack/react-table';
import { TableConfig } from '@/types/table';
import { getCellRenderer } from './cells';

export function generateColumns<T extends Record<string, unknown>>(config: TableConfig): ColumnDef<T>[] {
  return config.columns
    .filter((col) => !col.hidden)
    .map((columnConfig): ColumnDef<T> => {
      const cellRenderer = getCellRenderer<T>(columnConfig.type);

      return {
        id: columnConfig.accessor,
        accessorKey: columnConfig.accessor !== 'actions' ? columnConfig.accessor : undefined,
        header: columnConfig.label,
        enableSorting: columnConfig.sortable,
        enableColumnFilter: columnConfig.searchable,
        cell: (props) => cellRenderer(props, columnConfig),
      };
    });
}
