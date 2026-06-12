import { usePage } from '@inertiajs/react';
import type { SchemaNode } from '@/types/dynamic-page';
import { ResolveNodes } from '@/pages/dynamic/resolve-node';
import { DynamicPageProvider } from '@/pages/dynamic/page-context';
import '@/pages/dynamic/register-components';
import '@/pages/dynamic/register-controls';

/**
 * A named extension point on a hard-coded page. Plugin contributions for this slot
 * are delivered by the AttachPageExtensions middleware as the `slots` Inertia prop
 * and rendered through the shared schema component registry. Renders nothing when no
 * plugin targets the slot.
 */
export default function PageSlot({ name }: { name: string }) {
  const page = usePage<{ slots?: Record<string, SchemaNode[]> }>();
  const nodes = page.props.slots?.[name] ?? [];

  if (nodes.length === 0) {
    return null;
  }

  return (
    <DynamicPageProvider value={{ actions: {}, data: {} }}>
      <ResolveNodes nodes={nodes} />
    </DynamicPageProvider>
  );
}
