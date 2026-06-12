import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { AlertNode } from '@/types/dynamic-page';
import type { SchemaComponentProps } from '../component-registry';
import ButtonComponent from './button';

export default function AlertComponent({ node }: SchemaComponentProps<AlertNode>) {
  return (
    <Alert>
      {node.title && <AlertTitle>{node.title}</AlertTitle>}
      {node.message && <AlertDescription>{node.message}</AlertDescription>}
      {node.button && (
        <div className="mt-2">
          <ButtonComponent node={node.button} />
        </div>
      )}
    </Alert>
  );
}
