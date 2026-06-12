import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { CardNode } from '@/types/dynamic-page';
import type { SchemaComponentProps } from '../component-registry';
import { ResolveNodes } from '../resolve-node';

export default function CardComponent({ node }: SchemaComponentProps<CardNode>) {
  return (
    <Card className={cn('overflow-hidden', node.destructive && 'border-destructive/50')}>
      {(node.title || node.description) && (
        <CardHeader>
          {node.title && <CardTitle>{node.title}</CardTitle>}
          {node.description && <CardDescription>{node.description}</CardDescription>}
        </CardHeader>
      )}
      <CardContent className="bg-background divide-y p-0">
        <ResolveNodes nodes={node.children ?? []} />
      </CardContent>
    </Card>
  );
}
