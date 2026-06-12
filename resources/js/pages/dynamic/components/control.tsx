import type { ControlNode } from '@/types/dynamic-page';
import type { SchemaComponentProps } from '../component-registry';
import { getPanelControl } from '../controls/registry';

export default function ControlComponent({ node }: SchemaComponentProps<ControlNode>) {
  if (!node.using) {
    return null;
  }

  const PanelControl = getPanelControl(node.using);
  if (!PanelControl) {
    if (import.meta.env.DEV) {
      console.warn(`No panel control registered for "${node.using}" (id: ${node.id}).`);
    }
    return null;
  }

  return <PanelControl node={node} />;
}
