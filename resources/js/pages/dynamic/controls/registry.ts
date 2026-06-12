import type { ComponentType } from 'react';
import type { Row } from '@forjedio/inertia-table-react';
import type { DynamicFieldConfig } from '@/types/dynamic-field-config';
import type { ControlNode, DataRef } from '@/types/dynamic-page';

/**
 * Props a registered form-field control receives. `form`/`data`/`setBusy` are only
 * supplied when the control is rendered inside a framework dialog — controls MUST
 * guard them being undefined and degrade gracefully elsewhere.
 */
export interface FieldControlProps {
  value: unknown;
  onChange: (value: unknown) => void;
  error?: string;
  config: DynamicFieldConfig;
  form?: { data: Record<string, unknown>; setData: (name: string, value: unknown) => void };
  data?: Record<string, DataRef>;
  setBusy?: (busy: boolean) => void;
}

export interface PanelControlProps {
  node: ControlNode;
}

export interface RowActionsControlProps {
  row: Row;
}

/**
 * Runtime-mutable registries (mirroring component-registry) so first-party code — and,
 * by design, plugin bundles — can register custom controls. Table CELL controls use
 * the inertia-table library's own `registerCellComponent` instead of a map here.
 */
const fieldControls = new Map<string, ComponentType<FieldControlProps>>();
const panelControls = new Map<string, ComponentType<PanelControlProps>>();
const rowActionsControls = new Map<string, ComponentType<RowActionsControlProps>>();

export function registerFieldControl(name: string, component: ComponentType<FieldControlProps>): void {
  fieldControls.set(name, component);
}

export function getFieldControl(name: string): ComponentType<FieldControlProps> | undefined {
  return fieldControls.get(name);
}

export function registerPanelControl(name: string, component: ComponentType<PanelControlProps>): void {
  panelControls.set(name, component);
}

export function getPanelControl(name: string): ComponentType<PanelControlProps> | undefined {
  return panelControls.get(name);
}

export function registerRowActionsControl(name: string, component: ComponentType<RowActionsControlProps>): void {
  rowActionsControls.set(name, component);
}

export function getRowActionsControl(name: string): ComponentType<RowActionsControlProps> | undefined {
  return rowActionsControls.get(name);
}
