import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { LoaderCircleIcon } from 'lucide-react';
import { Application } from '@/types/application';

export default function DeleteApplication({
  open,
  onOpenChange,
  application,
  environmentCount,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  application: Application;
  environmentCount: number;
}) {
  const form = useForm({});

  const submit = () => {
    form.delete(route('applications.destroy', application.id), {
      onSuccess: () => onOpenChange(false),
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent onCloseAutoFocus={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle>Delete {application.name}</DialogTitle>
          <DialogDescription className="sr-only">Delete this application and everything inside it</DialogDescription>
        </DialogHeader>
        <div className="space-y-2 p-4">
          <p>
            Are you sure you want to delete <strong>{application.name}</strong>?
          </p>
          <p className="text-muted-foreground text-sm">
            {environmentCount === 1 ? 'Its environment' : `All ${environmentCount} environments`} and every component inside them will be removed from
            their servers, along with their routes and certificates. Attached resources such as databases are detached, not deleted. This action cannot
            be undone.
          </p>
        </div>
        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Cancel</Button>
          </DialogClose>
          <Button variant="destructive" disabled={form.processing} onClick={submit}>
            {form.processing && <LoaderCircleIcon className="animate-spin" />}
            Delete application
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
