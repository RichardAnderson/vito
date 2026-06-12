import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { EllipsisVerticalIcon, LockIcon, LockOpenIcon, RefreshCwIcon, ShieldCheckIcon, ShieldOffIcon } from 'lucide-react';
import type { Server } from '@/types/server';
import type { Site } from '@/types/site';
import type { PanelControlProps } from '@/pages/dynamic/controls/registry';

/**
 * SSL controls dropdown for the Domains page header: enable/disable SSL, toggle force
 * SSL, and force-renew the site certificate. Posts to the existing named routes.
 */
export default function SslMenu({ node }: PanelControlProps) {
  const { server, site } = usePage<{ server: Server; site: Site }>().props;
  const hasSiteSsl = Boolean(node.props.hasSiteSsl);
  const sslLocked = !site.can_configure_ssl;
  const params = { server: server.id, site: site.id };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline">
          {site.ssl_enabled ? <LockIcon /> : <LockOpenIcon />}
          <EllipsisVerticalIcon />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {site.ssl_enabled ? (
          <DropdownMenuItem disabled={sslLocked} onClick={() => !sslLocked && router.post(route('sites.disable-ssl', params))}>
            <LockOpenIcon />
            Disable SSL
          </DropdownMenuItem>
        ) : (
          <DropdownMenuItem disabled={sslLocked} onClick={() => !sslLocked && router.post(route('sites.enable-ssl', params))}>
            <LockIcon />
            Enable SSL
          </DropdownMenuItem>
        )}
        {site.force_ssl ? (
          <DropdownMenuItem disabled={sslLocked} onClick={() => !sslLocked && router.post(route('site-settings.disable-force-ssl', params))}>
            <ShieldOffIcon />
            Disable Force SSL
          </DropdownMenuItem>
        ) : (
          <DropdownMenuItem disabled={sslLocked} onClick={() => !sslLocked && router.post(route('site-settings.enable-force-ssl', params))}>
            <ShieldCheckIcon />
            Force SSL
          </DropdownMenuItem>
        )}
        {site.webserver_creates_site_ssls && (
          <DropdownMenuItem disabled={!hasSiteSsl} onClick={() => hasSiteSsl && router.post(route('hosted-domains.renew-ssl', params))}>
            <RefreshCwIcon />
            Force Renew SSL
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
