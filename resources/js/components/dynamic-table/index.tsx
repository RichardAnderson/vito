import { useMemo } from 'react';
import { DataTable } from '@/components/data-table';
import { TableConfig } from '@/types/table';
import { PaginatedData } from '@/types';
import { generateColumns } from './generate-columns';

interface DynamicTableProps<T extends Record<string, unknown>> {
  config: TableConfig;
  paginatedData: PaginatedData<T>;
  searchable?: boolean;
  sortable?: boolean;
  className?: string;
}

export function DynamicTable<T extends Record<string, unknown>>({
  config,
  paginatedData,
  searchable = true,
  sortable = true,
  className,
}: DynamicTableProps<T>) {
  const columns = useMemo(() => generateColumns<T>(config), [config]);

  return (
    <DataTable
      columns={columns}
      paginatedData={paginatedData}
      searchable={searchable}
      sortable={sortable}
      className={className}
    />
  );
}

export default DynamicTable;
