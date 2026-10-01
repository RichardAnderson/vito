import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { LoaderCircleIcon, PlusIcon, XIcon } from 'lucide-react';
import EnvironmentLayout from '@/layouts/environment/layout';
import { useDialog } from '@/hooks/use-dialog';
import { useConfigs } from '@/stores/bootstrap-store';
import { Application, Component, Environment } from '@/types/application';

export default function ComponentSettings() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    component: Component;
  }>();
  const dialog = useDialog();
  const configs = useConfigs();
  const { application, environment, component } = page.props;

  const availableFunctions = configs?.component?.functions ?? {};
  const functionIds = Object.keys(availableFunctions);

  const form = useForm({
    name: component.name,
    php_version: component.php_version ?? '',
    web_directory: component.web_directory ?? '',
    functions: (component.functions ?? []).map((fn) => fn.function),
  });

  const allFunctionsAdded = functionIds.length > 0 && form.data.functions.length >= functionIds.length;
  const isApp = component.type_value === 'app';

  const toggleFunction = (id: string, checked: boolean) =>
    form.setData('functions', checked ? [...form.data.functions, id] : form.data.functions.filter((existing) => existing !== id));

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.put(
      route('components.update', { application: application.id, environment: environment.id, component: component.id }),
      { preserveScroll: true },
    );
  };

  return (
    <EnvironmentLayout>
      <Head title={`${component.name} - Settings`} />

      <Container className="max-w-3xl">
        <HeaderContainer>
          <Heading title="Component settings" description={`${component.name} on ${component.server ?? 'unknown server'}`} />
        </HeaderContainer>

        <Card>
          <CardHeader>
            <CardTitle>General</CardTitle>
          </CardHeader>
          <CardContent className="p-4">
            <Form id="component-settings-form" onSubmit={submit}>
              <FormFields>
                <FormField>
                  <Label htmlFor="name">Name</Label>
                  <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                  <InputError message={form.errors.name} />
                </FormField>

                {isApp && (
                  <>
                    <FormField>
                      <Label>Functions</Label>
                      <div className="flex flex-col items-start gap-2">
                        <div className="flex min-h-[26px] flex-wrap items-center gap-2">
                          {form.data.functions.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Add at least one function so this component does something.</p>
                          ) : (
                            form.data.functions.map((id) => (
                              <Badge key={id} variant="outline" className="gap-1 py-1 pr-1 pl-2">
                                {availableFunctions[id]?.label ?? id}
                                <button
                                  type="button"
                                  onClick={() => toggleFunction(id, false)}
                                  aria-label={`Remove ${availableFunctions[id]?.label ?? id}`}
                                  className="hover:bg-accent rounded-sm p-0.5"
                                >
                                  <XIcon className="size-3" />
                                </button>
                              </Badge>
                            ))
                          )}
                        </div>

                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button type="button" variant="outline" size="sm" disabled={allFunctionsAdded}>
                              <PlusIcon />
                              Add function
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="start" className="w-64">
                            {Object.entries(availableFunctions).map(([id, fn]) => {
                              const added = form.data.functions.includes(id);

                              return (
                                <DropdownMenuItem
                                  key={id}
                                  disabled={added}
                                  onSelect={(e) => {
                                    if (form.data.functions.length + 1 < functionIds.length) {
                                      e.preventDefault();
                                    }
                                    toggleFunction(id, true);
                                  }}
                                  className="flex flex-col items-start gap-0.5"
                                >
                                  <span className="font-medium">{fn.label}</span>
                                  <span className="text-muted-foreground text-xs">{fn.description}</span>
                                </DropdownMenuItem>
                              );
                            })}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                      <InputError message={form.errors.functions} />
                    </FormField>

                    <FormField>
                      <Label htmlFor="php_version">PHP version</Label>
                      <Input id="php_version" value={form.data.php_version} onChange={(e) => form.setData('php_version', e.target.value)} />
                      <InputError message={form.errors.php_version} />
                    </FormField>

                    <FormField>
                      <Label htmlFor="web_directory">Web directory</Label>
                      <Input id="web_directory" value={form.data.web_directory} onChange={(e) => form.setData('web_directory', e.target.value)} />
                      <InputError message={form.errors.web_directory} />
                    </FormField>
                  </>
                )}
              </FormFields>
              <Button type="submit" disabled={form.processing || (isApp && form.data.functions.length === 0)} className="mt-4">
                {form.processing && <LoaderCircleIcon className="animate-spin" />}
                Save
              </Button>
            </Form>
          </CardContent>
        </Card>

        <Card className="border-destructive/30 mt-4 overflow-hidden">
          <CardHeader>
            <CardTitle>Danger zone</CardTitle>
            <CardDescription>
              Deleting this component removes its vhost, workers, scheduler entry and code checkout from the server. Attached databases are left alone.
            </CardDescription>
          </CardHeader>
          <CardContent className="bg-background">
            <div className="p-4">
              <Button
                variant="destructive"
                onClick={() =>
                  dialog.confirm.open({
                    title: `Delete ${component.name}`,
                    description:
                      'The vhost, workers, scheduler entry and code checkout are removed from the server. Attached databases are left alone.',
                    confirmLabel: 'Delete',
                    variant: 'destructive',
                    method: 'delete',
                    url: route('components.destroy', {
                      application: application.id,
                      environment: environment.id,
                      component: component.id,
                    }),
                    onSuccess: () =>
                      router.visit(route('environments.components', { application: application.id, environment: environment.id })),
                  })
                }
              >
                Delete component
              </Button>
            </div>
          </CardContent>
        </Card>
      </Container>
    </EnvironmentLayout>
  );
}
