import { Head, router, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { MoreVerticalIcon, PencilIcon, PlusIcon, ServerIcon, Trash2Icon } from 'lucide-react';
import { VitoTable } from '@/components/vito-table';
import Layout from '@/layouts/app/layout';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { asRow } from '@/lib/inertia-table';
import { useDialog } from '@/hooks/use-dialog';
import { PrivateNetwork, PrivateNetworkMember } from '@/types/private-network';
import { cn } from '@/lib/utils';

export default function Networks() {
  const page = usePage<{
    networks: PrivateNetwork[];
    selectedId: number | null;
    members: InertiaTableData | null;
    availableServers: { id: number; name: string }[];
    suggestedSubnet: string;
  }>();
  const dialog = useDialog();

  const networks = page.props.networks;
  const selected = networks.find((n) => n.id === page.props.selectedId) ?? null;

  const select = (id: number) => {
    if (id === page.props.selectedId) return;
    router.get(route('networks.show', { network: id }), {}, { preserveScroll: true, preserveState: true, only: ['selectedId', 'members', 'availableServers'] });
  };

  const deleteNetwork = (network: PrivateNetwork) =>
    dialog.confirm.open({
      title: `Delete network [${network.name}]`,
      description: `Are you sure you want to delete ${network.name}? All servers will be removed from the mesh. This cannot be undone.`,
      variant: 'destructive',
      confirmLabel: 'Delete',
      method: 'delete',
      url: route('networks.destroy', { network: network.id }),
    });

  return (
    <Layout>
      <Head title="Networks" />

      <Container className="max-w-6xl">
        <HeaderContainer>
          <Heading title="Networks" description="Create private WireGuard networks and connect your servers into a secure mesh" />
          <Button onClick={() => dialog.privateNetworkForm.open({ suggestedSubnet: page.props.suggestedSubnet })}>
            <PlusIcon />
            <span className="hidden lg:block">Create</span>
          </Button>
        </HeaderContainer>

        {networks.length === 0 ? (
          <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed p-12 text-center">
            <ServerIcon className="text-muted-foreground size-8" />
            <p className="text-muted-foreground text-sm">No private networks yet. Create one to connect your servers.</p>
          </div>
        ) : (
          <div className="flex flex-col gap-6">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {networks.map((network) => (
                <button
                  key={network.id}
                  type="button"
                  onClick={() => select(network.id)}
                  className={cn(
                    'flex flex-col gap-2 rounded-lg border p-4 text-left transition-colors',
                    network.id === page.props.selectedId ? 'border-primary bg-muted/50' : 'hover:bg-muted/30',
                  )}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="truncate font-medium">{network.name}</span>
                    <Badge variant={network.status_color}>{network.status}</Badge>
                  </div>
                  <span className="text-muted-foreground font-mono text-xs">{network.subnet}</span>
                  <span className="text-muted-foreground flex items-center gap-1 text-xs">
                    <ServerIcon className="size-3" />
                    {network.servers_count ?? 0} {network.servers_count === 1 ? 'server' : 'servers'}
                  </span>
                </button>
              ))}
            </div>

            <div className="flex flex-col gap-4">
              {!selected && (
                <p className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                  Select a network above to view and manage its servers.
                </p>
              )}
              {selected && (
                <>
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex flex-col gap-1">
                      <h2 className="text-lg font-semibold">{selected.name}</h2>
                      <p className="text-muted-foreground text-sm">
                        Overlay {selected.subnet} · MTU {selected.mtu}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => dialog.addServerToNetwork.open({ networkId: selected.id, servers: page.props.availableServers })}
                      >
                        <PlusIcon />
                        <span className="hidden lg:block">Add server</span>
                      </Button>
                      <DropdownMenu modal={false}>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon">
                            <MoreVerticalIcon />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onSelect={() => dialog.privateNetworkForm.open({ network: selected })}>
                            <PencilIcon />
                            Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem variant="destructive" onSelect={() => deleteNetwork(selected)}>
                            <Trash2Icon />
                            Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </div>

                  {page.props.members && (
                    <VitoTable
                      tableData={page.props.members}
                      actions={(row: Row) => {
                        const member = asRow<PrivateNetworkMember>(row, ['id', 'server_id', 'server_name']);
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
                                <DropdownMenuItem
                                  variant="destructive"
                                  onSelect={() =>
                                    dialog.confirm.open({
                                      title: `Remove ${member.server_name ?? 'server'}`,
                                      description: `Remove this server from ${selected.name}? Its tunnel and overlay firewall rules will be torn down.`,
                                      variant: 'destructive',
                                      confirmLabel: 'Remove',
                                      method: 'delete',
                                      url: route('networks.servers.detach', { network: selected.id, server: member.server_id }),
                                    })
                                  }
                                >
                                  Remove from network
                                </DropdownMenuItem>
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </div>
                        );
                      }}
                    />
                  )}
                </>
              )}
            </div>
          </div>
        )}
      </Container>
    </Layout>
  );
}
