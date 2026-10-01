import { Head, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PlusIcon, TrashIcon } from 'lucide-react';
import EnvironmentLayout from '@/layouts/environment/layout';
import { useDialog } from '@/hooks/use-dialog';
import { Application, Environment } from '@/types/application';

type Attached = { id: number; type: string; name: string; server: string | null };
type ResourceOption = { id: number; name: string; server_id: number };
type ServerOption = { id: number; name: string };

export default function EnvironmentResources() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    attached: Attached[];
    servers: ServerOption[];
    databases: ResourceOption[];
    redis: ResourceOption[];
  }>();
  const dialog = useDialog();
  const { application, environment } = page.props;

  return (
    <EnvironmentLayout>
      <Head title={`${environment.name} - Resources`} />

      <Container className="max-w-3xl">
        <HeaderContainer>
          <Heading title="Resources" description="Databases and services this environment uses. Attaching never takes ownership." />
          <Button
            onClick={() =>
              dialog.resourceAttach.open({
                applicationId: application.id,
                environmentId: environment.id,
                servers: page.props.servers,
                databases: page.props.databases,
                redis: page.props.redis,
              })
            }
          >
            <PlusIcon />
            <span className="hidden lg:block">Attach</span>
          </Button>
        </HeaderContainer>

        <Card>
          <CardHeader>
            <CardTitle>Attached</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {page.props.attached.length === 0 ? (
              <p className="text-muted-foreground py-10 text-center text-sm">Nothing attached yet.</p>
            ) : (
              <ul className="divide-border divide-y">
                {page.props.attached.map((resource) => (
                  <li key={resource.id} className="flex items-center gap-4 px-4 py-3">
                    <span className="flex-1 text-sm">{resource.name}</span>
                    {resource.server && <span className="text-muted-foreground text-sm">{resource.server}</span>}
                    <Badge variant="outline">{resource.type}</Badge>
                    <Button
                      variant="ghost"
                      size="sm"
                      aria-label={`Detach ${resource.name}`}
                      onClick={() =>
                        dialog.confirm.open({
                          title: 'Detach resource',
                          description: `${resource.name} stays exactly where it is — only the link to this environment is removed.`,
                          confirmLabel: 'Detach',
                          method: 'delete',
                          url: route('environments.resources.destroy', {
                            application: application.id,
                            environment: environment.id,
                            resource: resource.id,
                          }),
                        })
                      }
                    >
                      <TrashIcon />
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </Container>
    </EnvironmentLayout>
  );
}
