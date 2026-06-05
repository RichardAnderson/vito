export interface PrivateNetwork {
  id: number;
  project_id: number;
  name: string;
  subnet: string;
  mtu: number;
  servers_count?: number;
  status: string;
  status_color: 'gray' | 'success' | 'info' | 'warning' | 'danger';
  created_at: string;
  updated_at: string;
}

export interface PrivateNetworkMember {
  id: number;
  private_network_id: number;
  private_network_name?: string;
  private_network_subnet?: string;
  server_id: number;
  server_name?: string;
  server_ip?: string;
  overlay_ip: string;
  interface: string;
  public_key: string | null;
  status: string;
  status_color: 'gray' | 'success' | 'info' | 'warning' | 'danger';
  created_at: string;
  updated_at: string;
}
