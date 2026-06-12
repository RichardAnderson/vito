import { createContext, useContext } from 'react';
import { router } from '@inertiajs/react';
import type { ActionRef, DataRef } from '@/types/dynamic-page';

export interface DynamicPageContextValue {
  actions: Record<string, ActionRef>;
  data: Record<string, DataRef>;
}

const DynamicPageContext = createContext<DynamicPageContextValue>({ actions: {}, data: {} });

export const DynamicPageProvider = DynamicPageContext.Provider;

export function usePageContext(): DynamicPageContextValue {
  return useContext(DynamicPageContext);
}

/**
 * Replace `:column` placeholders with values from a (table) row context.
 */
export function interpolateParams(params: Record<string, string>, row?: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(params)) {
    out[key] = typeof value === 'string' && value.startsWith(':') && row ? row[value.slice(1)] : value;
  }
  return out;
}

export function dispatchAction(action: ActionRef, data: Record<string, unknown> = {}, onSuccess?: () => void): void {
  const options = { preserveScroll: true, onSuccess };
  const method = action.method.toLowerCase();

  if (method === 'delete') {
    router.delete(action.url, { ...options, data: data as Record<string, FormDataConvertible> });
  } else if (method === 'patch') {
    router.patch(action.url, data as Record<string, FormDataConvertible>, options);
  } else if (method === 'put') {
    router.put(action.url, data as Record<string, FormDataConvertible>, options);
  } else {
    router.post(action.url, data as Record<string, FormDataConvertible>, options);
  }
}

type FormDataConvertible = string | number | boolean | null | undefined | Blob;
