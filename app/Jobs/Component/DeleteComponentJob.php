<?php

namespace App\Jobs\Component;

use App\Actions\Component\ComponentResourceSharing;
use App\Actions\Component\SyncComponentVhost;
use App\Enums\ComponentStatus;
use App\Exceptions\SSHError;
use App\Models\Component;
use App\Models\CronJob;
use App\Models\Service;
use App\Services\PHP\PHP;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reverses InstallComponentJob. FK cascade deletes rows, not vhosts, Supervisor programs,
 * crontab entries, FPM pools, OS users or on-disk checkouts.
 */
class DeleteComponentJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected int $componentId, protected int $serverId) {}

    /**
     * @throws SSHError
     */
    public function handle(): void
    {
        $this->run("server-{$this->serverId}", function (): void {
            $component = Component::query()->find($this->componentId);

            if ($component === null) {
                return;
            }

            $server = $component->server;
            $user = (string) $component->user;

            app(SyncComponentVhost::class)->remove($component);

            foreach ($component->workers as $worker) {
                $worker->delete();
            }

            $hadCronJobs = $component->cronJobs()->exists();
            $component->cronJobs()->delete();

            if ($hadCronJobs) {
                $server->cron()->update($user, CronJob::crontab($server, $user));
            }

            $sharing = app(ComponentResourceSharing::class);

            if ($component->php_version !== null && ! $sharing->fpmPoolIsShared($component)) {
                $service = $server->php($component->php_version);

                if ($service instanceof Service) {
                    /** @var PHP $php */
                    $php = $service->handler();
                    $php->removeFpmPool($user, $component->php_version);
                }
            }

            $isLastConsumer = $component->isolated_user_id !== null && ! $sharing->userIsShared($component);

            if ($component->path !== null && $component->path !== '') {
                $server->ssh($user)->exec('rm -rf '.escapeshellarg($component->path), 'delete-component-path');
            }

            $isolatedUser = $component->isolatedUser;

            if ($isLastConsumer) {
                $server->os()->deleteIsolatedUser($user);
            }

            $component->delete();

            if ($isLastConsumer) {
                $isolatedUser?->delete();
            }
        });
    }

    public function failed(Throwable $e): void
    {
        $component = Component::query()->find($this->componentId);

        $component?->fill([
            'status' => ComponentStatus::FAILED,
            'last_error' => 'Teardown failed. See the server logs for full details.',
        ])->save();
    }
}
