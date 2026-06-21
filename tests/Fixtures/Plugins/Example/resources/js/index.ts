// Proves a plugin's frontend can consume the generated wire types from the published SDK package.
import type { Server, Site } from '@vito/plugin-sdk';

export function summarize(server: Server, site: Site): string {
  return `${site.domain} on ${server.name} (project ${server.project_id})`;
}
