import { useEffect } from 'react';
import type { ComponentType } from 'react';
import { router } from '@inertiajs/react';
import { useDialogStore } from '@/stores/dialog-store';
import { dialogs, type DialogControlProps } from './registry';

export default function DialogHost() {
  const stack = useDialogStore((s) => s.stack);

  useEffect(() => {
    return router.on('navigate', () => useDialogStore.getState().closeAll());
  }, []);

  if (stack.length === 0) {
    return null;
  }

  return (
    <>
      {stack.map((entry) => {
        const Component = dialogs[entry.key] as ComponentType<typeof entry.props & DialogControlProps> | undefined;

        if (!Component) {
          return null;
        }

        return (
          <Component
            key={entry.id}
            open
            onOpenChange={(o: boolean) => !o && useDialogStore.getState().closeById(entry.id)}
            {...entry.props}
          />
        );
      })}
    </>
  );
}
