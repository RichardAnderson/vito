import { usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { CellComponentProps } from '@forjedio/inertia-table-react';
import type { Site } from '@/types/site';

type RowSsl = { id: number; type: string; domains: string[]; expires_at: string } | null;

/**
 * Renders a hosted domain's SSL certificate state: webserver-managed badge, site
 * certificate, or the custom certificate's type/id/domain badges.
 */
export default function CertificateCell({ row }: CellComponentProps) {
  const ssl = (row.ssl as RowSsl) ?? null;
  const sslMethod = row.ssl_method as string | undefined;
  const { site } = usePage<{ site: Site }>().props;
  const createsSiteSSLs = site.webserver_creates_site_ssls;
  const webserverName = site.webserver.charAt(0).toUpperCase() + site.webserver.slice(1);

  if (sslMethod === 'letsencrypt' && !createsSiteSSLs) {
    return <Badge variant="outline">{webserverName} Managed SSL</Badge>;
  }

  if (sslMethod === 'letsencrypt' && createsSiteSSLs && ssl?.id) {
    return (
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger asChild>
            <Badge variant="outline" className="cursor-default">
              Site Certificate
            </Badge>
          </TooltipTrigger>
          <TooltipContent>ID: {ssl.id}</TooltipContent>
        </Tooltip>
      </TooltipProvider>
    );
  }

  if (!ssl) {
    return <span>-</span>;
  }

  const sslDomains = ssl.domains ?? [];

  return (
    <div className="flex flex-wrap gap-1">
      <Badge variant="info">{(ssl.type ?? '').toUpperCase()}</Badge>
      <Badge variant="info">#{ssl.id}</Badge>
      <TooltipProvider>
        {sslDomains.map((domain) => {
          const truncated = domain.length > 20;
          const label = truncated ? domain.slice(0, 20) + '...' : domain;
          return truncated ? (
            <Tooltip key={domain}>
              <TooltipTrigger asChild>
                <Badge variant="outline" className="cursor-default">
                  {label}
                </Badge>
              </TooltipTrigger>
              <TooltipContent>{domain}</TooltipContent>
            </Tooltip>
          ) : (
            <Badge key={domain} variant="outline">
              {label}
            </Badge>
          );
        })}
      </TooltipProvider>
    </div>
  );
}
