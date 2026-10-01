import { Head, Link, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { EyeIcon, RocketIcon } from 'lucide-react';
import { VitoTable } from '@/components/vito-table';
import EnvironmentLayout from '@/layouts/environment/layout';
import type { InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { useDialog } from '@/hooks/use-dialog';
import { Application, Environment } from '@/types/application';

export default function EnvironmentDeployments() {
  const page = usePage<{ application: Application; environment: Environment; deployments: InertiaTableData }>();
  const dialog = useDialog();
  const { application, environment } = page.props;

  return (
    <EnvironmentLayout>
      <Head title={`${environment.name} - Deployments`} />

      <Container className="max-w-5xl">
        <HeaderContainer>
          <Heading title="Deployments" description="One commit, fanned out to every component in stages" />
          <Button
            onClick={() =>
              dialog.confirm.open({
                title: 'Deploy environment',
                description: `Deploy the latest commit on ${environment.branch ?? 'the configured branch'} to every component.`,
                confirmLabel: 'Deploy',
                method: 'post',
                url: route('environments.deploy', { application: application.id, environment: environment.id }),
              })
            }
          >
            <RocketIcon />
            <span className="hidden lg:block">Deploy</span>
          </Button>
        </HeaderContainer>

        <VitoTable
          tableData={page.props.deployments}
          actions={(row: Row) => (
            <div className="flex items-center justify-end">
              <Button variant="outline" size="sm" asChild>
                <Link
                  href={route('environments.deployments.show', {
                    application: application.id,
                    environment: environment.id,
                    run: row.id,
                  })}
                  aria-label="View deployment"
                  prefetch
                >
                  <EyeIcon />
                </Link>
              </Button>
            </div>
          )}
        />
      </Container>
    </EnvironmentLayout>
  );
}
