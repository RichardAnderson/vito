import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { MoreVerticalIcon } from 'lucide-react';
import { Worker } from '@/types/worker';
import { useDialog } from '@/hooks/use-dialog';
import { asRow } from '@/lib/inertia-table';
import { WorkerAction, WorkerEnvironment, WorkerLogs } from '@/pages/workers/components/worker-row-actions';
import type { RowActionsControlProps } from '@/pages/dynamic/controls/registry';

function BootstrapLockedItem({ label, destructive }: { label: string; destructive?: boolean }) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <div>
          <DropdownMenuItem disabled variant={destructive ? 'destructive' : undefined} onSelect={(e) => e.preventDefault()}>
            {label}
          </DropdownMenuItem>
        </div>
      </TooltipTrigger>
      <TooltipContent side="left">Site managed application worker</TooltipContent>
    </Tooltip>
  );
}

export default function WorkerActions({ row }: RowActionsControlProps) {
  const dialog = useDialog();
  const worker = asRow<Worker>(row, ['id', 'server_id', 'is_site_bootstrap']);
  const locked = worker.is_site_bootstrap;

  return (
    <div className="flex items-center justify-end">
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" className="h-8 w-8 p-0">
            <span className="sr-only">Open menu</span>
            <MoreVerticalIcon />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          {locked ? (
            <BootstrapLockedItem label="Edit" />
          ) : (
            <DropdownMenuItem onSelect={() => dialog.workerForm.open({ serverId: worker.server_id, worker })}>Edit</DropdownMenuItem>
          )}
          <WorkerAction type="start" worker={worker} />
          <WorkerAction type="stop" worker={worker} />
          <WorkerAction type="restart" worker={worker} />
          <WorkerLogs worker={worker} />
          <WorkerEnvironment worker={worker} />
          <DropdownMenuSeparator />
          {locked ? (
            <BootstrapLockedItem label="Delete" destructive />
          ) : (
            <DropdownMenuItem
              variant="destructive"
              onSelect={() =>
                dialog.confirm.open({
                  title: 'Delete worker',
                  description: 'Are you sure you want to delete this worker? This action cannot be undone.',
                  variant: 'destructive',
                  confirmLabel: 'Delete',
                  method: 'delete',
                  url: route('workers.destroy', { server: worker.server_id, worker }),
                })
              }
            >
              Delete
            </DropdownMenuItem>
          )}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
