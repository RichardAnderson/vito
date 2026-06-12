import { Button } from '@/components/ui/button';
import { useDialog } from '@/hooks/use-dialog';
import type { ButtonNode } from '@/types/dynamic-page';
import type { SchemaComponentProps } from '../component-registry';
import { dispatchAction, usePageContext } from '../page-context';

type ButtonVariant = 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
type ButtonSize = 'default' | 'sm' | 'lg' | 'icon';

export default function ButtonComponent({ node, size = 'default' }: SchemaComponentProps<ButtonNode> & { size?: ButtonSize }) {
  const { actions, data } = usePageContext();
  const dialog = useDialog();

  const onClick = () => {
    if (node.dialog) {
      dialog.dynamicDialog.open({ dialog: node.dialog, actions, data });
      return;
    }

    if (!node.action) {
      return;
    }

    const action = actions[node.action];
    if (!action) {
      return;
    }

    if (action.confirm) {
      dialog.confirm.open({
        title: action.confirm,
        method: action.method as 'post' | 'patch' | 'put' | 'delete',
        url: action.url,
      });
    } else {
      dispatchAction(action);
    }
  };

  return (
    <Button variant={node.variant as ButtonVariant} size={size} onClick={onClick}>
      {node.label}
    </Button>
  );
}
