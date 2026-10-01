import { Head, Link, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ExternalLinkIcon, GitBranchIcon, PlusIcon } from 'lucide-react';
import Layout from '@/layouts/app/layout';
import { useDialog } from '@/hooks/use-dialog';
import { Application, Environment } from '@/types/application';

export default function ApplicationShow() {
  const page = usePage<{
    application: Application;
    environments: Environment[];
  }>();
  const dialog = useDialog();
  const application = page.props.application;
  const isDeleting = application.status === 'deleting';

  return (
    <Layout>
      <Head title={`${application.name} - Environments`} />

      <Container className="max-w-5xl">
        <HeaderContainer>
          <div className="flex items-center gap-3">
            <Heading title={application.name} description={application.repository ?? 'No repository linked'} />
            {isDeleting && <Badge variant="destructive">{application.status}</Badge>}
          </div>
          <Button onClick={() => dialog.environmentCreate.open({ application })} disabled={isDeleting}>
            <PlusIcon />
            <span className="hidden lg:block">New environment</span>
          </Button>
        </HeaderContainer>

        {page.props.environments.length === 0 ? (
          <Card>
            <CardContent className="text-muted-foreground px-4 py-10 text-center text-sm">
              No environments yet. Create one to place this application onto a server.
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            {page.props.environments.map((environment) => (
              <Card key={environment.id}>
                <CardHeader className="flex flex-row items-center justify-between gap-2">
                  <CardTitle className="flex items-center gap-2">
                    <span className="size-2.5 rounded-full" style={{ backgroundColor: `var(--color-${environment.color}-500)` }} />
                    {environment.name}
                  </CardTitle>
                  <Badge variant={environment.status_color === 'success' ? 'default' : 'outline'}>{environment.status}</Badge>
                </CardHeader>
                <CardContent className="flex flex-col gap-3 p-4">
                  <div className="text-muted-foreground flex items-center gap-2 text-sm">
                    <GitBranchIcon className="size-4" />
                    {environment.branch ?? 'No branch'}
                  </div>
                  <div className="text-muted-foreground flex items-center gap-4 text-sm">
                    <span>{environment.components_count ?? 0} components</span>
                    <span>{environment.domains_count ?? 0} domains</span>
                  </div>
                  {(environment.domains ?? []).length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {environment.domains?.map((domain) => (
                        <Badge key={domain.id} variant="outline" asChild>
                          <a href={domain.url} target="_blank" rel="noopener noreferrer" title={domain.url}>
                            {domain.host}
                            <ExternalLinkIcon />
                          </a>
                        </Badge>
                      ))}
                    </div>
                  )}
                  <Button variant="outline" size="sm" asChild className="self-start">
                    <Link href={route('environments.show', { application: application.id, environment: environment.id })} prefetch>
                      Open
                    </Link>
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        <Card className="border-destructive/30 mt-4 overflow-hidden">
          <CardHeader>
            <CardTitle>Danger zone</CardTitle>
            <CardDescription>
              Deleting this application removes all of its environments and every component inside them. This action cannot be undone.
            </CardDescription>
          </CardHeader>
          <CardContent className="bg-background">
            <div className="p-4">
              {isDeleting ? (
                <p className="text-muted-foreground text-sm">
                  This application is being deleted. Its components are being torn down on their servers, and it disappears once that finishes.
                </p>
              ) : (
                <Button
                  variant="destructive"
                  onClick={() => dialog.applicationDelete.open({ application, environmentCount: page.props.environments.length })}
                >
                  Delete application
                </Button>
              )}
            </div>
          </CardContent>
        </Card>
      </Container>
    </Layout>
  );
}
