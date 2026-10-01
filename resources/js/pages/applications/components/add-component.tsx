import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormEvent } from 'react';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useConfigs } from '@/stores/bootstrap-store';
import IsolatedUserSelect from '@/pages/sites/components/isolated-user-select';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { PlusIcon, XIcon } from 'lucide-react';
import InputError from '@/components/ui/input-error';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Server } from '@/types/server';


type AddComponentForm = {
  name: string;
  server_id: string;
  type: string;
  user: string;
  php_version: string;
  web_directory: string;
  functions: string[];
};

export default function AddComponent({
  open,
  onOpenChange,
  applicationId,
  environmentId,
  servers,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  applicationId: number;
  environmentId: number;
  servers: Server[];
}) {
  const configs = useConfigs();
  const availableFunctions = configs?.component?.functions ?? {};
  const functionIds = Object.keys(availableFunctions);
  const form = useForm<AddComponentForm>({
    name: '',
    server_id: '',
    type: 'app',
    user: '',
    php_version: '',
    web_directory: '/public',
    functions: [],
  });

  const isApp = form.data.type === 'app';
  const allFunctionsAdded = functionIds.length > 0 && form.data.functions.length >= functionIds.length;
  const missingFunctions = isApp && form.data.functions.length === 0;

  const toggleFunction = (fn: string, checked: boolean) => {
    form.setData(
      'functions',
      checked ? [...form.data.functions, fn] : form.data.functions.filter((existing) => existing !== fn),
    );
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(route('components.store', { application: applicationId, environment: environmentId }), {
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
          <DialogTitle>Add component</DialogTitle>
          <DialogDescription className="sr-only">Place this environment onto a server</DialogDescription>
        </DialogHeader>
        <Form id="add-component-form" onSubmit={submit} className="max-h-[70vh] overflow-y-auto p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="name">Name</Label>
              <Input id="name" placeholder="web" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <InputError message={form.errors.name} />
            </FormField>

            <FormField>
              <Label htmlFor="type">Type</Label>
              <Select value={form.data.type} onValueChange={(value) => form.setData('type', value)}>
                <SelectTrigger id="type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="app">App</SelectItem>
                    <SelectItem value="load_balancer">Load balancer</SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
              <InputError message={form.errors.type} />
            </FormField>

            <FormField>
              <Label htmlFor="server_id">Server</Label>
              <Select value={form.data.server_id} onValueChange={(value) => form.setData('server_id', value)}>
                <SelectTrigger id="server_id">
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
              <Label htmlFor="user">User</Label>
              <IsolatedUserSelect
                serverId={Number(form.data.server_id)}
                value={form.data.user}
                onValueChange={(value) => form.setData('user', value)}
              />
              <InputError message={form.errors.user} />
            </FormField>

            {isApp && (
              <>
                <FormField>
                  <Label>Functions</Label>
                  <div className="flex flex-col items-start gap-2">
                    {/* The placeholder shares this row so its height matches a row of tags. */}
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
                                // Keep the menu open while functions remain, so several can be
                                // added in one pass; the last one lets it close.
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
                  <Input
                    id="php_version"
                    placeholder="8.4"
                    value={form.data.php_version}
                    onChange={(e) => form.setData('php_version', e.target.value)}
                  />
                  <InputError message={form.errors.php_version} />
                </FormField>

                <FormField>
                  <Label htmlFor="web_directory">Web directory</Label>
                  <Input
                    id="web_directory"
                    value={form.data.web_directory}
                    onChange={(e) => form.setData('web_directory', e.target.value)}
                  />
                  <InputError message={form.errors.web_directory} />
                </FormField>
              </>
            )}
          </FormFields>
        </Form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button form="add-component-form" type="submit" disabled={form.processing || missingFunctions}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Add
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
