import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/ui/input-error';
import { Switch } from '@/components/ui/switch';
import { MultiSelect } from '@/components/multi-select';
import StorageProviderSelect from '@/pages/storage-providers/components/storage-provider-select';
import FormSuccessful from '@/components/form-successful';
import { Site } from '@/types/site';
import { SiteDeploymentBackup } from '@/types/site-deployment-backup';

export default function BackupConfig({
  open,
  onOpenChange,
  site,
  deploymentBackup,
  availableDatabases,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  site: Site;
  deploymentBackup: SiteDeploymentBackup | null;
  availableDatabases: Array<{ id: number; name: string }>;
}) {
  const [databasesEnabled, setDatabasesEnabled] = useState<boolean>((deploymentBackup?.databases?.length ?? 0) > 0);

  const form = useForm<{
    enabled: boolean;
    folders: string[];
    databases: string[];
    storage_id: string;
    keep: string;
  }>({
    enabled: deploymentBackup?.enabled ?? true,
    folders: deploymentBackup?.folders ?? [site.path],
    databases: (deploymentBackup?.databases ?? []).map((id) => id.toString()),
    storage_id: deploymentBackup?.storage_id?.toString() ?? '',
    keep: (deploymentBackup?.keep ?? 5).toString(),
  });

  const databaseOptions = availableDatabases.map((database) => ({
    value: database.id.toString(),
    label: database.name,
  }));

  const folderError = form.errors.folders ?? Object.entries(form.errors).find(([key]) => key.startsWith('folders.'))?.[1];
  const databaseError = form.errors.databases ?? Object.entries(form.errors).find(([key]) => key.startsWith('databases.'))?.[1];

  const toggleDatabases = (value: boolean) => {
    setDatabasesEnabled(value);
    if (!value) {
      form.setData('databases', []);
    }
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.transform((data) => ({
      ...data,
      folders: data.folders.map((folder) => folder.trim()).filter((folder) => folder.length > 0),
    }));
    form.put(route('application.update-deployment-backup', { server: site.server_id, site: site.id }), {
      onSuccess: () => onOpenChange(false),
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg" onCloseAutoFocus={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle>Backup before deployment</DialogTitle>
          <DialogDescription>Capture folders and databases before each deployment so you can restore them later.</DialogDescription>
        </DialogHeader>
        <Form id="backup-config-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <div className="flex items-center space-x-2">
                <Switch id="enabled" checked={form.data.enabled} onCheckedChange={(value) => form.setData('enabled', value)} />
                <Label htmlFor="enabled">Take a backup before each deployment</Label>
                <InputError message={form.errors.enabled} />
              </div>
            </FormField>

            {form.data.enabled && (
              <>
                <FormField>
                  <Label htmlFor="folders">Folders</Label>
                  <Textarea
                    id="folders"
                    value={form.data.folders.join('\n')}
                    onChange={(e) => form.setData('folders', e.target.value.split('\n'))}
                    placeholder={site.path}
                    rows={3}
                  />
                  <p className="text-muted-foreground text-sm">One absolute path per line, within the site directory. Defaults to the site folder.</p>
                  <InputError message={folderError} />
                </FormField>

                <FormField>
                  <div className="flex items-center space-x-2">
                    <Switch id="databases_enabled" checked={databasesEnabled} onCheckedChange={toggleDatabases} />
                    <Label htmlFor="databases_enabled">Back up databases</Label>
                  </div>
                </FormField>

                {databasesEnabled && (
                  <FormField>
                    <Label htmlFor="databases">Databases</Label>
                    <MultiSelect
                      options={databaseOptions}
                      onValueChange={(value) => form.setData('databases', value)}
                      defaultValue={form.data.databases}
                      placeholder="Select databases"
                      maxCount={5}
                    />
                    <InputError className="mt-2" message={databaseError} />
                  </FormField>
                )}

                <FormField>
                  <Label htmlFor="storage_id">Storage provider</Label>
                  <StorageProviderSelect
                    id="storage_id"
                    value={form.data.storage_id}
                    onValueChange={(value) => form.setData('storage_id', value)}
                  />
                  <InputError message={form.errors.storage_id} />
                </FormField>

                <FormField>
                  <Label htmlFor="keep">Keep backups for last N deployments</Label>
                  <Input
                    id="keep"
                    type="number"
                    min={1}
                    value={form.data.keep}
                    onChange={(e) => form.setData('keep', e.target.value)}
                  />
                  <InputError message={form.errors.keep} />
                </FormField>
              </>
            )}
          </FormFields>
        </Form>
        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Cancel</Button>
          </DialogClose>
          <Button form="backup-config-form" type="submit" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            <FormSuccessful successful={form.recentlySuccessful} />
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
