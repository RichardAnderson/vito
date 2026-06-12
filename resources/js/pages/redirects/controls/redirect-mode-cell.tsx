import type { CellComponentProps } from '@forjedio/inertia-table-react';

/**
 * Renders a redirect's mode: 1000 is Vito's "proxy" sentinel, shown as "Proxy".
 */
export default function RedirectModeCell({ value }: CellComponentProps) {
  return <>{String(value) === '1000' ? 'Proxy' : String(value ?? '')}</>;
}
