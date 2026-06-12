declare namespace App {
namespace Enums {
export type BackupFileStatus = 'created' | 'creating' | 'failed' | 'deleting' | 'restoring' | 'restored' | 'restore_failed';
export type BackupStatus = 'running' | 'failed' | 'deleting' | 'stopped';
export type BackupType = 'database' | 'file';
export type CommandExecutionStatus = 'executing' | 'completed' | 'failed';
export type CronjobStatus = 'creating' | 'ready' | 'deleting' | 'enabling' | 'disabling' | 'updating' | 'disabled';
export type DatabaseStatus = 'ready' | 'creating' | 'failed' | 'deleting';
export type DatabaseUserPermission = 'read' | 'write' | 'admin';
export type DatabaseUserStatus = 'ready' | 'creating' | 'failed' | 'deleting';
export type DeploymentStatus = 'deploying' | 'finished' | 'failed';
export type FirewallRuleStatus = 'creating' | 'updating' | 'ready' | 'deleting' | 'failed';
export type HostedDomainStatus = 'creating' | 'updating' | 'pending' | 'active' | 'inactive' | 'deleting';
export type HostedDomainType = 'primary' | 'alias' | 'redirect';
export type IpAddressFamily = 'inet' | 'inet6';
export type IpAddressStatus = 'configuring' | 'configured' | 'deleting' | 'failed';
export type IpAddressType = 'public' | 'private' | 'unknown';
export type LoadBalancerMethod = 'round-robin' | 'least-connections' | 'ip-hash';
export type NodePackageManager = 'npm' | 'pnpm' | 'yarn';
export type OperatingSystem = 'ubuntu_18' | 'ubuntu_20' | 'ubuntu_22' | 'ubuntu_24';
export type PHPIniType = 'cli' | 'fpm';
export type RedirectStatus = 'creating' | 'ready' | 'deleting' | 'failed';
export type ScriptExecutionStatus = 'executing' | 'completed' | 'failed';
export type SecurityControlStatus = 'disabled' | 'updating' | 'ready' | 'failed';
export type ServerStatus = 'ready' | 'installing' | 'installation_failed' | 'disconnected' | 'updating';
export type ServiceStatus = 'ready' | 'installing' | 'installation_failed' | 'uninstalling' | 'failed' | 'starting' | 'stopping' | 'restarting' | 'reloading' | 'stopped' | 'enabling' | 'disabling' | 'disabled';
export type SiteStatus = 'ready' | 'installing' | 'installation_failed' | 'deleting';
export type SshKeyStatus = 'adding' | 'added' | 'deleting';
export type SslMethod = 'none' | 'letsencrypt' | 'custom';
export type SslStatus = 'created' | 'creating' | 'deleting' | 'failed';
export type SslType = 'letsencrypt' | 'custom' | 'csr';
export type UserRole = 'user' | 'admin' | 'owner';
export type WorkerStatus = 'running' | 'creating' | 'deleting' | 'failed' | 'starting' | 'stopping' | 'restarting' | 'stopped';
export type WorkflowRunStatus = 'running' | 'completed' | 'failed';
}
}
