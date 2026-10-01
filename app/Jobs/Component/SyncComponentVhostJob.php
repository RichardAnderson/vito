<?php

namespace App\Jobs\Component;

use App\Actions\Component\SyncComponentVhost;
use App\Enums\ComponentStatus;
use App\Models\Component;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * A thin wrapper. Callers already holding `server-{id}` call SyncComponentVhost directly.
 */
class SyncComponentVhostJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected Component $component, protected bool $httpOnly = false) {}

    public function handle(): void
    {
        $this->run("server-{$this->component->server_id}", function (): void {
            app(SyncComponentVhost::class)->sync($this->component, $this->httpOnly);
        });
    }

    public function failed(Throwable $e): void
    {
        $this->component->status = ComponentStatus::FAILED;
        $this->component->last_error = $e->getMessage();
        $this->component->save();
    }
}
