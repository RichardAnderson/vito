import { registerCellComponent } from '@forjedio/inertia-table-react';
import { registerFieldControl, registerPanelControl, registerRowActionsControl } from './controls/registry';
import ServerProviderControl from './controls/server-provider';
import RedirectModeCell from '@/pages/redirects/controls/redirect-mode-cell';
import CertificateCell from '@/pages/hosted-domains/controls/certificate-cell';
import SslMatcher from '@/pages/hosted-domains/controls/ssl-matcher';
import SslMenu from '@/pages/hosted-domains/controls/ssl-menu';
import StatsDashboard from '@/pages/sites/controls/stats-dashboard';
import ToolingPanel from '@/pages/site-tooling/controls/tooling-panel';
import WorkersHeader from '@/pages/workers/controls/workers-header';
import WorkerActions from '@/pages/workers/controls/worker-actions';

/**
 * First-party control registrations. Imported for its side effect by the dynamic page
 * entry and the page-slot. Plugin bundles register additional controls into the same
 * runtime-mutable registries.
 *
 * - Field controls: registerFieldControl(name, Component)
 * - Panel controls: registerPanelControl(name, Component)
 * - Table cell controls: the inertia-table library's registerCellComponent(name, Component)
 */
registerFieldControl('server_provider', ServerProviderControl);
registerFieldControl('ssl-matcher', SslMatcher);
registerPanelControl('ssl-menu', SslMenu);
registerPanelControl('stats-dashboard', StatsDashboard);
registerPanelControl('tooling-panel', ToolingPanel);
registerPanelControl('workers-header', WorkersHeader);
registerRowActionsControl('worker-actions', WorkerActions);
registerCellComponent('redirect-mode', RedirectModeCell);
registerCellComponent('certificate-cell', CertificateCell);
