import { Head, router, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { PlusIcon, TrashIcon } from 'lucide-react';
import { VitoTable } from '@/components/vito-table';
import EnvironmentLayout from '@/layouts/environment/layout';
import type { InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { useDialog } from '@/hooks/use-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Application, Environment, EnvironmentDomain } from '@/types/application';

export default function EnvironmentDomains() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    domains: InertiaTableData;
    aliases: EnvironmentDomain[];
    hasServingComponent: boolean;
  }>();
  const dialog = useDialog();
  const { application, environment } = page.props;

  return (
    <EnvironmentLayout>
      <Head title={`${environment.name} - Domains`} />

      <Container className="max-w-5xl">
        <HeaderContainer>
          <Heading title="Domains" description="Hostnames this environment answers on, as aliases or redirects" />
          <Button
            onClick={() =>
              dialog.domainCreate.open({
                applicationId: application.id,
                environmentId: environment.id,
                aliases: page.props.aliases,
              })
            }
          >
            <PlusIcon />
            <span className="hidden lg:block">Add domain</span>
          </Button>
        </HeaderContainer>

        {!page.props.hasServingComponent && (
          <Alert>
            <AlertDescription>
              This environment has no component serving HTTP yet. Domains added now stay pending and are configured automatically once you add one.
            </AlertDescription>
          </Alert>
        )}

        <VitoTable
          tableData={page.props.domains}
          actions={(row: Row) => (
            <div className="flex items-center justify-end">
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  dialog.confirm.open({
                    title: 'Remove domain',
                    description: `${row.host} will stop being served by this environment.`,
                    confirmLabel: 'Remove',
                    variant: 'destructive',
                    method: 'delete',
                    url: route('environments.domains.destroy', {
                      application: application.id,
                      environment: environment.id,
                      environmentDomain: row.id,
                    }),
                    onSuccess: () => router.reload(),
                  })
                }
                aria-label="Remove domain"
              >
                <TrashIcon />
              </Button>
            </div>
          )}
        />
      </Container>
    </EnvironmentLayout>
  );
}
