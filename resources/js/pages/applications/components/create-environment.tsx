import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormEvent } from 'react';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import ColorSelect from '@/components/color-select';
import SelectBranch from '@/pages/source-controls/components/select-branch';
import { Application } from '@/types/application';

type CreateEnvironmentForm = {
  name: string;
  branch: string;
  color: string;
  auto_deploy: boolean;
};

export default function CreateEnvironment({
  open,
  onOpenChange,
  application,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  application: Application;
}) {
  const form = useForm<CreateEnvironmentForm>({
    name: '',
    branch: 'main',
    color: 'slate',
    auto_deploy: false,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(route('environments.store', { application: application.id }), {
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
          <DialogTitle>Create environment</DialogTitle>
          <DialogDescription className="sr-only">Create a new environment for this application</DialogDescription>
        </DialogHeader>
        <Form id="create-environment-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="name">Name</Label>
              <Input id="name" placeholder="Production" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
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
              <Label htmlFor="color">Color</Label>
              <ColorSelect value={form.data.color} onValueChange={(value) => form.setData('color', value)} />
              <InputError message={form.errors.color} />
            </FormField>

            <FormField>
              <div className="flex items-center gap-2">
                <Checkbox
                  id="auto_deploy"
                  checked={form.data.auto_deploy}
                  onCheckedChange={(checked) => form.setData('auto_deploy', checked === true)}
                />
                <Label htmlFor="auto_deploy">Deploy automatically on push to this branch</Label>
              </div>
              <InputError message={form.errors.auto_deploy} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button form="create-environment-form" type="submit" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Create
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
