import { Head, usePage, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/ui/input-error';
import { LoaderCircleIcon } from 'lucide-react';
import EnvironmentLayout from '@/layouts/environment/layout';
import { useDialog } from '@/hooks/use-dialog';
import { Application, Environment } from '@/types/application';
import SelectBranch from '@/pages/source-controls/components/select-branch';

export default function EnvironmentSettings() {
  const page = usePage<{
    application: Application;
    environment: Environment;
    scripts: { id: number; name: string; content: string }[];
  }>();
  const { application, environment } = page.props;
  const dialog = useDialog();

  const form = useForm({
    name: environment.name,
    branch: environment.branch ?? '',
    color: environment.color,
    auto_deploy: environment.auto_deploy,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.put(route('environments.update', { application: application.id, environment: environment.id }), { preserveScroll: true });
  };

  return (
    <EnvironmentLayout>
      <Head title={`${environment.name} - Settings`} />

      <Container className="max-w-3xl">
        <HeaderContainer>
          <Heading title="Environment settings" description="The slug is fixed at creation because on-disk paths derive from it" />
        </HeaderContainer>

        <Card>
          <CardHeader>
            <CardTitle>General</CardTitle>
          </CardHeader>
          <CardContent className="p-4">
            <Form id="environment-settings-form" onSubmit={submit}>
              <FormFields>
                <FormField>
                  <Label htmlFor="name">Name</Label>
                  <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                  <InputError message={form.errors.name} />
                </FormField>

                <FormField>
                  <Label htmlFor="branch">Branch</Label>
                  <SelectBranch
                    sourceControlId={application.source_control_id?.toString() ?? ''}
                    repository={application.repository ?? ''}
                    value={form.data.branch}
                    onValueChange={(value) => form.setData('branch', value)}
                    placeholder="e.g. main, master, develop"
                  />
                  <InputError message={form.errors.branch} />
                </FormField>

                <FormField>
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id="auto_deploy"
                      checked={form.data.auto_deploy}
                      onCheckedChange={(checked) => form.setData('auto_deploy', checked === true)}
                    />
                    <Label htmlFor="auto_deploy">Deploy automatically on push</Label>
                  </div>
                  <InputError message={form.errors.auto_deploy} />
                </FormField>
              </FormFields>
              <Button type="submit" disabled={form.processing} className="mt-4">
                {form.processing && <LoaderCircleIcon className="animate-spin" />}
                Save
              </Button>
            </Form>
          </CardContent>
        </Card>
        <Card className="mt-4">
          <CardHeader>
            <CardTitle>Deploy pipeline</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-2 p-4">
            <p className="text-muted-foreground text-sm">
              These scripts run on every component during the matching stage. A component can override either one.
            </p>
            <div className="flex flex-wrap gap-2">
              {(page.props.scripts ?? []).map((script) => (
                <Button
                  key={script.id}
                  variant="outline"
                  onClick={() =>
                    dialog.deployScriptEditor.open({
                      applicationId: application.id,
                      environmentId: environment.id,
                      scriptId: script.id,
                      scriptName: script.name,
                      content: script.content,
                      scope: 'environment',
                    })
                  }
                >
                  Edit {script.name} script
                </Button>
              ))}
            </div>
          </CardContent>
        </Card>
      </Container>
    </EnvironmentLayout>
  );
}
