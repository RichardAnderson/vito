import { FormEvent, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Editor } from '@monaco-editor/react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { LoaderCircleIcon } from 'lucide-react';
import InputError from '@/components/ui/input-error';
import { useAppearance } from '@/hooks/use-appearance';
import { useInputFocus } from '@/stores/useInputFocus';

export default function DeployScriptEditor({
  open,
  onOpenChange,
  applicationId,
  environmentId,
  scriptId,
  scriptName,
  content,
  scope,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  applicationId: number;
  environmentId: number;
  scriptId: number;
  scriptName: string;
  content: string;
  scope: string;
}) {
  const { getActualAppearance } = useAppearance();
  const setFocused = useInputFocus((state) => state.setFocused);
  const [value, setValue] = useState(content);

  const form = useForm<{ script: string }>({ script: content });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.transform((data) => ({ ...data, script: value }));
    form.put(
      route('environments.scripts.update', { application: applicationId, environment: environmentId, script: scriptId }),
      { onSuccess: () => onOpenChange(false) },
    );
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-3xl" onCloseAutoFocus={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle>
            {scriptName} script — {scope}
          </DialogTitle>
          <DialogDescription>Runs on every component during the {scriptName} stage of a deployment.</DialogDescription>
        </DialogHeader>
        <form id="deploy-script-form" onSubmit={submit} className="p-4">
          <div className="border-border overflow-hidden rounded-md border">
            <Editor
              height="360px"
              defaultLanguage="shell"
              theme={getActualAppearance() === 'dark' ? 'vs-dark' : 'light'}
              value={value}
              onChange={(next) => setValue(next ?? '')}
              onMount={(editor) => {
                editor.onDidFocusEditorText(() => setFocused(true));
                editor.onDidBlurEditorText(() => setFocused(false));
              }}
              options={{ minimap: { enabled: false }, fontSize: 13, scrollBeyondLastLine: false }}
            />
          </div>
          <InputError message={form.errors.script} className="mt-2" />
        </form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button form="deploy-script-form" type="submit" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
