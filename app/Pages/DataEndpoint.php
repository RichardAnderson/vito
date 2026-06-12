<?php

namespace App\Pages;

use App\Models\User;
use App\Pages\Schema\EvaluatesClosures;
use App\Pages\Schema\EvaluationContext;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A page data endpoint: {page}/data/{id} returning JSON for components (log
 * polling, editor load, async selects). GET by default; POST mode supports
 * compute-style interactions (e.g. VHost preview: unsaved buffer in, render out).
 * Read-only endpoints may inherit the page/Area canView gate; otherwise an explicit
 * authorize closure is required. The resolver and authorize are closure-DI evaluated.
 */
final class DataEndpoint
{
    use EvaluatesClosures;

    private string $method = 'get';

    /**
     * @var array<int, Binding>
     */
    private array $binds = [];

    private ?Closure $authorize = null;

    private bool $inheritCanView = false;

    private ?Closure $resolver = null;

    private ?string $routeName = null;

    public function __construct(
        private readonly string $id,
    ) {}

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function method(string $method): self
    {
        $this->method = strtolower($method);

        return $this;
    }

    public function post(): self
    {
        return $this->method('post');
    }

    public function bind(string $param, string $model, ?string $scopedTo = null, ?string $foreignKey = null): self
    {
        $this->binds[] = $scopedTo === null
            ? Binding::root($param, $model)
            : Binding::make($param, $model, $scopedTo, $foreignKey);

        return $this;
    }

    /**
     * Read-level gate, closure-DI evaluated: `fn (User $user, Site $site): bool`.
     */
    public function authorize(Closure $check): self
    {
        $this->authorize = $check;

        return $this;
    }

    public function inheritCanView(): self
    {
        $this->inheritCanView = true;

        return $this;
    }

    /**
     * Read-only endpoint that inherits the area/page canView gate.
     */
    public function public(): self
    {
        return $this->inheritCanView();
    }

    /**
     * The data producer, closure-DI evaluated: `fn (Site $site) => [...]`.
     */
    public function resolve(Closure $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Legacy alias for resolve() — DI-evaluated identically.
     */
    public function resolver(Closure $resolver): self
    {
        return $this->resolve($resolver);
    }

    public function routeName(string $name): self
    {
        $this->routeName = $name;

        return $this;
    }

    public function getRouteName(?string $default = null): ?string
    {
        return $this->routeName ?? $default;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<int, Binding>
     */
    public function getBinds(): array
    {
        return $this->binds;
    }

    public function inheritsCanView(): bool
    {
        return $this->inheritCanView;
    }

    public function hasAuthorization(): bool
    {
        return $this->authorize !== null || $this->inheritCanView;
    }

    /**
     * @param  array<string, \Illuminate\Database\Eloquent\Model>  $models
     */
    public function isAuthorized(User $user, array $models): bool
    {
        if ($this->authorize === null) {
            return false;
        }

        return (bool) $this->evaluate($this->authorize, EvaluationContext::make($models, $user));
    }

    /**
     * @param  array<string, \Illuminate\Database\Eloquent\Model>  $models
     * @return array<string, mixed>
     */
    public function run(array $models, Request $request): array
    {
        if ($this->resolver === null) {
            throw new RuntimeException("Data endpoint '{$this->id}' has no resolver.");
        }

        $ctx = new EvaluationContext($models, $request->user(), $request, null, $request->all());

        return $this->evaluate($this->resolver, $ctx);
    }
}
