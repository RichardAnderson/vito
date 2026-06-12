import { usePage } from '@inertiajs/react';
import HeaderContainer from '@/components/header-container';
import Heading from '@/components/heading';
import SiteBanners from '@/components/site-banners';
import type { Site } from '@/types/site';
import type { PageNode } from '@/types/dynamic-page';
import type { SchemaComponentProps } from '../component-registry';
import { ResolveNodes } from '../resolve-node';

export default function PageComponent({ node }: SchemaComponentProps<PageNode>) {
  const site = usePage<{ site?: Site }>().props.site;

  return (
    <div className="flex flex-col gap-4">
      {node.title && (
        <HeaderContainer>
          <Heading title={node.title} description={node.description ?? undefined} />
          {node.actions && node.actions.length > 0 && (
            <div className="flex items-center gap-2">
              <ResolveNodes nodes={node.actions} />
            </div>
          )}
        </HeaderContainer>
      )}
      {site && <SiteBanners site={site} />}
      <ResolveNodes nodes={node.children ?? []} />
    </div>
  );
}
