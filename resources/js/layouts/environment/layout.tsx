import { type NavItem } from '@/types';
import { BoxesIcon, DatabaseIcon, GlobeIcon, HomeIcon, InfoIcon, KeyRoundIcon, ListIcon, RocketIcon, Settings2Icon } from 'lucide-react';
import { ReactNode, useEffect } from 'react';
import { Application, Component, Environment } from '@/types/application';
import Layout from '@/layouts/app/layout';
import { usePage } from '@inertiajs/react';
import { useRealtimeRecord } from '@/hooks/use-socket-events';
import componentHelper from '@/lib/component-helper';

export default function EnvironmentLayout({ children }: { children: ReactNode }) {
  const page = usePage<{
    application: Application;
    environment: Environment;
    component?: Component;
    environmentComponents?: Component[];
  }>();
  const application = page.props.application;
  const environment = useRealtimeRecord<Environment>(page.props.environment, 'environment')!;

  const componentsInEnvironment = page.props.environmentComponents ?? [];
  const stored = typeof window === 'undefined' ? null : componentHelper.getStoredComponent();

  // Mirrors the server sidebar's site behaviour: the submenu always has a component selected —
  // the one being viewed, the last one visited in this environment, or the first that exists.
  const component =
    page.props.component ??
    (stored && stored.environment_id === environment.id ? componentsInEnvironment.find((c) => c.id === stored.id) : undefined) ??
    componentsInEnvironment[0] ??
    null;

  const storedIsForeign = stored !== null && stored.environment_id !== environment.id;

  useEffect(() => {
    if (component) {
      componentHelper.storeComponent(component);
    } else if (storedIsForeign) {
      componentHelper.storeComponent(undefined);
    }
  }, [component, storedIsForeign]);

  if (typeof window === 'undefined') {
    return null;
  }

  const params = { application: application.id, environment: environment.id };
  const componentParams = component ? { ...params, component: component.id } : null;

  const sidebarNavItems: NavItem[] = [
    {
      title: 'Overview',
      href: route('environments.show', params),
      onlyActivePath: route('environments.show', params),
      icon: HomeIcon,
    },
    {
      title: 'Components',
      href: route('environments.components', params),
      icon: BoxesIcon,
      children:
        component && componentParams
          ? [
              {
                title: 'All components',
                href: route('environments.components', params),
                onlyActivePath: route('environments.components', params),
                icon: ListIcon,
              },
              {
                title: 'Details',
                href: route('components.show', componentParams),
                onlyActivePath: route('components.show', componentParams),
                icon: InfoIcon,
              },
              // Tooling, Web configuration (http), Workers (processes) and Crons (scheduler)
              // slot in here once their pages exist, gated on the component's functions.
              {
                title: 'Settings',
                href: route('components.settings', componentParams),
                onlyActivePath: route('components.settings', componentParams),
                icon: Settings2Icon,
              },
            ]
          : [],
    },
    {
      title: 'Domains',
      href: route('environments.domains', params),
      icon: GlobeIcon,
    },
    {
      title: 'Variables',
      href: route('environments.variables', params),
      icon: KeyRoundIcon,
    },
    {
      title: 'Deployments',
      href: route('environments.deployments', params),
      icon: RocketIcon,
    },
    {
      title: 'Resources',
      href: route('environments.resources', params),
      icon: DatabaseIcon,
    },
    {
      title: 'Settings',
      href: route('environments.settings', params),
      icon: Settings2Icon,
    },
  ];

  return (
    <Layout secondNavItems={sidebarNavItems} secondNavTitle={`${application.name} · ${environment.name}`}>
      <div>{children}</div>
    </Layout>
  );
}
