export interface DynamicFieldConfig {
  type:
    | 'text'
    | 'hidden'
    | 'password'
    | 'password-with-toggle'
    | 'textarea'
    | 'select'
    | 'checkbox'
    | 'component'
    | 'alert'
    | 'tooling'
    | 'tooling-picker'
    | 'tooling-selector'
    | 'repeater';
  name: string;
  options?: string[] | { [key: string]: string };
  optionLabels?: { [key: string]: string };
  component?: string;
  placeholder?: string;
  description?: string;
  label?: string;
  default?: string | number | boolean;
  link?: {
    label: string;
    url: string;
  };
  className?: string;
  componentProps?: Record<string, unknown>;
  fields?: DynamicFieldConfig[];
}
