<?php

namespace App\Support;

/**
 * Reads the component-function catalogue. The catalogue is config-driven rather than an enum
 * so plugins can add their own through App\Plugins\RegisterComponentFunction.
 */
final class ComponentFunctions
{
    public const string HTTP = 'http';

    public const string PROCESSES = 'processes';

    public const string SCHEDULER = 'scheduler';

    /**
     * @return array<string, array{label: string, description: string, color: string, unlocks: array<int, string>}>
     */
    public static function all(): array
    {
        return config('component.functions', []);
    }

    /**
     * @return array<int, string>
     */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $id): bool
    {
        return array_key_exists($id, self::all());
    }

    public static function label(string $id): string
    {
        return self::all()[$id]['label'] ?? $id;
    }

    public static function color(string $id): string
    {
        return self::all()[$id]['color'] ?? 'gray';
    }

    /**
     * @return array<int, string>
     */
    public static function unlocks(string $id): array
    {
        return self::all()[$id]['unlocks'] ?? [];
    }

    /**
     * Every surface the given functions make available, deduplicated.
     *
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    public static function unlocksFor(array $ids): array
    {
        $unlocked = [];

        foreach ($ids as $id) {
            foreach (self::unlocks($id) as $surface) {
                $unlocked[$surface] = true;
            }
        }

        return array_keys($unlocked);
    }
}
