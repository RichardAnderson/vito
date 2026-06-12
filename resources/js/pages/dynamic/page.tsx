import { Head } from '@inertiajs/react';
import Container from '@/components/container';
import type { DynamicPageProps } from '@/types/dynamic-page';
import { ResolveNodes } from './resolve-node';
import { DynamicPageProvider } from './page-context';
import { getAreaLayout } from './area-layouts';
import './register-components';
import './register-controls';

/**
 * Single generic renderer for every framework page. Resolves the schema tree
 * through the component registry, inside the area's own layout so the page gets the
 * same chrome (sub-nav, banners) as a hand-written page. The root Page node renders
 * the heading; everything else is schema.
 */
export default function DynamicPage({ area, page, schema, actions, data }: DynamicPageProps) {
  const AreaLayout = getAreaLayout(area);

  return (
    <AreaLayout>
      <Head title={page.title ?? 'Vito'} />
      <Container className="max-w-5xl">
        <DynamicPageProvider value={{ actions, data }}>
          <ResolveNodes nodes={schema} />
        </DynamicPageProvider>
      </Container>
    </AreaLayout>
  );
}
