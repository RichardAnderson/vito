import { useEffect, useState } from 'react';
import { Editor, useMonaco } from '@monaco-editor/react';
import { useQuery } from '@tanstack/react-query';
import axios from 'axios';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { EyeIcon, LoaderCircleIcon, RotateCcwIcon } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import { registerCaddyLanguage, registerNginxLanguage } from '@/lib/editor';
import type { CodeEditorNode } from '@/types/dynamic-page';
import { dispatchAction, usePageContext } from '../page-context';
import { useDialog } from '@/hooks/use-dialog';

/**
 * Monaco-backed editor that fills the available height of its dialog/sheet, with an
 * optional info banner and a footer (Reset left, Preview/Save right). Bound to a data
 * endpoint (load), an action (save), and optional preview/reset actions. Content is
 * posted under the `content` key.
 */
export function CodeEditor({ node }: { node: CodeEditorNode }) {
  const { actions, data } = usePageContext();
  const dialog = useDialog();
  const { getActualAppearance } = useAppearance();
  const monaco = useMonaco();

  const [content, setContent] = useState('');
  const [previewing, setPreviewing] = useState(false);

  const loadRef = node.load ? data[node.load] : undefined;

  const query = useQuery({
    queryKey: ['dynamic-editor', loadRef?.url],
    queryFn: async () => {
      const response = await axios.get(loadRef!.url);
      return response.data as { content?: string } & Record<string, unknown>;
    },
    enabled: Boolean(loadRef),
    refetchOnWindowFocus: false,
    retry: false,
  });

  useEffect(() => {
    if (query.isSuccess) {
      setContent(typeof query.data.content === 'string' ? query.data.content : '');
    }
  }, [query.isSuccess, query.data]);

  useEffect(() => {
    if (monaco) {
      registerNginxLanguage(monaco);
      registerCaddyLanguage(monaco);
    }
  }, [monaco]);

  const save = () => {
    if (node.save && actions[node.save]) {
      dispatchAction(actions[node.save], { content });
    }
  };

  const reset = () => {
    if (node.reset && actions[node.reset]) {
      const action = actions[node.reset];
      dialog.confirm.open({
        title: 'Reset to default',
        description: 'Discard the custom template and regenerate the default? This cannot be undone.',
        variant: 'destructive',
        confirmLabel: 'Reset',
        method: action.method as 'post' | 'patch' | 'put' | 'delete',
        url: action.url,
      });
    }
  };

  const preview = async () => {
    if (!node.preview || !data[node.preview]) {
      return;
    }
    setPreviewing(true);
    try {
      const response = await axios.post(data[node.preview].url, { content });
      const rendered = typeof response.data.content === 'string' ? response.data.content : JSON.stringify(response.data, null, 2);
      dialog.dynamicPreview.open({ content: rendered, language: node.language });
    } finally {
      setPreviewing(false);
    }
  };

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="min-h-[55vh] flex-1">
        {query.isSuccess || !loadRef ? (
          <Editor
            defaultLanguage={node.language}
            value={content}
            theme={getActualAppearance() === 'dark' ? 'vs-dark' : 'vs'}
            height="100%"
            className="h-full"
            onChange={(value) => setContent(value ?? '')}
            options={{ fontSize: 14, readOnly: node.readonly, minimap: { enabled: false } }}
          />
        ) : (
          <Skeleton className="h-full w-full rounded-none" />
        )}
      </div>

      {node.info && (
        <div className="border-t p-4">
          <Alert>
            <AlertDescription>{node.info}</AlertDescription>
          </Alert>
        </div>
      )}

      {!node.readonly && (
        <div className="flex items-center justify-between gap-2 border-t p-4">
          {node.reset ? (
            <Button variant="outline" onClick={reset} disabled={query.isLoading}>
              <RotateCcwIcon />
              Reset
            </Button>
          ) : (
            <span />
          )}
          <div className="flex items-center gap-2">
            {node.preview && (
              <Button variant="outline" onClick={preview} disabled={previewing || query.isLoading}>
                {previewing ? <LoaderCircleIcon className="animate-spin" /> : <EyeIcon />}
                Preview
              </Button>
            )}
            {node.save && (
              <Button onClick={save} disabled={query.isLoading}>
                Save
              </Button>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
