export interface Application {
  id: number;
  project_id: number;
  name: string;
  source_control_id?: number;
  source_control?: string;
  repository?: string;
  status: string;
  status_color: string;
  environments_count?: number;
  created_at: string;
  updated_at: string;
}

export interface Environment {
  id: number;
  application_id: number;
  primary_component_id?: number;
  name: string;
  slug: string;
  branch?: string;
  color: string;
  auto_deploy: boolean;
  status: string;
  status_color: string;
  components_count?: number;
  domains_count?: number;
  domains?: EnvironmentDomain[];
  created_at: string;
  updated_at: string;
}

export interface ComponentFunction {
  function: string;
  label: string;
  color: string;
  status: string;
  status_color: string;
}

export interface Component {
  id: number;
  environment_id: number;
  server_id: number;
  server?: string;
  isolated_user_id?: number;
  user?: string;
  name: string;
  type: string;
  type_value: string;
  type_color: string;
  path?: string;
  web_directory?: string;
  php_version?: string;
  needs_redeploy: boolean;
  functions?: ComponentFunction[];
  status: string;
  status_color: string;
  last_error?: string;
  created_at: string;
  updated_at: string;
}

export interface EnvironmentDomain {
  id: number;
  environment_id: number;
  host: string;
  url: string;
  type: string;
  type_color: string;
  redirect_to_domain_id?: number;
  redirect_to?: string;
  ssl_method: string;
  ssl_id?: number;
  status: string;
  status_color: string;
  created_at: string;
  updated_at: string;
}

