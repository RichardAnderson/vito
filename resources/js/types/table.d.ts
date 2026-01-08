export type ColumnType = 'text' | 'date' | 'status' | 'link' | 'actions';

export interface TableColumnConfig {
  accessor: string;
  label: string;
  type: ColumnType;
  sortable: boolean;
  searchable: boolean;
  linkRoute: string | null;
  linkParams: Record<string, string>;
  colorAccessor: string | null;
  hidden: boolean;
}

export interface TableConfig {
  columns: TableColumnConfig[];
  defaultSort: {
    field: string | null;
    direction: 'asc' | 'desc';
  };
}
