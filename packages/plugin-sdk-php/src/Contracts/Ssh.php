<?php

namespace Vito\Plugin\Contracts;

use Illuminate\Contracts\View\View;

/**
 * Capability facade key for running SSH commands on a server.
 *
 * Bound by core to the host SSH helper. Capability-gating (manifest "ssh") and
 * per-plugin audit are layered on at this chokepoint in a later slice.
 */
interface Ssh
{
    public function init(Server $server, ?string $asUser = null): self;

    public function exec(string|View $command, string $log = '', ?int $siteId = null): string;

    public function upload(string $local, string $remote, ?string $owner = null): void;

    public function download(string $local, string $remote): void;

    public function write(string $remotePath, string|View $content, ?string $owner = null): void;
}
