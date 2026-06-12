import { Badge } from '@/components/ui/badge';
import CopyableBadge from '@/components/copyable-badge';
import type { CardRowNode } from '@/types/dynamic-page';
import type { SchemaComponentProps } from '../component-registry';
import ButtonComponent from './button';

function renderValue(node: CardRowNode) {
  const value = node.value == null ? '' : String(node.value);

  switch (node.display) {
    case 'copyable':
      return <CopyableBadge text={value} />;
    case 'button':
      return node.button ? <ButtonComponent node={node.button} size="sm" /> : null;
    case 'badge':
      return <Badge variant={(node.color as never) ?? 'default'}>{value}</Badge>;
    case 'link':
      return node.href ? (
        <a href={node.href} target="_blank" rel="noreferrer" className="text-muted-foreground hover:underline">
          {value}
        </a>
      ) : (
        <span className="text-muted-foreground">{value}</span>
      );
    case 'code':
      return <code className="bg-muted rounded px-1.5 py-0.5 text-sm">{value}</code>;
    default:
      return <span className="text-muted-foreground">{value || '-'}</span>;
  }
}

export default function CardRowComponent({ node }: SchemaComponentProps<CardRowNode>) {
  return (
    <div className="flex items-center justify-between gap-4 p-4">
      <span>{node.label}</span>
      {renderValue(node)}
    </div>
  );
}
