import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormEvent } from 'react';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { EnvironmentDomain } from '@/types/application';

type DomainForm = {
  host: string;
  type: string;
  redirect_to_domain_id: string;
  ssl_method: string;
};

export default function DomainFormDialog({
  open,
  onOpenChange,
  applicationId,
  environmentId,
  aliases,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  applicationId: number;
  environmentId: number;
  aliases: EnvironmentDomain[];
}) {
  const form = useForm<DomainForm>({
    host: '',
    type: 'alias',
    redirect_to_domain_id: '',
    ssl_method: 'letsencrypt',
  });

  const isRedirect = form.data.type === 'redirect';
  const isWildcard = form.data.host.startsWith('*.');
  const hasAliases = aliases.length > 0;

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(route('environments.domains.store', { application: applicationId, environment: environmentId }), {
      onSuccess: () => {
        onOpenChange(false);
        form.reset();
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg" onCloseAutoFocus={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle>Add domain</DialogTitle>
          <DialogDescription className="sr-only">Point a hostname at this environment</DialogDescription>
        </DialogHeader>
        <Form id="domain-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="host">Host</Label>
              <Input id="host" placeholder="app.example.com" value={form.data.host} onChange={(e) => form.setData('host', e.target.value)} />
              <InputError message={form.errors.host} />
            </FormField>

            {isWildcard && (
              <Alert>
                <AlertDescription>
                  A wildcard host is issued over DNS-01, so the parent zone must be connected to a DNS provider in this project.
                </AlertDescription>
              </Alert>
            )}

            <FormField>
              <Label htmlFor="type">Type</Label>
              <Select value={form.data.type} onValueChange={(value) => form.setData('type', value)}>
                <SelectTrigger id="type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="alias">Alias</SelectItem>
                    <SelectItem value="redirect">Redirect</SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
              <p className="text-muted-foreground text-sm">
                {isRedirect ? 'Forwards visitors to one of this environment’s aliases.' : 'Served by this environment’s primary component.'}
              </p>
              <InputError message={form.errors.type} />
            </FormField>

            {isRedirect &&
              (hasAliases ? (
                <FormField>
                  <Label htmlFor="redirect_to_domain_id">Redirect to</Label>
                  <Select
                    value={form.data.redirect_to_domain_id}
                    onValueChange={(value) => form.setData('redirect_to_domain_id', value)}
                  >
                    <SelectTrigger id="redirect_to_domain_id">
                      <SelectValue placeholder="Select an alias" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectGroup>
                        {aliases.map((alias) => (
                          <SelectItem key={alias.id} value={alias.id.toString()}>
                            {alias.host}
                          </SelectItem>
                        ))}
                      </SelectGroup>
                    </SelectContent>
                  </Select>
                  <InputError message={form.errors.redirect_to_domain_id} />
                </FormField>
              ) : (
                <Alert variant="destructive">
                  <AlertDescription>
                    This environment has no alias to redirect to yet. Add a domain of type Alias first.
                  </AlertDescription>
                </Alert>
              ))}

            <FormField>
              <Label htmlFor="ssl_method">SSL</Label>
              <Select value={form.data.ssl_method} onValueChange={(value) => form.setData('ssl_method', value)}>
                <SelectTrigger id="ssl_method">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="letsencrypt">Let's Encrypt</SelectItem>
                    <SelectItem value="custom">Custom certificate</SelectItem>
                    <SelectItem value="none">None</SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
              <InputError message={form.errors.ssl_method} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button form="domain-form" type="submit" disabled={form.processing || (isRedirect && !hasAliases)}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Add
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
