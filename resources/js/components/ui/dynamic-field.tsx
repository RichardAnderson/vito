import { InputHTMLAttributes, useEffect, useState } from 'react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { PasswordInput } from '@/components/ui/password-input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DynamicFieldConfig } from '@/types/dynamic-field-config';
import InputError from '@/components/ui/input-error';
import { FormField } from '@/components/ui/form';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { PlusIcon, TriangleAlertIcon, Trash2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { getFieldControl } from '@/pages/dynamic/controls/registry';
import type { DataRef } from '@/types/dynamic-page';

interface DynamicFieldProps {
  value: string | number | boolean | string[] | undefined;
  onChange: (value: string | number | boolean | string[]) => void;
  config: DynamicFieldConfig;
  error?: string;
  form?: { data: Record<string, unknown>; setData: (name: string, value: unknown) => void };
  data?: Record<string, DataRef>;
  setBusy?: (busy: boolean) => void;
}

export default function DynamicField({ value, onChange, config, error, form, data, setBusy }: DynamicFieldProps) {
  const defaultLabel = config.name.replaceAll('_', ' ');
  const label = config?.label || defaultLabel;
  const [initialValue, setInitialValue] = useState(false);

  // Respect the value type: only fall back to the default when genuinely unset,
  // so 0 / false / '' are preserved rather than coerced away.
  if (value === undefined || value === null) {
    value = config?.type === 'repeater' ? [] : (config?.default ?? '');
  }

  useEffect(() => {
    if (!initialValue) {
      if (config.type === 'checkbox') {
        onChange((value as boolean) || false);
      } else {
        onChange(value);
      }
      setInitialValue(true);
    }
  }, [initialValue, setInitialValue, onChange, value, config]);

  // Hidden fields carry their value in the form payload but render nothing.
  if (config?.type === 'hidden') {
    return null;
  }

  // Handle alert
  if (config?.type === 'alert') {
    return (
      <FormField>
        <Alert>
          {!Array.isArray(config.options) && config.options?.type === 'warning' && <TriangleAlertIcon className="text-warning!" />}
          {config.label && <AlertTitle>{config.label}</AlertTitle>}
          <AlertDescription>
            {config.description}
            {config.link && (
              <a href={config.link.url} target="_blank" className="text-primary underline">
                {config.link.label}
              </a>
            )}
          </AlertDescription>
        </Alert>
      </FormField>
    );
  }

  // Handle repeater (array-of-subfields rows editor, e.g. Basic Auth users)
  if (config?.type === 'repeater') {
    const rows = (Array.isArray(value) ? value : []) as unknown as Array<Record<string, unknown>>;
    const subFields = config.fields ?? [];
    const update = (next: Array<Record<string, unknown>>) => onChange(next as unknown as string[]);

    return (
      <FormField>
        <Label className="capitalize">{label}</Label>
        <div className="flex flex-col gap-3">
          {rows.map((row, index) => (
            <div key={index} className="flex items-start gap-2 rounded-md border p-3">
              <div className="grid flex-1 gap-3">
                {subFields.map((subField) => (
                  <DynamicField
                    key={subField.name}
                    config={subField}
                    value={row[subField.name] as string | number | boolean | string[] | undefined}
                    onChange={(v) => {
                      const next = rows.map((r, i) => (i === index ? { ...r, [subField.name]: v } : r));
                      update(next);
                    }}
                  />
                ))}
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="text-muted-foreground"
                onClick={() => update(rows.filter((_, i) => i !== index))}
              >
                <Trash2Icon />
              </Button>
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" className="w-fit" onClick={() => update([...rows, {}])}>
            <PlusIcon />
            Add
          </Button>
        </div>
        <InputError message={error} />
      </FormField>
    );
  }

  // Handle checkbox
  if (config?.type === 'checkbox') {
    return (
      <FormField>
        <div className="flex items-center space-x-2">
          <Switch id={`switch-${config.name}`} checked={value as boolean} onCheckedChange={onChange} />
          <Label htmlFor={`switch-${config.name}`}>{label}</Label>
          {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
          <InputError message={error} />
        </div>
      </FormField>
    );
  }

  // Handle select
  if (config?.type === 'select' && config.options) {
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <Select value={value as string} onValueChange={onChange}>
          <SelectTrigger id={`field-${config.name}`}>
            <SelectValue placeholder={config.placeholder || `Select ${label}`} />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              {(Array.isArray(config.options)
                ? config.options.map((item): [string, string] => [item, config.optionLabels?.[item] ?? item])
                : Object.entries(config.options)
              ).map(([optionValue, optionLabel]) => (
                <SelectItem key={`${config.name}-${optionValue}`} value={optionValue}>
                  {optionLabel}
                </SelectItem>
              ))}
            </SelectGroup>
          </SelectContent>
        </Select>
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  // Handle textarea
  if (config?.type === 'textarea') {
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <Textarea
          name={config.name}
          id={`field-${config.name}`}
          value={(value as string) ?? ''}
          placeholder={config.placeholder}
          onChange={(e) => onChange(e.target.value)}
          className={config.className}
        />
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  // Handle password
  if (config?.type === 'password') {
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <Input
          type="password"
          name={config.name}
          id={`field-${config.name}`}
          value={(value as string) ?? ''}
          placeholder={config.placeholder}
          onChange={(e) => onChange(e.target.value)}
          autoComplete="off"
          spellCheck={false}
        />
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  // Handle password with visibility toggle
  if (config?.type === 'password-with-toggle') {
    return (
      <FormField>
        <Label htmlFor={`field-${config.name}`} className="capitalize">
          {label}
        </Label>
        <PasswordInput
          name={config.name}
          id={`field-${config.name}`}
          value={(value as string) ?? ''}
          placeholder={config.placeholder}
          onChange={(e) => onChange(e.target.value)}
          autoComplete="off"
          spellCheck={false}
        />
        {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
        <InputError message={error} />
      </FormField>
    );
  }

  // Handle custom controls (registered React components keyed by component name / field name).
  if (config?.type === 'component') {
    const Control = getFieldControl(config.component ?? config.name);
    if (Control) {
      return (
        <Control
          value={value}
          onChange={(v) => onChange(v as string | number | boolean | string[])}
          error={error}
          config={config}
          form={form}
          data={data}
          setBusy={setBusy}
        />
      );
    }
  }

  // Default to text input
  const props: InputHTMLAttributes<HTMLInputElement> = {};
  if (config?.placeholder) {
    props.placeholder = config.placeholder;
  }

  return (
    <FormField>
      <Label htmlFor={`field-${config.name}`} className="capitalize">
        {label}
      </Label>
      <Input
        type="text"
        name={config.name}
        id={`field-${config.name}`}
        value={(value as string) ?? ''}
        onChange={(e) => onChange(e.target.value)}
        {...props}
      />
      {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
      <InputError message={error} />
    </FormField>
  );
}
