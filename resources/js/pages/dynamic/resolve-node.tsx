import type { SchemaNode } from '@/types/dynamic-page';
import { getComponent } from './component-registry';

/**
 * Resolves a single schema node to its registered component. Keyed by the node's
 * stable schema `id` (never index) wherever a list is rendered — under extension
 * insertion/removal, index keys reattach component state to the wrong node.
 */
export function ResolveNode({ node }: { node: SchemaNode }) {
  const Component = getComponent(node.type);

  if (!Component) {
    if (import.meta.env.DEV) {
      console.warn(`No renderer registered for schema node type "${node.type}" (id: ${node.id}).`);
    }
    return null;
  }

  return <Component node={node} />;
}

export function ResolveNodes({ nodes }: { nodes: SchemaNode[] }) {
  return (
    <>
      {nodes.map((node) => (
        <ResolveNode key={node.id} node={node} />
      ))}
    </>
  );
}
