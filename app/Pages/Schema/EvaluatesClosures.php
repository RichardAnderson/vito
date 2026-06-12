<?php

namespace App\Pages\Schema;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use RuntimeException;

/**
 * Filament-style closure evaluation: any setter value may be a closure that is
 * called with reflection-resolved, dependency-injected arguments. Resolution is
 * by parameter NAME first (`site`, `server`, `user`, `input`, `request`, `record`,
 * `models`, or any bound model key), then by TYPE (`Site`, `Server`, `User`,
 * `Request`, or a `Model` matching a bound model / the row record). This one engine
 * serves both new `fn (Site $site, array $input)` and legacy `fn (array $models)`
 * handlers, so headless actions kept verbatim keep working.
 */
trait EvaluatesClosures
{
    /**
     * @param  array<string, mixed>  $named  Per-call name overrides (highest priority).
     */
    protected function evaluate(mixed $value, EvaluationContext $ctx, array $named = []): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        $parameters = (new ReflectionFunction($value))->getParameters();
        if ($parameters === []) {
            return $value();
        }

        $arguments = [];
        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }
            $arguments[] = $this->resolveClosureParameter($parameter, $ctx, $named);
        }

        return $value(...$arguments);
    }

    /**
     * @param  array<string, mixed>  $named
     */
    private function resolveClosureParameter(ReflectionParameter $parameter, EvaluationContext $ctx, array $named): mixed
    {
        $byName = $this->resolveByName($parameter->getName(), $ctx, $named);
        if ($byName !== []) {
            return $byName[0];
        }

        $byType = $this->resolveByType($parameter->getType(), $ctx);
        if ($byType !== []) {
            return $byType[0];
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new RuntimeException("Unable to resolve page closure parameter \${$parameter->getName()}.");
    }

    /**
     * Results are wrapped in an array so a resolved `null` is distinguishable from
     * "not handled" (`[]`).
     *
     * @param  array<string, mixed>  $named
     * @return array<int, mixed>
     */
    private function resolveByName(string $name, EvaluationContext $ctx, array $named): array
    {
        if (array_key_exists($name, $named)) {
            return [$named[$name]];
        }

        return match ($name) {
            'models' => [$ctx->models],
            'input' => [$ctx->input],
            'request' => $ctx->request !== null ? [$ctx->request] : [],
            'user' => $ctx->user !== null ? [$ctx->user] : [],
            'record' => $ctx->record !== null ? [$ctx->record] : [],
            default => array_key_exists($name, $ctx->models) ? [$ctx->models[$name]] : [],
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function resolveByType(?ReflectionType $type, EvaluationContext $ctx): array
    {
        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $class = $type->getName();

        if ($class === Request::class && $ctx->request !== null) {
            return [$ctx->request];
        }

        if (is_a($class, User::class, true) && $ctx->user !== null) {
            return [$ctx->user];
        }

        if (is_a($class, Model::class, true)) {
            foreach ($ctx->models as $model) {
                if ($model instanceof $class) {
                    return [$model];
                }
            }
            if ($ctx->record instanceof $class) {
                return [$ctx->record];
            }
        }

        return [];
    }
}
