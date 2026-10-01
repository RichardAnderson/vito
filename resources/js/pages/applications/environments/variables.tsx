import { Head, router, usePage } from '@inertiajs/react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PencilIcon, PlusIcon, TrashIcon } from 'lucide-react';
import EnvironmentLayout from '@/layouts/environment/layout';
import { useDialog } from '@/hooks/use-dialog';
import { Application, Component, Environment } from '@/types/application';

type VariableRow = {
  id: number | null;
  key: string;
  value: string | null;
  is_secret: boolean;
  source: string;
};

export default function EnvironmentVariables() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    variables: VariableRow[];
    scopeComponentId: number | null;
    components: Component[];
  }>();
  const dialog = useDialog();
  const { application, environment, scopeComponentId } = page.props;

  const changeScope = (value: string) => {
    router.get(
      route('environments.variables', { application: application.id, environment: environment.id }),
      value === 'environment' ? {} : { component: value },
      { preserveState: false },
    );
  };

  return (
    <EnvironmentLayout>
      <Head title={`${environment.name} - Variables`} />

      <Container className="max-w-4xl">
        <HeaderContainer>
          <Heading title="Variables" description="Environment values, and the component overrides that beat them" />
          <div className="flex items-center gap-2">
            <Select value={scopeComponentId ? scopeComponentId.toString() : 'environment'} onValueChange={changeScope}>
              <SelectTrigger className="w-48">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem value="environment">Environment</SelectItem>
                  {page.props.components.map((component) => (
                    <SelectItem key={component.id} value={component.id.toString()}>
                      {component.name}
                    </SelectItem>
                  ))}
                </SelectGroup>
              </SelectContent>
            </Select>
            <Button
              onClick={() =>
                dialog.variableForm.open({
                  applicationId: application.id,
                  environmentId: environment.id,
                  componentId: scopeComponentId,
                })
              }
            >
              <PlusIcon />
              <span className="hidden lg:block">Add</span>
            </Button>
          </div>
        </HeaderContainer>

        <Card>
          <CardContent className="p-0">
            {page.props.variables.length === 0 ? (
              <p className="text-muted-foreground py-10 text-center text-sm">No variables yet.</p>
            ) : (
              <ul className="divide-border divide-y">
                {page.props.variables.map((variable) => (
                  <li key={`${variable.key}-${variable.id ?? 'inherited'}`} className="flex items-center gap-4 px-4 py-3">
                    <span className="w-1/3 font-mono text-sm">{variable.key}</span>
                    <span className="text-muted-foreground flex-1 truncate font-mono text-sm">{variable.value}</span>
                    <Badge variant="outline">{variable.source}</Badge>
                    <div className="flex items-center gap-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        aria-label={`Edit ${variable.key}`}
                        onClick={() =>
                          dialog.variableForm.open({
                            applicationId: application.id,
                            environmentId: environment.id,
                            componentId: scopeComponentId,
                            variable,
                          })
                        }
                      >
                        <PencilIcon />
                      </Button>
                      {variable.id !== null && (
                        <Button
                          variant="ghost"
                          size="sm"
                          aria-label={`Remove ${variable.key}`}
                          onClick={() =>
                            dialog.confirm.open({
                              title: 'Remove variable',
                              description: `${variable.key} will be removed and the affected components marked for redeploy.`,
                              confirmLabel: 'Remove',
                              method: 'delete',
                              url: route('environments.variables.destroy', {
                                application: application.id,
                                environment: environment.id,
                                variable: variable.id,
                              }),
                            })
                          }
                        >
                          <TrashIcon />
                        </Button>
                      )}
                    </div>
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
