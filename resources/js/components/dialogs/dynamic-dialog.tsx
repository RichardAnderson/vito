import { FormEvent, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Form, FormFields } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import DynamicField from '@/components/ui/dynamic-field';
import { LoaderCircleIcon } from 'lucide-react';
import type { ActionRef, DataRef, DialogNode } from '@/types/dynamic-page';
import type { DynamicFieldConfig } from '@/types/dynamic-field-config';
import { CodeEditor } from '@/pages/dynamic/components/code-editor';
import { LogView } from '@/pages/dynamic/components/log-view';
import { DynamicPageProvider } from '@/pages/dynamic/page-context';

type FormValue = string | number | boolean | string[];

export type DynamicDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  dialog: DialogNode;
  actions: Record<string, ActionRef>;
  data: Record<string, DataRef>;
  context?: Record<string, unknown>;
};

export default function DynamicDialog({ open, onOpenChange, dialog, actions, data, context }: DynamicDialogProps) {
  const action = dialog.action ? actions[dialog.action] : undefined;
  const fields = dialog.form ?? action?.form ?? [];

  const initial: Record<string, FormValue> = {};
  for (const field of fields) {
    initial[field.name] = (context?.[field.name] as FormValue) ?? (field.default as FormValue) ?? '';
  }

  const form = useForm<Record<string, FormValue>>(initial);
  const [confirmInput, setConfirmInput] = useState('');
  const [busyFields, setBusyFields] = useState<Set<string>>(new Set());

  const fieldBusySetter = (name: string) => (busy: boolean) =>
    setBusyFields((prev) => {
      const next = new Set(prev);
      if (busy) {
        next.add(name);
      } else {
        next.delete(name);
      }
      return next;
    });

  const dispatch = (extra: Record<string, unknown> = {}) => {
    if (!action) {
      onOpenChange(false);
      return;
    }
    const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };
    const payload: Record<string, unknown> = { ...form.data, ...extra };
    if (dialog.confirmText) {
      payload[dialog.confirmField] = confirmInput;
    }
    form.transform(() => payload as Record<string, FormValue>);
    if (action.method === 'delete') {
      form.delete(action.url, options);
    } else if (action.method === 'patch') {
      form.patch(action.url, options);
    } else if (action.method === 'put') {
      form.put(action.url, options);
    } else {
      form.post(action.url, options);
    }
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    dispatch();
  };

  const isFullBleed = Boolean(dialog.editor || dialog.logView);
  const generalError = Object.values(form.errors)[0] as string | undefined;

  const body = (
    <DynamicPageProvider value={{ actions, data }}>
      {dialog.editor && <CodeEditor node={dialog.editor} />}
      {dialog.logView && <LogView node={dialog.logView} context={context} />}
      {!isFullBleed && (
        <div className="flex flex-col gap-4 p-4">
          {fields.length > 0 && (
            <Form id={`dialog-form-${dialog.id}`} onSubmit={submit}>
              <FormFields>
                {fields.map((field: DynamicFieldConfig) => (
                  <DynamicField
                    key={field.name}
                    config={field}
                    value={form.data[field.name]}
                    onChange={(value) => form.setData(field.name, value)}
                    error={form.errors[field.name as keyof typeof form.errors] as string | undefined}
                    form={{ data: form.data, setData: (name, value) => form.setData(name, value as FormValue) }}
                    data={data}
                    setBusy={fieldBusySetter(field.name)}
                  />
                ))}
              </FormFields>
            </Form>
          )}
          {dialog.confirmText && (
            <div className="flex flex-col gap-2">
              <p className="text-muted-foreground text-sm">
                Type <span className="text-foreground font-mono font-semibold">{dialog.confirmText}</span> to confirm.
              </p>
              <Label htmlFor="confirm-text" className="sr-only">
                Confirmation
              </Label>
              <Input id="confirm-text" value={confirmInput} onChange={(e) => setConfirmInput(e.target.value)} autoComplete="off" />
            </div>
          )}
          {fields.length === 0 && !dialog.confirmText && dialog.confirm && <p className="text-muted-foreground text-sm">{dialog.confirm}</p>}
          {generalError && <InputError message={generalError} />}
        </div>
      )}
    </DynamicPageProvider>
  );

  const footer = !dialog.logView && !dialog.editor && (
    <>
      <Button variant="outline" onClick={() => onOpenChange(false)}>
        Cancel
      </Button>
      <Button
        disabled={form.processing || busyFields.size > 0 || (dialog.confirmText ? confirmInput !== dialog.confirmText : false)}
        onClick={() => (fields.length > 0 ? dispatch() : dispatch())}
      >
        {form.processing && <LoaderCircleIcon className="animate-spin" />}
        Confirm
      </Button>
    </>
  );

  if (dialog.sheet) {
    return (
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent className="sm:max-w-5xl">
          <SheetHeader>
            <SheetTitle>{dialog.title ?? 'Edit'}</SheetTitle>
            <SheetDescription className={dialog.description ? undefined : 'sr-only'}>{dialog.description ?? dialog.title}</SheetDescription>
          </SheetHeader>
          {body}
          {footer && <SheetFooter>{footer}</SheetFooter>}
        </SheetContent>
      </Sheet>
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{dialog.title ?? 'Confirm'}</DialogTitle>
          <DialogDescription className={dialog.description ? undefined : 'sr-only'}>{dialog.description ?? dialog.title}</DialogDescription>
        </DialogHeader>
        {body}
        {footer && <DialogFooter>{footer}</DialogFooter>}
      </DialogContent>
    </Dialog>
  );
}
