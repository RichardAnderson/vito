<?php

namespace App\Pages\Components;

use App\Pages\DataEndpoint;
use App\Pages\PageAction;
use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * A page panel rendered by a registered custom React control (the panel-control
 * registry). Carries serializable props (DI-evaluated) and, optionally, its own
 * actions/data endpoints so an interactive panel's behaviour is harvested for routing.
 */
final class Control extends AbstractComponent
{
    private ?string $using = null;

    /**
     * @var array<string, mixed>|Closure
     */
    private array|Closure $controlProps = [];

    /**
     * @var array<int, PageAction>
     */
    private array $controlActions = [];

    /**
     * @var array<int, DataEndpoint>
     */
    private array $controlData = [];

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'control';
    }

    public function using(string $name): self
    {
        $this->using = $name;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $props
     */
    public function with(array|Closure $props): self
    {
        $this->controlProps = $props;

        return $this;
    }

    /**
     * @param  array<int, PageAction>  $actions
     */
    public function withActions(array $actions): self
    {
        $this->controlActions = $actions;

        return $this;
    }

    /**
     * @param  array<int, DataEndpoint>  $data
     */
    public function withData(array $data): self
    {
        $this->controlData = $data;

        return $this;
    }

    /**
     * @return array<int, PageAction>
     */
    public function actions(): array
    {
        return $this->controlActions;
    }

    /**
     * @return array<int, DataEndpoint>
     */
    public function data(): array
    {
        return $this->controlData;
    }

    protected function props(EvaluationContext $ctx): array
    {
        return [
            'using' => $this->using,
            'props' => $this->evaluate($this->controlProps, $ctx),
        ];
    }
}
