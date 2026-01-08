import { TableColumnConfig } from '@/types/table';
import { CellContext } from '@tanstack/react-table';
import TextCell from './text-cell';
import DateCell from './date-cell';
import StatusCell from './status-cell';
import LinkCell from './link-cell';
import ActionsCell from './actions-cell';

export type CellRenderer<T extends Record<string, unknown>> = (
  props: CellContext<T, unknown>,
  config: TableColumnConfig,
) => React.ReactNode;

const cellRenderers: Record<string, CellRenderer<Record<string, unknown>>> = {
  text: TextCell,
  date: DateCell,
  status: StatusCell,
  link: LinkCell,
  actions: ActionsCell,
};

export function getCellRenderer<T extends Record<string, unknown>>(type: string): CellRenderer<T> {
  return (cellRenderers[type] || cellRenderers.text) as CellRenderer<T>;
}
