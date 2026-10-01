<?php

namespace App\Actions\Environment;

use App\Models\Component;
use App\Models\EnvironmentVariable;

/**
 * Precedence is component override > environment > injected resource credential, and the winning
 * source travels with the value so the UI can show which layer won.
 *
 * Nothing here logs a value.
 */
class ResolveComponentVariables
{
    /**
     * @return array<string, array{value: ?string, source: string, is_secret: bool}>
     */
    public function resolve(Component $component): array
    {
        $resolved = [];

        // Resource-injected credentials are the lowest layer. Automatic injection is V8/S4 and
        // out of scope for v1, so this tier is deliberately empty and has no writer yet.

        foreach ($this->variablesFor($component->environment_id, null) as $variable) {
            $resolved[$variable->key] = [
                'value' => $variable->value,
                'source' => 'environment',
                'is_secret' => $variable->is_secret,
            ];
        }

        foreach ($this->variablesFor($component->environment_id, $component->id) as $variable) {
            $resolved[$variable->key] = [
                'value' => $variable->value,
                'source' => 'component:'.$component->name,
                'is_secret' => $variable->is_secret,
            ];
        }

        return $resolved;
    }

    /**
     * @return array<int, array{key: string, value: string}>
     */
    public function toEnvPairs(Component $component): array
    {
        $pairs = [];

        foreach ($this->resolve($component) as $key => $entry) {
            $pairs[] = ['key' => $key, 'value' => (string) $entry['value']];
        }

        return $pairs;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, EnvironmentVariable>
     */
    private function variablesFor(int $environmentId, ?int $componentId): \Illuminate\Database\Eloquent\Collection
    {
        return EnvironmentVariable::query()
            ->where('environment_id', $environmentId)
            ->when($componentId === null,
                fn ($query) => $query->whereNull('component_id'),
                fn ($query) => $query->where('component_id', $componentId))
            ->get();
    }
}
