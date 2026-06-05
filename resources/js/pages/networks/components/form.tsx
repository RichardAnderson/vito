import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormEvent } from 'react';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { PrivateNetwork } from '@/types/private-network';

export default function PrivateNetworkForm({
  open,
  onOpenChange,
  network,
  suggestedSubnet,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  network?: PrivateNetwork;
  suggestedSubnet?: string;
}) {
  const form = useForm<{
    name: string;
    subnet: string;
    mtu: string;
  }>({
    name: network?.name || '',
    subnet: network?.subnet || suggestedSubnet || '10.88.0.0/24',
    mtu: network?.mtu?.toString() || '1420',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (network) {
      form.put(route('networks.update', { network: network.id }), {
        onSuccess: () => onOpenChange(false),
      });
      return;
    }

    form.post(route('networks.store'), {
      onSuccess: () => onOpenChange(false),
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg" onCloseAutoFocus={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle>{network ? 'Edit' : 'Create'} private network</DialogTitle>
          <DialogDescription className="sr-only">{network ? 'Edit' : 'Create new'} private network</DialogDescription>
        </DialogHeader>
        <Form id="private-network-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="name">Name</Label>
              <Input type="text" id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <InputError message={form.errors.name} />
            </FormField>

            <FormField>
              <Label htmlFor="subnet">Subnet</Label>
              <Input
                type="text"
                id="subnet"
                placeholder="10.88.0.0/24"
                value={form.data.subnet}
                disabled={!!network}
                onChange={(e) => form.setData('subnet', e.target.value)}
              />
              <p className="text-muted-foreground text-xs">
                Private IPv4 CIDR for the overlay. Each member server is assigned an address from this range. Cannot be changed later.
              </p>
              <InputError message={form.errors.subnet} />
            </FormField>

            <FormField>
              <Label htmlFor="mtu">MTU</Label>
              <Input type="text" id="mtu" value={form.data.mtu} onChange={(e) => form.setData('mtu', e.target.value)} />
              <p className="text-muted-foreground text-xs">Changing the MTU briefly restarts the tunnel on every member.</p>
              <InputError message={form.errors.mtu} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Close</Button>
          </DialogClose>
          <Button form="private-network-form" type="submit" disabled={form.processing}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            {network ? 'Save' : 'Create'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
