import type { ComponentType, ReactNode } from 'react';
import AppLayout from '@/layouts/app/layout';
import ServerLayout from '@/layouts/server/layout';

/**
 * Maps an area id to the layout that wraps its framework pages, so a schema page
 * gets the same chrome (server/site sub-navigation, banners) as a hand-written one.
 */
const areaLayouts: Record<string, ComponentType<{ children: ReactNode }>> = {
  server: ServerLayout,
  site: ServerLayout,
};

export function getAreaLayout(area: string): ComponentType<{ children: ReactNode }> {
  return areaLayouts[area] ?? AppLayout;
}
