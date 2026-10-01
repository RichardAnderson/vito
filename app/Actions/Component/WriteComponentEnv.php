<?php

namespace App\Actions\Component;

use App\Actions\Environment\ResolveComponentVariables;
use App\Exceptions\SSHError;
use App\Helpers\EnvParser;
use App\Models\Component;

/**
 * The payload travels over SFTP inside SSH::write(), so it never reaches a ServerLog — only the
 * subsequent chmod/mv/chown go through exec(). No extra redaction is needed here.
 */
class WriteComponentEnv
{
    /**
     * @throws SSHError
     */
    public function write(Component $component, ?string $releasePath = null): void
    {
        $pairs = app(ResolveComponentVariables::class)->toEnvPairs($component);

        if ($pairs === []) {
            return;
        }

        $path = rtrim($releasePath ?? $component->currentPath(), '/').'/.env';

        $component->server->os()->write($path, EnvParser::stringify($pairs), (string) $component->user);
    }
}
