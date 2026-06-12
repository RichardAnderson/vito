import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { usePage } from '@inertiajs/react';
import { FormField } from '@/components/ui/form';
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Site } from '@/types/site';
import type { AvailableSsl } from '@/types/hosted-domain';
import type { FieldControlProps } from '@/pages/dynamic/controls/registry';

const SSL_METHOD_OPTIONS: { value: string; label: string }[] = [
  { value: 'none', label: 'Disabled' },
  { value: 'letsencrypt', label: "Generate Let's Encrypt Certificate" },
  { value: 'custom', label: 'Custom Certificate' },
];

/**
 * Fieldset control owning ssl_method + ssl_id. Reads the sibling `domain` field,
 * debounce-fetches matching server certificates from the matching-ssls endpoint,
 * auto-selects the best match, and gates the dialog submit while loading/stale.
 */
export default function SslMatcher({ form, data, setBusy, error }: FieldControlProps) {
  const { site } = usePage<{ site: Site }>().props;
  const allowed = site.webserver_allowed_ssl_methods;
  const methodOptions = allowed ? SSL_METHOD_OPTIONS.filter((o) => allowed.includes(o.value)) : SSL_METHOD_OPTIONS;

  const domain = (form?.data.domain as string) ?? '';
  const sslMethod = (form?.data.ssl_method as string) ?? '';
  const sslId = (form?.data.ssl_id as string) ?? '';

  const [matchingSsls, setMatchingSsls] = useState<AvailableSsl[]>([]);
  const [loadingSsls, setLoadingSsls] = useState(false);
  const originalDomain = useRef<string | null>(null);
  const lastFetched = useRef('');

  const formRef = useRef(form);
  formRef.current = form;
  const matchingUrl = data?.['matching-ssls']?.url;

  // Mount: capture the original domain and seed the default method on create.
  useEffect(() => {
    originalDomain.current = domain;
    lastFetched.current = domain;
    if (!sslMethod) {
      const fallback = !site.ssl_enabled && methodOptions.some((o) => o.value === 'none') ? 'none' : site.webserver_default_ssl_method;
      formRef.current?.setData('ssl_method', fallback);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const stale = domain !== lastFetched.current;

  useEffect(() => {
    setBusy?.(loadingSsls || stale);
  }, [loadingSsls, stale, setBusy]);

  useEffect(() => {
    if (!domain || !matchingUrl) {
      setMatchingSsls([]);
      lastFetched.current = domain;
      return;
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => {
      setLoadingSsls(true);
      axios
        .get(`${matchingUrl}?domain=${encodeURIComponent(domain)}`, { signal: controller.signal })
        .then((response) => {
          const { certificates, best_match_id } = response.data;
          setMatchingSsls(certificates);
          lastFetched.current = domain;
          if (originalDomain.current && domain === originalDomain.current) {
            return;
          }
          if (best_match_id) {
            formRef.current?.setData('ssl_method', 'custom');
            formRef.current?.setData('ssl_id', String(best_match_id));
          } else {
            formRef.current?.setData('ssl_method', 'letsencrypt');
            formRef.current?.setData('ssl_id', '');
          }
        })
        .catch((err) => {
          if (!axios.isCancel(err)) {
            setMatchingSsls([]);
            lastFetched.current = domain;
          }
        })
        .finally(() => setLoadingSsls(false));
    }, 500);

    return () => {
      clearTimeout(timeout);
      controller.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [domain, matchingUrl]);

  const handleMethodChange = (value: string) => {
    formRef.current?.setData('ssl_method', value);
    formRef.current?.setData('ssl_id', value !== 'custom' ? '' : sslId);
  };

  return (
    <>
      <FormField>
        <Label htmlFor="ssl-method">SSL</Label>
        <Select onValueChange={handleMethodChange} value={sslMethod}>
          <SelectTrigger id="ssl-method">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {methodOptions.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <InputError message={error} />
      </FormField>
      {sslMethod === 'custom' && (
        <FormField>
          <Label htmlFor="ssl_id">SSL Certificate</Label>
          <Select onValueChange={(value) => formRef.current?.setData('ssl_id', value)} value={sslId}>
            <SelectTrigger id="ssl_id">
              <SelectValue placeholder={loadingSsls ? 'Loading...' : 'Select a certificate'} />
            </SelectTrigger>
            <SelectContent>
              {matchingSsls.map((ssl) => (
                <SelectItem key={ssl.id} value={String(ssl.id)}>
                  {ssl.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className="text-muted-foreground text-sm">
            Only server-level SSL certificates that match the domain you entered will appear here. Add certificates via the server SSL settings.
          </p>
        </FormField>
      )}
    </>
  );
}
