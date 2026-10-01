<?php

namespace App\Jobs\Component;

use App\Actions\Component\ComponentResourceSharing;
use App\Actions\Component\SyncComponentVhost;
use App\Actions\Worker\ProvisionWorker;
use App\DTOs\SocketEventDTO;
use App\Enums\ComponentStatus;
use App\Events\SocketEvent;
use App\Exceptions\SSHCommandError;
use App\Exceptions\SSHError;
use App\Http\Resources\ComponentResource;
use App\Jobs\EnvironmentDomain\SyncEnvironmentDomainJob;
use App\Models\Component;
use App\Models\CronJob;
use App\Models\IsolatedUser;
use App\Models\Server;
use App\Models\Service;
use App\Services\PHP\PHP;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MoveComponentJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(
        protected Component $component,
        protected int $targetServerId,
        protected int $originServerId,
    ) {}

    /**
     * @throws SSHError
     */
    public function handle(): void
    {
        $this->run("server-{$this->targetServerId}", function (): void {
            $component = $this->component;
            /** @var Server $target */
            $target = Server::query()->findOrFail($this->targetServerId);
            /** @var Server $origin */
            $origin = Server::query()->findOrFail($this->originServerId);
            $user = (string) $component->user;

            $this->prepareTarget($component, $target, $user);
            $this->flipOwnership($component, $target, $user);
            $this->tearDownOrigin($component, $origin, $user);

            $component->status = ComponentStatus::READY;
            $component->last_error = null;
            $component->save();

            $this->broadcast($component);
        });

        // Certificates follow the component. SyncEnvironmentDomainJob is keyed on the same server,
        // so it is dispatched only after run() has released the lock.
        if ($this->component->environment->servingComponent()?->id === $this->component->id) {
            foreach ($this->component->environment->domains()->get() as $domain) {
                dispatch(new SyncEnvironmentDomainJob($domain))->onQueue('ssh');
            }
        }
    }

    public function failed(Throwable $e): void
    {
        $this->component->fill([
            'status' => ComponentStatus::FAILED,
            'last_error' => 'Move failed. The component is still served from its original server.',
        ])->save();
    }

    /**
     * @throws SSHError
     */
    private function prepareTarget(Component $component, Server $target, string $user): void
    {
        $isolatedUser = IsolatedUser::query()->firstOrCreate([
            'server_id' => $target->id,
            'username' => $user,
        ]);

        $lock = $isolatedUser->lock();
        $lock->block(30, fn (): bool => true);

        try {
            $target->os()->createIsolatedUser($user, Str::random(15));
        } finally {
            $lock->release();
        }

        if ($component->php_version !== null && ! $this->fpmPoolExists($target, $user, $component->php_version)) {
            $service = $target->php($component->php_version);

            if ($service instanceof Service) {
                /** @var PHP $php */
                $php = $service->handler();
                $php->createFpmPool($user, $component->php_version);
            }
        }

        $basePath = $component->basePath();
        $target->ssh($user)->exec('mkdir -p '.escapeshellarg($basePath.'/releases'), 'create-component-path');
    }

    private function flipOwnership(Component $component, Server $target, string $user): void
    {
        DB::transaction(function () use ($component, $target, $user): void {
            $component->server_id = $target->id;
            $component->isolated_user_id = IsolatedUser::query()
                ->where('server_id', $target->id)
                ->where('username', $user)
                ->value('id');
            $component->save();

            $component->workers()->update(['server_id' => $target->id]);
            $component->cronJobs()->update(['server_id' => $target->id]);
        });

        $component->refresh();

        if ($component->terminatesHttp()) {
            app(SyncComponentVhost::class)->sync($component);
        }

        foreach ($component->workers as $worker) {
            app(ProvisionWorker::class)->provision($worker);
        }

        if ($component->cronJobs()->exists()) {
            $target->cron()->update($user, CronJob::crontab($target, $user));
        }
    }

    /**
     * @throws SSHError
     */
    private function tearDownOrigin(Component $component, Server $origin, string $user): void
    {
        $originComponent = $component->replicate();
        $originComponent->id = $component->id;
        $originComponent->setRelation('server', $origin);

        $origin->ssh()->exec(
            view('ssh.services.webserver.nginx.remove-component-vhost', ['name' => 'component-'.$component->id]),
            'remove-component-vhost'
        );

        $origin->cron()->update($user, CronJob::crontab($origin, $user));

        $sharing = app(ComponentResourceSharing::class);

        if ($component->php_version !== null && ! $sharing->fpmPoolIsShared($component)) {
            $service = $origin->php($component->php_version);

            if ($service instanceof Service) {
                /** @var PHP $php */
                $php = $service->handler();
                $php->removeFpmPool($user, $component->php_version);
            }
        }

        $origin->ssh($user)->exec('rm -rf '.escapeshellarg($component->basePath()), 'delete-component-path');
    }

    /**
     * @throws SSHError
     */
    private function fpmPoolExists(Server $server, string $user, string $version): bool
    {
        try {
            $server->ssh()->exec(view('ssh.site.check-fpm-pool-exists', [
                'user' => $user,
                'version' => $version,
            ]));

            return true;
        } catch (SSHCommandError) {
            return false;
        }
    }

    private function broadcast(Component $component): void
    {
        SocketEvent::dispatch(new SocketEventDTO(
            projectId: $component->server->project_id,
            type: 'component.updated',
            data: new ComponentResource($component->load(['server', 'functions'])),
        ));
    }
}
