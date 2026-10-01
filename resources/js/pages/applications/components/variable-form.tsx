import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormEvent } from 'react';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/ui/input-error';

type VariableRow = {
  id: number | null;
  key: string;
  value: string | null;
  is_secret: boolean;
};

export default function VariableForm({
  open,
  onOpenChange,
  applicationId,
  environmentId,
  componentId,
  variable,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  applicationId: number;
  environmentId: number;
  componentId: number | null;
  variable?: VariableRow;
}) {
  const form = useForm({
    id: variable?.id ?? '',
    key: variable?.key ?? '',
    value: '',
    is_secret: variable?.is_secret ?? false,
    component_id: componentId ?? '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(route('environments.variables.store', { application: applicationId, environment: environmentId }), {
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
          <DialogTitle>{variable ? 'Edit variable' : 'Add variable'}</DialogTitle>
          <DialogDescription className="sr-only">Set a value for this environment or component</DialogDescription>
        </DialogHeader>
        <Form id="variable-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="key">Key</Label>
              <Input id="key" placeholder="APP_ENV" value={form.data.key} onChange={(e) => form.setData('key', e.target.value)} />
              <InputError message={form.errors.key} />
            </FormField>

            <FormField>
              <Label htmlFor="value">Value</Label>
              <Input id="value" value={form.data.value} onChange={(e) => form.setData('value', e.target.value)} />
              {variable?.is_secret && (
                <p className="text-muted-foreground text-xs">Leave blank to keep the stored secret unchanged.</p>
              )}
              <InputError message={form.errors.value} />
            </FormField>

            <FormField>
              <div className="flex items-center gap-2">
                <Checkbox
                  id="is_secret"
                  checked={form.data.is_secret}
                  onCheckedChange={(checked) => form.setData('is_secret', checked === true)}
                />
                <Label htmlFor="is_secret">Secret — mask the value everywhere</Label>
              </div>
              <InputError message={form.errors.is_secret} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button form="variable-form" type="submit" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
