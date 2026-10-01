import { createContext, useContext } from 'react';

/**
 * Which node ids are lit by the current hover. This travels by context rather than on the nodes
 * themselves: rewriting node data on every hover handed React Flow a fresh graph, which made it
 * remeasure and briefly paint child nodes at their parent-relative position.
 */
export const TopologyFocusContext = createContext<Set<string>>(new Set());

export function useDimmed(id: string): boolean {
  const lit = useContext(TopologyFocusContext);

  return lit.size > 0 && !lit.has(id);
}
