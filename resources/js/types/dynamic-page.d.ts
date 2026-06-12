import type { DynamicFieldConfig } from '@/types/dynamic-field-config';

export interface SchemaNodeBase {
  id: string;
  type: string;
  children?: SchemaNode[];
}

export interface PageNode extends SchemaNodeBase {
  type: 'page';
  title: string | null;
  description: string | null;
  actions?: SchemaNode[];
  children?: SchemaNode[];
}

export interface CardNode extends SchemaNodeBase {
  type: 'card';
  title: string | null;
  description: string | null;
  destructive: boolean;
  children?: SchemaNode[];
}

export interface CardRowNode extends SchemaNodeBase {
  type: 'card-row';
  display: 'text' | 'copyable' | 'badge' | 'link' | 'code' | 'button';
  label: string | null;
  value: unknown;
  color: string | null;
  href: string | null;
  button: ButtonNode | null;
}

export interface ButtonNode extends SchemaNodeBase {
  type: 'button';
  label: string | null;
  variant: string;
  icon: string | null;
  action: string | null;
  dialog: DialogNode | null;
}

export interface DialogNode extends SchemaNodeBase {
  type: 'dialog';
  title: string | null;
  description: string | null;
  sheet: boolean;
  form: DynamicFieldConfig[] | null;
  action: string | null;
  confirm: string | null;
  confirmText: string | null;
  confirmField: string;
  logView: LogViewNode | null;
  editor: CodeEditorNode | null;
}

export interface AlertNode extends SchemaNodeBase {
  type: 'alert';
  variant: string;
  title: string | null;
  message: string | null;
  button: ButtonNode | null;
}

export interface LogViewNode extends SchemaNodeBase {
  type: 'log-view';
  endpoint: string | null;
  interval: number;
  params: Record<string, unknown>;
}

export interface CodeEditorNode extends SchemaNodeBase {
  type: 'code-editor';
  load: string | null;
  save: string | null;
  preview: string | null;
  reset: string | null;
  info: string | null;
  language: string;
  readonly: boolean;
}

export interface RowActionNode extends SchemaNodeBase {
  type: 'row-action';
  label: string;
  icon: string | null;
  action: string | null;
  params: Record<string, string>;
  dialog: DialogNode | null;
  confirm: string | null;
  destructive: boolean;
  visibleWhen: { column: string; value: unknown } | null;
}

export interface TableNode extends SchemaNodeBase {
  type: 'table';
  tableId: string;
  headerButtons: ButtonNode[];
  rowActions: RowActionNode[];
  realtime: string | null;
  realtimeScope: Record<string, unknown>;
  rowActionsControl?: string | null;
}

export interface ControlNode extends SchemaNodeBase {
  type: 'control';
  using: string | null;
  props: Record<string, unknown>;
}

export type SchemaNode =
  | PageNode
  | CardNode
  | CardRowNode
  | ButtonNode
  | DialogNode
  | AlertNode
  | LogViewNode
  | CodeEditorNode
  | TableNode
  | ControlNode
  | SchemaNodeBase;

export interface ActionRef {
  method: string;
  url: string;
  confirm: string | null;
  confirmText: string | null;
  form: DynamicFieldConfig[] | null;
}

export interface DataRef {
  method: string;
  url: string;
}

export interface DynamicPageProps {
  area: string;
  layout: string;
  page: {
    id: string;
    title: string | null;
  };
  schema: SchemaNode[];
  actions: Record<string, ActionRef>;
  data: Record<string, DataRef>;
  [key: string]: unknown;
}
