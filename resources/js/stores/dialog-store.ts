import { create } from 'zustand';
import type { DialogRegistry, ConsumerProps } from '@/components/dialogs/registry';

export type ActiveDialog = {
  [K in keyof DialogRegistry]: { id: number; key: K; props: ConsumerProps<DialogRegistry[K]>; trigger: HTMLElement | null };
}[keyof DialogRegistry];

const MAX_STACK = 5;
let nextId = 0;

type DialogStore = {
  stack: ActiveDialog[];
  open: <K extends keyof DialogRegistry>(key: K, props: ConsumerProps<DialogRegistry[K]>) => number;
  closeById: (id: number) => void;
  closeTopByKey: (key: keyof DialogRegistry) => void;
  closeTop: () => void;
  closeAll: () => void;
};

function restoreFocus(trigger: HTMLElement | null): void {
  requestAnimationFrame(() => {
    if (trigger?.isConnected) {
      trigger.focus();
    }
  });
}

export const useDialogStore = create<DialogStore>((set, get) => ({
  stack: [],
  open: (key, props) => {
    const id = ++nextId;
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    set((s) => {
      const top = s.stack[s.stack.length - 1];
      // Re-opening the same key that is already on top replaces it (preserves the
      // legacy single-active "open replaces" semantics); a different key stacks.
      if (top && top.key === key) {
        return { stack: [...s.stack.slice(0, -1), { id, key, props, trigger: top.trigger } as ActiveDialog] };
      }
      const next = [...s.stack, { id, key, props, trigger } as ActiveDialog];
      if (next.length > MAX_STACK) {
        next.shift();
      }
      return { stack: next };
    });
    return id;
  },
  closeById: (id) => {
    const stack = get().stack;
    const entry = stack.find((e) => e.id === id);
    const wasTop = stack[stack.length - 1]?.id === id;
    set({ stack: stack.filter((e) => e.id !== id) });
    if (entry && wasTop) {
      restoreFocus(entry.trigger);
    }
  },
  closeTopByKey: (key) => {
    const stack = get().stack;
    for (let i = stack.length - 1; i >= 0; i--) {
      if (stack[i].key === key) {
        get().closeById(stack[i].id);
        return;
      }
    }
  },
  closeTop: () => {
    const top = get().stack[get().stack.length - 1];
    if (top) {
      get().closeById(top.id);
    }
  },
  closeAll: () => {
    const top = get().stack[get().stack.length - 1];
    set({ stack: [] });
    if (top) {
      restoreFocus(top.trigger);
    }
  },
}));
