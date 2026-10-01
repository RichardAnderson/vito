import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormEvent } from 'react';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SourceControl } from '@/types/source-control';
import SelectRepo from '@/pages/source-controls/components/select-repo';
import SelectBranch from '@/pages/source-controls/components/select-branch';

type CreateApplicationForm = {
  name: string;
  source_control_id: string;
  repository: string;
  production_branch: string;
};

export default function CreateApplication({
  open,
  onOpenChange,
  sourceControls,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  sourceControls: SourceControl[];
}) {
  const form = useForm<CreateApplicationForm>({
    name: '',
    source_control_id: '',
    repository: '',
    production_branch: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(route('applications.store'), {
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
          <DialogTitle>Create application</DialogTitle>
          <DialogDescription className="sr-only">Create a new application</DialogDescription>
        </DialogHeader>
        <Form id="create-application-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="name">Name</Label>
              <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <InputError message={form.errors.name} />
            </FormField>

            <FormField>
              <Label htmlFor="source_control_id">Source control</Label>
              <Select value={form.data.source_control_id} onValueChange={(value) => form.setData('source_control_id', value)}>
                <SelectTrigger id="source_control_id">
                  <SelectValue placeholder="Select a source control" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    {sourceControls.map((sourceControl) => (
                      <SelectItem key={sourceControl.id} value={sourceControl.id.toString()}>
                        {sourceControl.name || sourceControl.provider}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                </SelectContent>
              </Select>
              <InputError message={form.errors.source_control_id} />
            </FormField>

            <FormField>
              <Label htmlFor="repository">Repository</Label>
              <SelectRepo
                sourceControlId={form.data.source_control_id}
                value={form.data.repository}
                onValueChange={(value) => form.setData('repository', value)}
                placeholder="organization/repository"
              />
              <InputError message={form.errors.repository} />
            </FormField>

            <FormField>
              <Label htmlFor="production_branch">Production branch</Label>
              <SelectBranch
                sourceControlId={form.data.source_control_id}
                repository={form.data.repository}
                value={form.data.production_branch}
                onValueChange={(value) => form.setData('production_branch', value)}
                placeholder="e.g. main, master"
                allowClear
              />
              <p className="text-muted-foreground text-sm">Optional. When set, a "Production" environment is created on this branch.</p>
              <InputError message={form.errors.production_branch} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button form="create-application-form" type="submit" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Create
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
