import { Head, router, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TrashIcon } from 'lucide-react';
import EnvironmentLayout from '@/layouts/environment/layout';
import { useDialog } from '@/hooks/use-dialog';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { PlusIcon } from 'lucide-react';
import { Application, Component, Environment } from '@/types/application';

export default function ComponentShow() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    component: Component;
    scriptOverrides: { id: number; name: string; content: string }[];
    backends: { id: number; target_component_id: number; target: string; port: number }[];
    backendCandidates: { id: number; name: string }[];
  }>();
  const dialog = useDialog();
  const { application, environment, component } = page.props;

  return (
    <EnvironmentLayout>
      <Head title={`${environment.name} - ${component.name}`} />

      <Container className="max-w-3xl">
        <HeaderContainer>
          <Heading title={component.name} description={`${component.type} on ${component.server ?? 'unknown server'}`} />
          <Button
            variant="outline"
            onClick={() =>
              dialog.confirm.open({
                title: 'Delete component',
                description:
                  'The vhost, workers, scheduler entry and code checkout are removed from the server. Attached databases are left alone.',
                confirmLabel: 'Delete',
                method: 'delete',
                url: route('components.destroy', {
                  application: application.id,
                  environment: environment.id,
                  component: component.id,
                }),
                onSuccess: () => router.visit(route('environments.components', { application: application.id, environment: environment.id })),
              })
            }
          >
            <TrashIcon />
            Delete
          </Button>
        </HeaderContainer>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Placement</CardTitle>
            <Badge variant="outline">{component.status}</Badge>
          </CardHeader>
          <CardContent className="flex flex-col gap-2 p-4 text-sm">
            <div className="flex justify-between">
              <span className="text-muted-foreground">Server</span>
              <span>{component.server ?? '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">User</span>
              <span>{component.user ?? '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Path</span>
              <span className="font-mono text-xs">{component.path ?? '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">PHP</span>
              <span>{component.php_version ?? '-'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Functions</span>
              <span className="flex gap-1">
                {(component.functions ?? []).map((fn) => (
                  <Badge key={fn.function} variant="outline">
                    {fn.label}
                  </Badge>
                ))}
                {(component.functions ?? []).length === 0 && '-'}
              </span>
            </div>
          </CardContent>
        </Card>

        <Card className="mt-4">
          <CardHeader>
            <CardTitle>Deploy script overrides</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-3 p-4">
            <p className="text-muted-foreground text-sm">
              An override replaces the environment script for this component only.
            </p>
            <div className="flex flex-wrap gap-2">
              {page.props.scriptOverrides.map((script) => (
                <span key={script.id} className="flex items-center gap-1">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                      dialog.deployScriptEditor.open({
                        applicationId: application.id,
                        environmentId: environment.id,
                        scriptId: script.id,
                        scriptName: script.name,
                        content: script.content,
                        scope: component.name,
                      })
                    }
                  >
                    Edit {script.name} override
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    aria-label={`Remove ${script.name} override`}
                    onClick={() =>
                      dialog.confirm.open({
                        title: 'Remove override',
                        description: `${component.name} will fall back to the environment ${script.name} script.`,
                        confirmLabel: 'Remove',
                        method: 'delete',
                        url: route('environments.scripts.destroy', {
                          application: application.id,
                          environment: environment.id,
                          script: script.id,
                        }),
                      })
                    }
                  >
                    <TrashIcon />
                  </Button>
                </span>
              ))}
              {(['build', 'pre-flight'] as const)
                .filter((name) => !page.props.scriptOverrides.some((script) => script.name === name))
                .map((name) => (
                  <Button
                    key={name}
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                      router.post(
                        route('environments.scripts.store', { application: application.id, environment: environment.id }),
                        { component_id: component.id, name },
                        { preserveScroll: true },
                      )
                    }
                  >
                    <PlusIcon />
                    Override {name}
                  </Button>
                ))}
            </div>
          </CardContent>
        </Card>

        {component.type_value === 'load_balancer' && <BackendsCard />}

        {component.last_error && (
          <Card className="mt-4">
            <CardHeader>
              <CardTitle className="text-destructive">Last error</CardTitle>
            </CardHeader>
            <CardContent className="p-4 text-sm">{component.last_error}</CardContent>
          </Card>
        )}
      </Container>
    </EnvironmentLayout>
  );
}

function BackendsCard() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    component: Component;
    backends: { id: number; target_component_id: number; target: string; port: number }[];
    backendCandidates: { id: number; name: string }[];
  }>();
  const { application, environment, component } = page.props;

  const [rows, setRows] = useState(
    page.props.backends.map((backend) => ({ target_component_id: String(backend.target_component_id), port: String(backend.port) })),
  );
  const form = useForm({});

  const submit = (e: FormEvent) => {
    e.preventDefault();
    router.post(
      route('components.backends', { application: application.id, environment: environment.id, component: component.id }),
      { backends: rows.map((row) => ({ target_component_id: Number(row.target_component_id), port: Number(row.port) })) },
      { preserveScroll: true },
    );
  };

  return (
    <Card className="mt-4">
      <CardHeader>
        <CardTitle>Backends</CardTitle>
      </CardHeader>
      <CardContent className="p-4">
        <p className="text-muted-foreground mb-3 text-sm">
          Traffic is forwarded to these components over the private network, addressed by their network IP.
        </p>
        <form onSubmit={submit} className="flex flex-col gap-2">
          {rows.map((row, index) => (
            <div key={index} className="flex items-center gap-2">
              <Select
                value={row.target_component_id}
                onValueChange={(value) =>
                  setRows(rows.map((existing, i) => (i === index ? { ...existing, target_component_id: value } : existing)))
                }
              >
                <SelectTrigger className="flex-1">
                  <SelectValue placeholder="Select a component" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    {page.props.backendCandidates.map((candidate) => (
                      <SelectItem key={candidate.id} value={candidate.id.toString()}>
                        {candidate.name}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                </SelectContent>
              </Select>
              <Input
                className="w-24"
                value={row.port}
                aria-label="Port"
                onChange={(e) => setRows(rows.map((existing, i) => (i === index ? { ...existing, port: e.target.value } : existing)))}
              />
              <Button variant="ghost" size="sm" type="button" aria-label="Remove backend" onClick={() => setRows(rows.filter((_, i) => i !== index))}>
                <TrashIcon />
              </Button>
            </div>
          ))}
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="sm" type="button" onClick={() => setRows([...rows, { target_component_id: '', port: '80' }])}>
              <PlusIcon />
              Add backend
            </Button>
            <Button type="submit" size="sm" disabled={form.processing || rows.length === 0}>
              Save
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
