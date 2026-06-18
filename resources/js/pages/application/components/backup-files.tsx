import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Deployment } from '@/types/deployment';
import { DatabaseIcon, FolderIcon } from 'lucide-react';

export default function BackupFiles({
  open,
  onOpenChange,
  files,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  files: Deployment['backup_files'];
}) {
  const folders = files?.folders ?? [];
  const databases = files?.databases ?? [];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent onCloseAutoFocus={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle>Backup files</DialogTitle>
          <DialogDescription>Folders and databases captured before this deployment.</DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-4 p-4">
          <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">Folders</p>
            {folders.length > 0 ? (
              folders.map((folder) => (
                <div key={folder} className="text-muted-foreground flex items-center gap-2 text-sm">
                  <FolderIcon className="size-4" />
                  <span className="font-mono">{folder}</span>
                </div>
              ))
            ) : (
              <p className="text-muted-foreground text-sm">No folders backed up.</p>
            )}
          </div>
          <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">Databases</p>
            {databases.length > 0 ? (
              databases.map((database) => (
                <div key={database} className="text-muted-foreground flex items-center gap-2 text-sm">
                  <DatabaseIcon className="size-4" />
                  <span className="font-mono">{database}</span>
                </div>
              ))
            ) : (
              <p className="text-muted-foreground text-sm">No databases backed up.</p>
            )}
          </div>
        </div>
        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Close</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
