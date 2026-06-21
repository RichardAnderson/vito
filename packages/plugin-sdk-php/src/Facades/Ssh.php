<?php

namespace Vito\Plugin\Facades;

use Illuminate\Support\Facades\Facade;
use Vito\Plugin\Contracts\Ssh as SshContract;

/**
 * @method static SshContract init(\Vito\Plugin\Contracts\Server $server, ?string $asUser = null)
 * @method static string exec(string|\Illuminate\Contracts\View\View $command, string $log = '', ?int $siteId = null)
 * @method static void upload(string $local, string $remote, ?string $owner = null)
 * @method static void download(string $local, string $remote)
 * @method static void write(string $remotePath, string|\Illuminate\Contracts\View\View $content, ?string $owner = null)
 *
 * @see SshContract
 */
final class Ssh extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SshContract::class;
    }
}
