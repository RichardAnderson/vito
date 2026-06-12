import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export type DynamicPreviewDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  content: string;
  language?: string;
};

export default function DynamicPreviewDialog({ open, onOpenChange, content }: DynamicPreviewDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Preview</DialogTitle>
          <DialogDescription className="sr-only">Rendered preview</DialogDescription>
        </DialogHeader>
        <pre className="bg-muted max-h-[60vh] overflow-auto rounded-md p-4 font-mono text-xs whitespace-pre-wrap">{content}</pre>
      </DialogContent>
    </Dialog>
  );
}
