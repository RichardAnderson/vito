import { Head, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import EnvironmentLayout from '@/layouts/environment/layout';
import StageRail from '@/pages/applications/components/stage-rail';
import { Application, Environment } from '@/types/application';

type RunComponent = {
  id: number;
  component_id: number | null;
  component: string | null;
  stage: string | null;
  status: string;
  status_color: string;
  release: string | null;
  active: boolean;
  log_id: number | null;
};

type Run = {
  id: number;
  commit_id: string | null;
  release: string | null;
  status: string;
  status_color: string;
  current_stage: string | null;
  components: RunComponent[];
};

export default function DeploymentRun() {
  const page = usePage<{ application: Application; environment: Environment; run: Run }>();
  const { environment, run } = page.props;

  const isPartial = run.status === 'partial';
  const driftedComponents = run.components.filter((component) => component.release !== run.release);

  return (
    <EnvironmentLayout>
      <Head title={`${environment.name} - Deployment ${run.id}`} />

      <Container className="max-w-4xl">
        <HeaderContainer>
          <Heading
            title={`Deployment ${run.id}`}
            description={run.commit_id ? `Commit ${run.commit_id.slice(0, 7)} · release ${run.release ?? '—'}` : 'No commit resolved'}
          />
          <Badge variant="outline">{run.status}</Badge>
        </HeaderContainer>

        {isPartial && (
          <Alert className="mb-4">
            <AlertTitle>Components are on different releases</AlertTitle>
            <AlertDescription>
              Some components cut over and some did not
              {driftedComponents.length > 0 && `: ${driftedComponents.map((c) => c.component ?? `#${c.component_id}`).join(', ')}`}. Redeploy
              to bring them back in line.
            </AlertDescription>
          </Alert>
        )}

        <Card className="mb-4">
          <CardHeader>
            <CardTitle>Stages</CardTitle>
          </CardHeader>
          <CardContent className="p-4">
            <StageRail currentStage={run.current_stage} failed={run.status === 'failed' || isPartial} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Components</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <ul className="divide-border divide-y">
              {run.components.map((component) => (
                <li key={component.id} className="flex items-center gap-4 px-4 py-3">
                  <span className="w-1/3 text-sm font-medium">{component.component ?? `#${component.component_id}`}</span>
                  <span className="text-muted-foreground flex-1 text-sm">{component.stage ?? 'pending'}</span>
                  <span className="text-muted-foreground font-mono text-xs">{component.release ?? '—'}</span>
                  <Badge variant="outline">{component.status}</Badge>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      </Container>
    </EnvironmentLayout>
  );
}
