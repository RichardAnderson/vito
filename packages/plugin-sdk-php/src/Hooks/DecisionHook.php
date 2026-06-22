<?php

namespace Vito\Plugin\Hooks;

use ReflectionClass;
use Throwable;
use Vito\Plugin\Facades\Hooks;

/**
 * A boolean decision point with a default (≈ Kubernetes validating admission webhooks). Listeners vote
 * bool|Decision, or null to abstain; we collect ALL of them and fold: any non-default vote wins. Because
 * the result is strictly boolean the fold is order-independent.
 *
 * Failure isolation: a throwing/wrong-typed listener is reported and abstains (fail-open). A
 * security-critical hook may set `$safeValue` to force that constant when a listener errors (fail-closed).
 */
abstract class DecisionHook
{
    protected bool $default = true;

    protected ?bool $safeValue = null;

    public static function register(callable $listener): void
    {
        Hooks::register(static::class, $listener);
    }

    public static function execute(mixed ...$args): bool
    {
        return static::evaluate(...$args)->allowed;
    }

    public static function evaluate(mixed ...$args): DecisionResult
    {
        $defaults = static::declaredDefaults();
        $default = $defaults['default'];
        $safeValue = $defaults['safeValue'];

        $result = $default;
        $reason = null;
        $deniedBy = null;

        foreach (Hooks::listeners(static::class) as $listener) {
            try {
                $vote = ($listener->callback)(...$args);
            } catch (Throwable $exception) {
                Hooks::report($exception, $listener->source);
                if ($safeValue !== null && $safeValue !== $default) {
                    $result = $safeValue;
                    $reason ??= 'a hook listener errored (fail-closed)';
                    $deniedBy = $listener->source;
                }

                continue;
            }

            if ($vote instanceof Decision) {
                $value = $vote->allowed;
                $voteReason = $vote->reason;
            } elseif (is_bool($vote)) {
                $value = $vote;
                $voteReason = null;
            } else {
                continue;
            }

            if ($value !== $default) {
                $result = $value;
                $reason = $voteReason ?? $reason;
                $deniedBy = $listener->source;
            }
        }

        return new DecisionResult($result, $reason, $deniedBy);
    }

    /**
     * @return array{default: bool, safeValue: bool|null}
     */
    private static function declaredDefaults(): array
    {
        static $cache = [];

        if (! isset($cache[static::class])) {
            $properties = (new ReflectionClass(static::class))->getDefaultProperties();
            $cache[static::class] = [
                'default' => (bool) ($properties['default'] ?? true),
                'safeValue' => $properties['safeValue'] ?? null,
            ];
        }

        return $cache[static::class];
    }
}
