import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormEvent } from 'react';
import { Form, FormField, FormFields } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export default function AddServerToNetwork({
  open,
  onOpenChange,
  networkId,
  servers,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  networkId: number;
  servers: { id: number; name: string }[];
}) {
  const form = useForm<{
    server_id: string;
  }>({
    server_id: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(route('networks.servers.attach', { network: networkId }), {
      onSuccess: () => onOpenChange(false),
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg" onCloseAutoFocus={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle>Add server</DialogTitle>
          <DialogDescription className="sr-only">Add a server to the private network</DialogDescription>
        </DialogHeader>
        <Form id="add-server-to-network-form" onSubmit={submit} className="p-4">
          <FormFields>
            <FormField>
              <Label htmlFor="server_id">Server</Label>
              {servers.length === 0 ? (
                <p className="text-muted-foreground text-sm">All project servers are already members of this network.</p>
              ) : (
                <Select onValueChange={(value) => form.setData('server_id', value)} value={form.data.server_id}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select a server" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectGroup>
                      {servers.map((server) => (
                        <SelectItem key={`server-${server.id}`} value={server.id.toString()}>
                          {server.name}
                        </SelectItem>
                      ))}
                    </SelectGroup>
                  </SelectContent>
                </Select>
              )}
              <InputError message={form.errors.server_id} />
            </FormField>
          </FormFields>
        </Form>
        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Close</Button>
          </DialogClose>
          <Button form="add-server-to-network-form" type="submit" disabled={form.processing || servers.length === 0}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Add
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
