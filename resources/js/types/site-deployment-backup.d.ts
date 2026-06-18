export interface SiteDeploymentBackup {
  id: number;
  site_id: number;
  enabled: boolean;
  folders: string[];
  databases: number[];
  storage_id: number | null;
  keep: number;
}
