<?php

namespace App\Pages\Components;

use App\Pages\Schema\EvaluationContext;

/**
 * A polling log/output viewer bound to a page data endpoint. Interpolates row data
 * into the endpoint params via `:column` placeholders (table row-action context).
 */
final class DynamicLogView extends AbstractComponent
{
    private ?string $endpoint = null;

    private int $interval = 2500;

    /**
     * @var array<string, mixed>
     */
    private array $params = [];

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'log-view';
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function endpoint(string $endpoint, array $params = []): self
    {
        $this->endpoint = $endpoint;
        $this->params = $params;

        return $this;
    }

    public function interval(int $milliseconds): self
    {
        $this->interval = $milliseconds;

        return $this;
    }

    protected function props(EvaluationContext $ctx): array
    {
        return [
            'endpoint' => $this->endpoint,
            'interval' => $this->interval,
            'params' => $this->params,
        ];
    }
}
