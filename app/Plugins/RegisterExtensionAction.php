<?php

namespace App\Plugins;

use App\DTOs\DynamicForm;
use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\Binding;
use App\Pages\ExtensionActionRegistry;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Plugin SDK hook: a standalone server handler for contributions to hard-coded pages
 * (which have nowhere else to POST). Context is compositional and always rooted in an
 * Area — ->area() supplies route-borne context through the Area's full pipeline
 * (binding chain, project boundary, canView), and ->bind() adds input-borne children
 * scoped onto it. Routed POST {area-prefix}/ext/{plugin}/{action}. The authorize
 * callback is mandatory and is the write gate.
 */
class RegisterExtensionAction
{
    private string $method = 'post';

    /**
     * @var class-string<AbstractArea>|null
     */
    private ?string $areaClass = null;

    /**
     * @var array<int, Binding>
     */
    private array $binds = [];

    private ?Closure $authorize = null;

    private ?Closure $handler = null;

    private ?DynamicForm $form = null;

    private string $plugin = 'core';

    public function __construct(
        private string $action,
    ) {}

    public static function make(string $action): self
    {
        return new self($action);
    }

    public function method(string $method): self
    {
        $this->method = strtolower($method);

        return $this;
    }

    /**
     * @param  class-string<AbstractArea>  $areaClass
     */
    public function area(string $areaClass): self
    {
        $this->areaClass = $areaClass;

        return $this;
    }

    public function bind(string $param, string $model, string $scopedTo, ?string $foreignKey = null): self
    {
        $this->binds[] = Binding::make($param, $model, $scopedTo, $foreignKey);

        return $this;
    }

    /**
     * @param  Closure(User, array<string, \Illuminate\Database\Eloquent\Model>): bool  $check
     */
    public function authorize(Closure $check): self
    {
        $this->authorize = $check;

        return $this;
    }

    /**
     * @param  Closure(array<string, \Illuminate\Database\Eloquent\Model>, array<string, mixed>, Request): mixed  $handler
     */
    public function handler(Closure $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function form(DynamicForm $form): self
    {
        $this->form = $form;

        return $this;
    }

    public function register(): void
    {
        app(ExtensionActionRegistry::class)->add($this);
    }

    public function attribute(string $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function name(): string
    {
        return $this->action;
    }

    public function plugin(): string
    {
        return $this->plugin;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return class-string<AbstractArea>
     */
    public function getAreaClass(): string
    {
        if ($this->areaClass === null) {
            throw new RuntimeException("Extension action '{$this->action}' must declare an area().");
        }

        return $this->areaClass;
    }

    public function hasArea(): bool
    {
        return $this->areaClass !== null;
    }

    /**
     * @return array<int, Binding>
     */
    public function getBinds(): array
    {
        return $this->binds;
    }

    public function getForm(): ?DynamicForm
    {
        return $this->form;
    }

    public function hasAuthorization(): bool
    {
        return $this->authorize !== null;
    }

    /**
     * @param  array<string, \Illuminate\Database\Eloquent\Model>  $models
     */
    public function isAuthorized(User $user, array $models): bool
    {
        return $this->authorize !== null && ($this->authorize)($user, $models);
    }

    /**
     * @param  array<string, \Illuminate\Database\Eloquent\Model>  $models
     * @param  array<string, mixed>  $input
     */
    public function run(array $models, array $input, Request $request): mixed
    {
        if ($this->handler === null) {
            throw new RuntimeException("Extension action '{$this->action}' has no handler.");
        }

        return ($this->handler)($models, $input, $request);
    }
}
