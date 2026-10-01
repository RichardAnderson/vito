import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { BoxesIcon, DatabaseIcon, GlobeIcon, LayoutGridIcon, NetworkIcon, PlusIcon } from 'lucide-react';
import EnvironmentLayout from '@/layouts/environment/layout';
import TopologyCanvas from './topology/canvas';
import { Topology } from './topology/layout';
import { Application, Environment, EnvironmentDomain } from '@/types/application';
import { Server } from '@/types/server';
import { useDialog } from '@/hooks/use-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

const VIEW_KEY = 'vito.environment.topology-view';

export default function EnvironmentShow() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    strandedServers: { server_id: number; server: string }[];
    topology: Topology;
    aliases: EnvironmentDomain[];
    servers: { id: number; name: string }[];
    databases: { id: number; name: string; server_id: number }[];
    redis: { id: number; name: string; server_id: number }[];
    projectServers: Server[];
  }>();
  const dialog = useDialog();
  const { application, environment, topology } = page.props;
  const stranded = page.props.strandedServers ?? [];

  // NF7 ships both views; the choice is a per-viewer convenience, so it lives in localStorage.
  const [view, setView] = useState<'canvas' | 'tiles'>('tiles');

  useEffect(() => {
    try {
      const stored = window.localStorage.getItem(VIEW_KEY);
      if (stored === 'canvas' || stored === 'tiles') {
        setView(stored);
      }
    } catch {
      // Private windows and blocked site data are fine — the default stands.
    }
  }, []);

  const chooseView = (next: 'canvas' | 'tiles') => {
    setView(next);
    try {
      window.localStorage.setItem(VIEW_KEY, next);
    } catch {
      // Ignore: remembering the choice is a convenience, not a requirement.
    }
  };

  const openAddDomain = useCallback(
    () =>
      dialog.domainCreate.open({
        applicationId: application.id,
        environmentId: environment.id,
        aliases: page.props.aliases,
      }),
    [dialog, application.id, environment.id, page.props.aliases],
  );

  const openAddResource = useCallback(
    () =>
      dialog.resourceAttach.open({
        applicationId: application.id,
        environmentId: environment.id,
        servers: page.props.servers,
        databases: page.props.databases,
        redis: page.props.redis,
      }),
    [dialog, application.id, environment.id, page.props.servers, page.props.databases, page.props.redis],
  );

  const openAddComponent = useCallback(
    () =>
      dialog.componentCreate.open({
        applicationId: application.id,
        environmentId: environment.id,
        servers: page.props.projectServers,
      }),
    [dialog, application.id, environment.id, page.props.projectServers],
  );

  return (
    <EnvironmentLayout>
      <Head title={`${application.name} - ${environment.name}`} />

      <Container className="max-w-6xl">
        <HeaderContainer>
          <Heading title={environment.name} description={`${application.name} · ${environment.branch ?? 'no branch'}`} />
          <div className="flex items-center gap-2">
            <Badge variant="outline">{environment.status}</Badge>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button>
                  <PlusIcon />
                  <span className="hidden lg:block">Add</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={openAddComponent}>
                  <BoxesIcon />
                  Add component
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={openAddDomain}>
                  <GlobeIcon />
                  Add domain
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={openAddResource}>
                  <DatabaseIcon />
                  Add resource
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <div className="border-border flex items-center rounded-md border p-0.5">
              <Button variant={view === 'canvas' ? 'secondary' : 'ghost'} size="sm" onClick={() => chooseView('canvas')} aria-label="Canvas view">
                <NetworkIcon />
              </Button>
              <Button variant={view === 'tiles' ? 'secondary' : 'ghost'} size="sm" onClick={() => chooseView('tiles')} aria-label="Tile view">
                <LayoutGridIcon />
              </Button>
            </div>
          </div>
        </HeaderContainer>

        {stranded.length > 0 && (
          <Alert className="mb-4">
            <AlertTitle>These servers share no private network</AlertTitle>
            <AlertDescription>
              {stranded.map((server) => server.server).join(', ')} cannot reach the other components in this environment.{' '}
              <Link href={route('networks')} className="underline">
                Manage networks
              </Link>
            </AlertDescription>
          </Alert>
        )}

        {topology.components.length === 0 ? (
          <Card>
            <CardContent className="text-muted-foreground px-4 py-10 text-center text-sm">
              No components yet. Add one to place this environment onto a server.
            </CardContent>
          </Card>
        ) : view === 'canvas' ? (
          <TopologyCanvas topology={topology} />
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            {topology.components.map((component) => {
              const server = topology.servers.find((s) => s.id === component.server_id);
              const domains = topology.domains.filter((domain) => domain.component_id === component.id);

              return (
                <Card key={component.id}>
                  <CardContent className="flex flex-col gap-2 p-4">
                    <div className="flex items-center gap-2">
                      <span className="font-medium">{component.name}</span>
                      <Badge variant="outline" className="ml-auto">
                        {component.status}
                      </Badge>
                    </div>
                    <div className="text-muted-foreground text-sm">
                      {server?.name ?? 'unknown server'} · {component.functions.length > 0 ? component.functions.join(' · ') : component.type}
                    </div>
                    <div className="text-muted-foreground text-sm">
                      {domains.length > 0 ? domains.map((domain) => domain.host).join(', ') : 'no routes · no domains'}
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
      </Container>
    </EnvironmentLayout>
  );
}
