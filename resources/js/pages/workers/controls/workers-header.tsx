import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { BookOpenIcon, MoreVerticalIcon, PlusIcon, RefreshCwIcon, RotateCwIcon } from 'lucide-react';
import { useDialog } from '@/hooks/use-dialog';
import type { Server } from '@/types/server';
import type { Site } from '@/types/site';
import type { PanelControlProps } from '@/pages/dynamic/controls/registry';

/**
 * Workers header actions (rendered beside the page title): docs + resync/restart-all
 * menu + create. Reuses the existing worker form/confirm dialogs and named routes.
 */
export default function WorkersHeader(_props: PanelControlProps) {
  const { server, site } = usePage<{ server: Server; site: Site }>().props;
  const dialog = useDialog();

  const scope = { server: server.id, site: site.id };
  const scopeLabel = `${site.domain}'s workers`;

  return (
    <>
      <a href="https://vitodeploy.com/docs/servers/workers" target="_blank">
        <Button variant="outline">
          <BookOpenIcon />
          <span className="hidden lg:block">Docs</span>
        </Button>
      </a>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline">
            <MoreVerticalIcon />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem
            onSelect={() =>
              dialog.confirm.open({
                title: 'Resync workers',
                description: `Fetch the live status of ${scopeLabel} from the process manager and update Vito. Continue?`,
                confirmLabel: 'Resync',
                method: 'post',
                url: route('workers.resync', scope),
              })
            }
          >
            <RefreshCwIcon />
            Resync
          </DropdownMenuItem>
          <DropdownMenuItem
            onSelect={() =>
              dialog.confirm.open({
                title: 'Restart all workers',
                description: `Are you sure you want to restart ${scopeLabel}?`,
                confirmLabel: 'Restart All',
                method: 'post',
                url: route('workers.restart-all', scope),
              })
            }
          >
            <RotateCwIcon />
            Restart All
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      <Button onClick={() => dialog.workerForm.open({ serverId: server.id, site })}>
        <PlusIcon />
        <span className="hidden lg:block">Create</span>
      </Button>
    </>
  );
}
