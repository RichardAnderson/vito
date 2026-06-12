import { Head, usePage } from '@inertiajs/react';
import { Server } from '@/types/server';
import ServerLayout from '@/layouts/server/layout';
import SiteBanners from '@/components/site-banners';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { BookOpenIcon, MoreVerticalIcon, PlusIcon, RefreshCwIcon, RotateCwIcon } from 'lucide-react';
import Container from '@/components/container';
import { VitoTable } from '@/components/vito-table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Worker } from '@/types/worker';
import { Site } from '@/types/site';
import { useDialog } from '@/hooks/use-dialog';
import { asRow } from '@/lib/inertia-table';
import type { InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { WorkerAction, WorkerEnvironment, WorkerLogs } from '@/pages/workers/components/worker-row-actions';
import PageSlot from '@/components/page-slot';

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

function RowActions({ row }: { row: Row }) {
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

export default function WorkerIndex() {
  const page = usePage<{
    server: Server;
    workers: InertiaTableData;
    site?: Site;
    sites?: Array<{ id: number; domain: string }>;
  }>();
  const dialog = useDialog();

  const scope = page.props.site ? { server: page.props.server.id, site: page.props.site.id } : { server: page.props.server.id };
  const scopeLabel = page.props.site ? `${page.props.site.domain}'s workers` : "this server's workers";

  return (
    <ServerLayout>
      <Head title={`Workers - ${page.props.server.name}`} />

      <Container className="max-w-5xl">
        <HeaderContainer>
          <Heading
            title="Workers"
            description={page.props.site ? `Here you can manage ${page.props.site.domain}'s workers` : "Here you can manage server's workers"}
          />
          <div className="flex items-center gap-2">
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
            <Button onClick={() => dialog.workerForm.open({ serverId: page.props.server.id, site: page.props.site })}>
              <PlusIcon />
              <span className="hidden lg:block">Create</span>
            </Button>
          </div>
        </HeaderContainer>

        {page.props.site && <SiteBanners site={page.props.site} />}

        <PageSlot name="workers.before-table" />

        <VitoTable tableData={page.props.workers} actions={(row: Row) => <RowActions row={row} />} />
      </Container>
    </ServerLayout>
  );
}
