<?php

namespace App\Actions\Component;

use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\ServerStatus;
use App\Jobs\Component\MoveComponentJob;
use App\Models\Component;
use App\Models\NetworkServer;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MoveComponent
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function move(Component $component, array $input): void
    {
        $this->validate($component, $input);

        $originServerId = $component->server_id;

        $component->status = ComponentStatus::CREATING;
        $component->save();

        DB::afterCommit(fn () => dispatch(
            new MoveComponentJob($component, (int) $input['server_id'], $originServerId)
        )->onQueue('ssh'));
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function validate(Component $component, array $input): void
    {
        $projectId = $component->environment->application->project_id;

        Validator::make($input, [
            'server_id' => [
                'required',
                Rule::exists('servers', 'id')->where('project_id', $projectId),
            ],
        ])->validate();

        /** @var Server $target */
        $target = Server::query()->findOrFail($input['server_id']);

        if ($target->id === $component->server_id) {
            throw ValidationException::withMessages([
                'server_id' => 'The component is already on that server.',
            ]);
        }

        if ($target->status !== ServerStatus::READY) {
            throw ValidationException::withMessages([
                'server_id' => 'The target server is not ready.',
            ]);
        }

        $this->assertFunctionsAreSupported($component, $target);
        $this->assertPhpVersionIsInstalled($component, $target);
        $this->assertPlacementIsFree($component, $target);
        $this->assertSharesANetwork($component, $target);
    }

    /**
     * @throws ValidationException
     */
    private function assertFunctionsAreSupported(Component $component, Server $target): void
    {
        foreach ($component->functions as $function) {
            if ($function->function === ComponentFunctions::HTTP && $target->webserver() === null) {
                throw ValidationException::withMessages([
                    'server_id' => 'The target server has no webserver installed.',
                ]);
            }

            if ($function->function === ComponentFunctions::PROCESSES && $target->processManager() === null) {
                throw ValidationException::withMessages([
                    'server_id' => 'The target server has no process manager installed.',
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertPhpVersionIsInstalled(Component $component, Server $target): void
    {
        if ($component->php_version === null) {
            return;
        }

        if ($target->php($component->php_version) === null) {
            throw ValidationException::withMessages([
                'server_id' => 'PHP '.$component->php_version.' is not installed on the target server.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertPlacementIsFree(Component $component, Server $target): void
    {
        $taken = $component->environment->components()
            ->where('server_id', $target->id)
            ->whereKeyNot($component->id)
            ->get()
            ->contains(fn (Component $sibling): bool => $sibling->user === $component->user);

        if ($taken) {
            throw ValidationException::withMessages([
                'server_id' => 'This environment already places a component on that server and user.',
            ]);
        }
    }

    /**
     * Components of one environment that sit on different servers must reach each other over a
     * private network, so a move that strands the component is rejected up front.
     *
     * @throws ValidationException
     */
    private function assertSharesANetwork(Component $component, Server $target): void
    {
        $siblingServerIds = $component->environment->components()
            ->whereKeyNot($component->id)
            ->pluck('server_id')
            ->unique()
            ->reject(fn (int $serverId): bool => $serverId === $target->id)
            ->values();

        if ($siblingServerIds->isEmpty()) {
            return;
        }

        $targetNetworkIds = NetworkServer::query()
            ->where('server_id', $target->id)
            ->pluck('network_id');

        $reachable = $siblingServerIds->every(
            fn (int $serverId): bool => NetworkServer::query()
                ->where('server_id', $serverId)
                ->whereIn('network_id', $targetNetworkIds)
                ->exists()
        );

        if (! $reachable) {
            throw ValidationException::withMessages([
                'server_id' => 'The target server shares no private network with the other components in this environment.',
            ]);
        }
    }
}
