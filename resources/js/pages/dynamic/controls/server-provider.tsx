import ServerProviderSelect from '@/pages/server-providers/components/server-provider-select';
import { FormField } from '@/components/ui/form';
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import type { FieldControlProps } from './registry';

/**
 * Field control wrapping the existing ServerProviderSelect (maps onChange → onValueChange).
 * Registered as the `server_provider` control for back-compat with the old hardcoded branch.
 */
export default function ServerProviderControl({ value, onChange, error, config }: FieldControlProps) {
  const label = config.label || config.name.replaceAll('_', ' ');

  return (
    <FormField>
      <Label className="capitalize">{label}</Label>
      <ServerProviderSelect value={value as string} onValueChange={(v) => onChange(v)} />
      {config.description && <p className="text-muted-foreground text-xs">{config.description}</p>}
      <InputError message={error} />
    </FormField>
  );
}
