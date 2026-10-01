import { Head, Link, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { EyeIcon, PlusIcon } from 'lucide-react';
import { VitoTable } from '@/components/vito-table';
import EnvironmentLayout from '@/layouts/environment/layout';
import type { InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { useDialog } from '@/hooks/use-dialog';
import { Application, Environment } from '@/types/application';
import { Server } from '@/types/server';

export default function EnvironmentComponents() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    components: InertiaTableData;
    servers: Server[];
  }>();
  const dialog = useDialog();
  const { application, environment } = page.props;

  return (
    <EnvironmentLayout>
      <Head title={`${environment.name} - Components`} />

      <Container className="max-w-5xl">
        <HeaderContainer>
          <Heading title="Components" description="Where this environment is placed, and what each placement runs" />
          <Button
            onClick={() =>
              dialog.componentCreate.open({
                applicationId: application.id,
                environmentId: environment.id,
                servers: page.props.servers,
              })
            }
          >
            <PlusIcon />
            <span className="hidden lg:block">Add component</span>
          </Button>
        </HeaderContainer>

        <VitoTable
          tableData={page.props.components}
          actions={(row: Row) => (
            <div className="flex items-center justify-end">
              <Button variant="outline" size="sm" asChild>
                <Link
                  href={route('components.show', { application: application.id, environment: environment.id, component: row.id })}
                  aria-label="View component"
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
