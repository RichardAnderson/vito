<?php

namespace App\Jobs\Environment;

use App\DTOs\SocketEventDTO;
use App\Events\SocketEvent;
use App\Models\Application;
use App\Models\Environment;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The terminal link of the teardown chain. It touches no server, so it is keyed on the parent
 * row rather than a server id — the arch rules require a `$this->run()` call, not a server key.
 */
class DeleteParentRowJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected string $parentType, protected int $parentId) {}

    public function handle(): void
    {
        $this->run("{$this->parentType}-{$this->parentId}", function (): void {
            $parent = $this->parentType === 'application'
                ? Application::query()->find($this->parentId)
                : Environment::query()->find($this->parentId);

            if ($parent === null) {
                return;
            }

            $projectId = $parent instanceof Application
                ? $parent->project_id
                : $parent->application->project_id;

            $parent->delete();

            SocketEvent::dispatch(new SocketEventDTO(
                projectId: $projectId,
                type: "{$this->parentType}.deleted",
                data: ['id' => $this->parentId],
            ));
        });
    }

    public function failed(Throwable $e): void
    {
        // The parent row survives on failure, which is the correct outcome: re-invoking the
        // delete action re-chains only the components that remain.
    }
}
