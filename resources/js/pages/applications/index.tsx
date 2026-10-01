import { Head, Link, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { EyeIcon, PlusIcon } from 'lucide-react';
import { VitoTable } from '@/components/vito-table';
import Layout from '@/layouts/app/layout';
import type { InertiaTableData, Row } from '@forjedio/inertia-table-react';
import { useDialog } from '@/hooks/use-dialog';
import { SourceControl } from '@/types/source-control';

export default function Applications() {
  const page = usePage<{
    applications: InertiaTableData;
    sourceControls: SourceControl[];
  }>();
  const dialog = useDialog();

  return (
    <Layout>
      <Head title="Applications" />

      <Container className="max-w-5xl">
        <HeaderContainer>
          <Heading title="Applications" description="Codebases deployed across the servers in this project" />
          <Button onClick={() => dialog.applicationCreate.open({ sourceControls: page.props.sourceControls })}>
            <PlusIcon />
            <span className="hidden lg:block">Create</span>
          </Button>
        </HeaderContainer>

        <VitoTable
          tableData={page.props.applications}
          actions={(row: Row) => (
            <div className="flex items-center justify-end">
              <Button variant="outline" size="sm" asChild>
                <Link href={route('applications.show', { application: row.id })} aria-label="View application" prefetch>
                  <EyeIcon />
                </Link>
              </Button>
            </div>
          )}
        />
      </Container>
    </Layout>
  );
}
