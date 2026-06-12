import { usePage } from '@inertiajs/react';
import type { InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { MoreVerticalIcon } from 'lucide-react';
import { VitoTable } from '@/components/vito-table';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useDialog } from '@/hooks/use-dialog';
import type { RowActionNode, TableNode } from '@/types/dynamic-page';
import type { SchemaComponentProps } from '../component-registry';
import { dispatchAction, interpolateParams, usePageContext } from '../page-context';
import { getRowActionsControl } from '../controls/registry';
import { ResolveNodes } from '../resolve-node';

export default function TableComponent({ node }: SchemaComponentProps<TableNode>) {
  const page = usePage();
  const { actions, data } = usePageContext();
  const dialog = useDialog();

  const tableData = page.props[`tables:${node.tableId}`] as InertiaTableData | undefined;
  if (!tableData) {
    return null;
  }

  const isVisible = (rowAction: RowActionNode, row: Row): boolean => {
    if (!rowAction.visibleWhen) {
      return true;
    }
    const cell = row[rowAction.visibleWhen.column];
    const expected = rowAction.visibleWhen.value;
    return Array.isArray(expected) ? expected.includes(cell as never) : cell === expected;
  };

  const run = (rowAction: RowActionNode, row: Row): void => {
    const context = row as unknown as Record<string, unknown>;

    if (rowAction.dialog) {
      // Merge the interpolated row-action params (e.g. the row id) into the dialog
      // context so an edit dialog can seed a hidden field and bind the model.
      const merged = { ...context, ...interpolateParams(rowAction.params, context) };
      dialog.dynamicDialog.open({ dialog: rowAction.dialog, actions, data, context: merged });
      return;
    }

    if (!rowAction.action) {
      return;
    }

    const action = actions[rowAction.action];
    if (!action) {
      return;
    }

    const body = interpolateParams(rowAction.params, context);

    if (rowAction.confirm) {
      dialog.confirm.open({
        title: rowAction.confirm,
        variant: rowAction.destructive ? 'destructive' : 'default',
        method: action.method as 'post' | 'patch' | 'put' | 'delete',
        url: action.url,
        data: body as Record<string, string | number | boolean | null>,
      });
    } else {
      dispatchAction(action, body);
    }
  };

  const RowActionsControl = node.rowActionsControl ? getRowActionsControl(node.rowActionsControl) : undefined;

  return (
    <div className="flex flex-col gap-4">
      {node.headerButtons.length > 0 && (
        <div className="flex items-center justify-end gap-2">
          <ResolveNodes nodes={node.headerButtons} />
        </div>
      )}
      <VitoTable
        tableData={tableData}
        actions={
          RowActionsControl
            ? (row: Row) => <RowActionsControl row={row} />
            : node.rowActions.length === 0
            ? undefined
            : (row: Row) => (
                <div className="flex items-center justify-end">
                  <DropdownMenu modal={false}>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" className="h-8 w-8 p-0">
                        <span className="sr-only">Open menu</span>
                        <MoreVerticalIcon />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      {node.rowActions
                        .filter((rowAction) => isVisible(rowAction, row))
                        .map((rowAction) => (
                          <DropdownMenuItem
                            key={rowAction.id}
                            variant={rowAction.destructive ? 'destructive' : 'default'}
                            onSelect={() => run(rowAction, row)}
                          >
                            {rowAction.label}
                          </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              )
        }
      />
    </div>
  );
}
