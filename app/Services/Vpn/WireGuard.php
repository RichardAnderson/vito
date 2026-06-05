<?php

namespace App\Services\Vpn;

use App\DTOs\ServiceLog;
use App\Enums\MemberStatus;
use App\Exceptions\SSHError;
use App\Services\AbstractService;
use App\Services\HasLogs;

class WireGuard extends AbstractService implements HasLogs
{
    public static function id(): string
    {
        return 'wireguard';
    }

    public static function type(): string
    {
        return 'vpn';
    }

    public function unit(): string
    {
        return '';
    }

    /**
     * @throws SSHError
     */
    public function install(): void
    {
        $this->service->server->ssh()
            ->setLog($this->service->log)
            ->exec(
                view('ssh.services.wireguard.install'),
                'install-wireguard'
            );
        event('service.installed', $this->service);
        $this->service->server->os()->cleanup();
    }

    /**
     * @throws SSHError
     */
    public function uninstall(): void
    {
        $this->service->server->ssh()->exec(
            view('ssh.services.wireguard.uninstall'),
            'uninstall-wireguard'
        );
        event('service.uninstalled', $this->service);
        $this->service->server->os()->cleanup();
    }

    /**
     * @throws SSHError
     */
    public function version(): string
    {
        return $this->service->server->ssh()->exec(
            'wg --version | awk \'{print $2}\' | sed \'s/^v//\'',
            'get-wireguard-version'
        );
    }

    /**
     * @return array<int, ServiceLog>
     */
    public function logs(): array
    {
        return $this->service->server->privateNetworkMembers()
            ->where('status', MemberStatus::ACTIVE)
            ->get()
            ->map(fn ($member): ServiceLog => new ServiceLog(
                key: "wireguard:{$member->interface}",
                serviceLabel: 'WireGuard',
                label: "Tunnel {$member->interface}",
                source: ServiceLog::SOURCE_JOURNAL,
                target: "wg-quick@{$member->interface}.service",
            ))
            ->all();
    }
}
