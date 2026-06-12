import type { ComponentType } from 'react';
import type { SchemaNode } from '@/types/dynamic-page';

export type SchemaComponentProps<T extends SchemaNode = SchemaNode> = {
  node: T;
};

/**
 * The page component registry maps a node `type` discriminator to its React
 * implementation. Deliberately a mutable Map with a write API (not a compile-time
 * `as const`) so v2 plugin bundles can register components at runtime; unknown
 * types resolve to `undefined` and render nothing (with a warning).
 */
const registry = new Map<string, ComponentType<SchemaComponentProps>>();

export function registerComponent<T extends SchemaNode>(type: string, component: ComponentType<SchemaComponentProps<T>>): void {
  registry.set(type, component as ComponentType<SchemaComponentProps>);
}

export function getComponent(type: string): ComponentType<SchemaComponentProps> | undefined {
  return registry.get(type);
}
