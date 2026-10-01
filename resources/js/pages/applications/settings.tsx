import { Head, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import Container from '@/components/container';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { LoaderCircleIcon } from 'lucide-react';
import Layout from '@/layouts/app/layout';
import { useForm } from '@inertiajs/react';
import { Application } from '@/types/application';

export default function ApplicationSettings() {
  const page = usePage<{ application: Application }>();
  const application = page.props.application;

  const form = useForm({
    name: application.name,
    repository: application.repository ?? '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.put(route('applications.update', { application: application.id }), { preserveScroll: true });
  };

  return (
    <Layout>
      <Head title={`${application.name} - Settings`} />

      <Container className="max-w-3xl">
        <HeaderContainer>
          <Heading title="Settings" description="Rename this application or point it at a different repository" />
        </HeaderContainer>

        <Card>
          <CardHeader>
            <CardTitle>General</CardTitle>
          </CardHeader>
          <CardContent className="p-4">
            <Form id="application-settings-form" onSubmit={submit}>
              <FormFields>
                <FormField>
                  <Label htmlFor="name">Name</Label>
                  <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                  <InputError message={form.errors.name} />
                </FormField>

                <FormField>
                  <Label htmlFor="repository">Repository</Label>
                  <Input id="repository" value={form.data.repository} onChange={(e) => form.setData('repository', e.target.value)} />
                  <InputError message={form.errors.repository} />
                </FormField>
              </FormFields>
              <Button type="submit" disabled={form.processing} className="mt-4">
                {form.processing && <LoaderCircleIcon className="animate-spin" />}
                Save
              </Button>
            </Form>
          </CardContent>
        </Card>
      </Container>
    </Layout>
  );
}
