import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { ClipboardCheckIcon, ClipboardIcon, LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export type ResourceOption = { id: number; name: string; server_id: number };
export type ServerOption = { id: number; name: string };

type AttachForm = {
  server_id: string;
  resource_type: string;
  source: string;
  resource_id: string;
  name: string;
  charset: string;
  collation: string;
  alias: string;
  user: string;
  password: string;
};

const ALIAS_PATTERN = /^[A-Z][A-Z0-9_]*$/;

export default function AttachResource({
  open,
  onOpenChange,
  applicationId,
  environmentId,
  servers,
  databases,
  redis,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  applicationId: number;
  environmentId: number;
  servers: ServerOption[];
  databases: ResourceOption[];
  redis: ResourceOption[];
}) {
  const [result, setResult] = useState<{ alias: string | null; password: string | null; credentials_pending: boolean } | null>(null);
  const [copied, setCopied] = useState(false);
  const [charsets, setCharsets] = useState<string[]>([]);
  const [collations, setCollations] = useState<string[]>([]);

  const form = useForm<AttachForm>({
    server_id: '',
    resource_type: 'database',
    source: 'existing',
    resource_id: '',
    name: '',
    charset: '',
    collation: '',
    alias: '',
    user: '',
    password: '',
  });

  const isDatabase = form.data.resource_type === 'database';
  const isNew = isDatabase && form.data.source === 'new';

  const options = useMemo(() => {
    if (!form.data.server_id) return [];
    const pool = isDatabase ? databases : redis;

    return pool.filter((option) => option.server_id === Number(form.data.server_id));
  }, [form.data.server_id, isDatabase, databases, redis]);

  const serverId = form.data.server_id;
  const charset = form.data.charset;
  const { setData } = form;

  // A new database needs a charset the server actually offers; Postgres in particular rejects a
  // collation that is not in that charset's list.
  useEffect(() => {
    if (!isNew || !serverId) {
      setCharsets([]);

      return;
    }

    let cancelled = false;

    axios
      .get(route('databases.charsets', { server: serverId }))
      .then((response) => {
        if (cancelled) return;
        setCharsets(response.data);
        setData('charset', response.data[0] ?? '');
      })
      .catch(() => {
        if (cancelled) return;
        setCharsets([]);
      });

    return () => {
      cancelled = true;
    };
  }, [isNew, serverId, setData]);

  useEffect(() => {
    if (!isNew || !serverId || !charset) {
      setCollations([]);

      return;
    }

    let cancelled = false;

    axios
      .get(route('databases.collations', { server: serverId, charset }))
      .then((response) => {
        if (cancelled) return;
        setCollations(response.data.list ?? []);
        setData('collation', response.data.default ?? response.data.list?.[0] ?? '');
      })
      .catch(() => {
        if (cancelled) return;
        setCollations([]);
        setData('collation', '');
      });

    return () => {
      cancelled = true;
    };
  }, [isNew, serverId, charset, setData]);

  const aliasInvalid = form.data.alias.length > 0 && !ALIAS_PATTERN.test(form.data.alias);
  const aliasRequired = isNew;

  const reset = () => {
    form.reset();
    form.clearErrors();
    setResult(null);
    setCopied(false);
  };

  const handleOpenChange = (next: boolean) => {
    onOpenChange(next);
    if (!next) {
      reset();
    }
  };

  const copyPassword = () => {
    navigator.clipboard
      .writeText(result?.password ?? '')
      .then(() => {
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
      })
      .catch(() => setCopied(false));
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();

    if (isDatabase && (isNew || form.data.alias || form.data.user)) {
      form.post(route('environments.resources.databases', { application: applicationId, environment: environmentId }), {
        preserveScroll: true,
        onSuccess: (page) => {
          const flash = page.props.flash as { data?: { alias?: string; password?: string; credentials_pending?: boolean } };
          setResult({
            alias: flash.data?.alias ?? null,
            password: flash.data?.password ?? null,
            credentials_pending: flash.data?.credentials_pending ?? false,
          });
        },
      });

      return;
    }

    form.post(route('environments.resources.store', { application: applicationId, environment: environmentId }), {
      preserveScroll: true,
      onSuccess: () => handleOpenChange(false),
    });
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-h-screen overflow-y-auto sm:max-w-lg" onCloseAutoFocus={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle>{result ? 'Database attached' : 'Attach a resource'}</DialogTitle>
          <DialogDescription className="sr-only">Attach a database or service to this environment</DialogDescription>
        </DialogHeader>

        {result ? (
          <div className="flex flex-col gap-4 p-4">
            {result.password && (
              <>
                <FormField>
                  <Label htmlFor="generated-password" className="flex items-center gap-1">
                    Password {copied ? <ClipboardCheckIcon className="text-success! size-4" /> : <ClipboardIcon className="size-4" />}
                  </Label>
                  <Input id="generated-password" readOnly value={result.password} onClick={copyPassword} className="cursor-pointer" />
                </FormField>
                <Alert>
                  <AlertDescription>
                    This password is shown once and never again. It has been saved as the secret <strong>DB_{result.alias}_PASSWORD</strong> on the
                    Variables page, alongside DB_{result.alias}_NAME and DB_{result.alias}_USER.
                  </AlertDescription>
                </Alert>
              </>
            )}

            {result.credentials_pending && (
              <Alert variant="destructive">
                <AlertDescription>
                  No database user was created, so only <strong>DB_{result.alias}_NAME</strong> was saved. Add{' '}
                  <strong>DB_{result.alias}_USER</strong> and <strong>DB_{result.alias}_PASSWORD</strong> yourself on the Variables page.
                </AlertDescription>
              </Alert>
            )}
          </div>
        ) : (
          <Form id="attach-resource-form" onSubmit={submit} className="p-4">
            <FormFields>
              <FormField>
                <Label htmlFor="server_id">Server</Label>
                <Select
                  value={form.data.server_id}
                  onValueChange={(value) => {
                    form.setData('server_id', value);
                    form.setData('resource_id', '');
                  }}
                >
                  <SelectTrigger id="server_id" className="w-full">
                    <SelectValue placeholder="Select a server" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
                      {servers.map((server) => (
                        <SelectItem key={server.id} value={server.id.toString()}>
                          {server.name}
                        </SelectItem>
                      ))}
                    </SelectGroup>
                  </SelectContent>
                </Select>
                <InputError message={form.errors.server_id} />
              </FormField>

              <FormField>
                <Label htmlFor="resource_type">Type</Label>
                <Select
                  value={form.data.resource_type}
                  onValueChange={(value) => {
                    form.setData('resource_type', value);
                    form.setData('resource_id', '');
                    form.setData('source', 'existing');
                  }}
                >
                  <SelectTrigger id="resource_type" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
                      <SelectItem value="database">Database</SelectItem>
                      <SelectItem value="redis">Redis</SelectItem>
                    </SelectGroup>
                  </SelectContent>
                </Select>
              </FormField>

              {isDatabase && (
                <FormField>
                  <Label htmlFor="source">Source</Label>
                  <Select value={form.data.source} onValueChange={(value) => form.setData('source', value)}>
                    <SelectTrigger id="source" className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectGroup>
                        <SelectItem value="existing">Use an existing database</SelectItem>
                        <SelectItem value="new">Create a new database</SelectItem>
                      </SelectGroup>
                    </SelectContent>
                  </Select>
                </FormField>
              )}

              {isNew ? (
                <>
                  <FormField>
                    <Label htmlFor="name">Database name</Label>
                    <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <InputError message={form.errors.name} />
                  </FormField>

                  <FormField>
                    <Label htmlFor="charset">Charset</Label>
                    <Select value={form.data.charset} onValueChange={(value) => form.setData('charset', value)} disabled={charsets.length === 0}>
                      <SelectTrigger id="charset" className="w-full">
                        <SelectValue placeholder={serverId ? 'Loading charsets...' : 'Select a server first'} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectGroup>
                          {charsets.map((option) => (
                            <SelectItem key={option} value={option}>
                              {option}
                            </SelectItem>
                          ))}
                        </SelectGroup>
                      </SelectContent>
                    </Select>
                    <InputError message={form.errors.charset} />
                  </FormField>

                  <FormField>
                    <Label htmlFor="collation">Collation</Label>
                    <Select value={form.data.collation} onValueChange={(value) => form.setData('collation', value)} disabled={collations.length === 0}>
                      <SelectTrigger id="collation" className="w-full">
                        <SelectValue placeholder={charset ? 'Loading collations...' : 'Select a charset first'} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectGroup>
                          {collations.map((option) => (
                            <SelectItem key={option} value={option}>
                              {option}
                            </SelectItem>
                          ))}
                        </SelectGroup>
                      </SelectContent>
                    </Select>
                    <InputError message={form.errors.collation} />
                  </FormField>
                </>
              ) : (
                <FormField>
                  <Label htmlFor="resource_id">Resource</Label>
                  <Select
                    value={form.data.resource_id}
                    onValueChange={(value) => form.setData('resource_id', value)}
                    disabled={!form.data.server_id || options.length === 0}
                  >
                    <SelectTrigger id="resource_id" className="w-full">
                      <SelectValue
                        placeholder={
                          !form.data.server_id
                            ? 'Select a server first'
                            : options.length === 0
                              ? `No ${isDatabase ? 'database' : 'Redis service'} on that server`
                              : 'Select a resource'
                        }
                      />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectGroup>
                        {options.map((option) => (
                          <SelectItem key={option.id} value={option.id.toString()}>
                            {option.name}
                          </SelectItem>
                        ))}
                      </SelectGroup>
                    </SelectContent>
                  </Select>
                  <InputError message={form.errors.resource_id} />
                </FormField>
              )}

              {isDatabase && (
                <>
                  <FormField>
                    <Label htmlFor="alias">Alias{aliasRequired ? '' : ' (optional)'}</Label>
                    <Input
                      id="alias"
                      placeholder="PRIMARY"
                      value={form.data.alias}
                      onChange={(e) => form.setData('alias', e.target.value.toUpperCase().replace(/[^A-Z0-9_]/g, ''))}
                    />
                    <p className="text-muted-foreground text-sm">
                      Uppercase letters, digits and underscores. Referenced as DB_{form.data.alias || 'ALIAS'}_NAME, _USER and _PASSWORD.
                    </p>
                    {aliasInvalid && <p className="text-destructive text-sm">An alias must start with a letter.</p>}
                    <InputError message={form.errors.alias} />
                  </FormField>

                  <FormField>
                    <Label htmlFor="user">Database user{isNew ? '' : ' (optional)'}</Label>
                    <Input id="user" value={form.data.user} onChange={(e) => form.setData('user', e.target.value)} />
                    <InputError message={form.errors.user} />
                  </FormField>

                  {isNew ? (
                    <Alert>
                      <AlertDescription>The password is generated for you and shown once after the database is created.</AlertDescription>
                    </Alert>
                  ) : (
                    <>
                      <FormField>
                        <Label htmlFor="password">Password (optional)</Label>
                        <Input
                          id="password"
                          type="password"
                          value={form.data.password}
                          onChange={(e) => form.setData('password', e.target.value)}
                        />
                        <InputError message={form.errors.password} />
                      </FormField>
                      <Alert>
                        <AlertDescription>
                          Leave the user and password empty to attach the database without creating a user. You will need to add
                          DB_{form.data.alias || 'ALIAS'}_USER and DB_{form.data.alias || 'ALIAS'}_PASSWORD yourself.
                        </AlertDescription>
                      </Alert>
                    </>
                  )}
                </>
              )}
            </FormFields>
          </Form>
        )}

        <DialogFooter>
          {result ? (
            <Button onClick={() => handleOpenChange(false)}>Done</Button>
          ) : (
            <>
              <Button variant="outline" onClick={() => handleOpenChange(false)}>
                Cancel
              </Button>
              <Button form="attach-resource-form" type="submit" disabled={form.processing || aliasInvalid || (aliasRequired && !form.data.alias)}>
                {form.processing && <LoaderCircleIcon className="animate-spin" />}
                {isNew ? 'Create' : 'Attach'}
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
