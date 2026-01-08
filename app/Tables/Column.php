<?php

namespace App\Tables;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class Column implements Arrayable
{
    /**
     * @param  array<string>  $linkParams
     * @param  array<string>  $fields  Database fields this column requires
     */
    public function __construct(
        public readonly string $accessor,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly bool $sortable = true,
        public readonly bool $searchable = false,
        public readonly ?string $linkRoute = null,
        public readonly array $linkParams = [],
        public readonly ?string $colorAccessor = null,
        public readonly bool $hidden = false,
        public readonly array $fields = [],
    ) {}

    public static function text(string $accessor, string $label): self
    {
        return new self(
            accessor: $accessor,
            label: $label,
            type: 'text',
            fields: [$accessor],
        );
    }

    public static function date(string $accessor, string $label): self
    {
        return new self(
            accessor: $accessor,
            label: $label,
            type: 'date',
            fields: [$accessor],
        );
    }

    public static function status(string $accessor, string $label, string $colorAccessor): self
    {
        return new self(
            accessor: $accessor,
            label: $label,
            type: 'status',
            colorAccessor: $colorAccessor,
            fields: [$accessor, $colorAccessor],
        );
    }

    /**
     * @param  array<string, string>  $params
     */
    public static function link(string $accessor, string $label, string $route, array $params = []): self
    {
        $paramFields = collect($params)
            ->filter(fn (string $v) => str_starts_with($v, ':'))
            ->map(fn (string $v) => substr($v, 1))
            ->values()
            ->all();

        return new self(
            accessor: $accessor,
            label: $label,
            type: 'link',
            linkRoute: $route,
            linkParams: $params,
            fields: array_values(array_unique(array_merge([$accessor], $paramFields))),
        );
    }

    /**
     * @param  array<string, string>  $params
     */
    public static function actions(string $route, array $params = []): self
    {
        $paramFields = collect($params)
            ->filter(fn (string $v) => str_starts_with($v, ':'))
            ->map(fn (string $v) => substr($v, 1))
            ->values()
            ->all();

        return new self(
            accessor: 'actions',
            label: '',
            type: 'actions',
            sortable: false,
            searchable: false,
            linkRoute: $route,
            linkParams: $params,
            fields: $paramFields,
        );
    }

    public function sortable(bool $sortable = true): self
    {
        return new self(
            accessor: $this->accessor,
            label: $this->label,
            type: $this->type,
            sortable: $sortable,
            searchable: $this->searchable,
            linkRoute: $this->linkRoute,
            linkParams: $this->linkParams,
            colorAccessor: $this->colorAccessor,
            hidden: $this->hidden,
            fields: $this->fields,
        );
    }

    public function searchable(bool $searchable = true): self
    {
        return new self(
            accessor: $this->accessor,
            label: $this->label,
            type: $this->type,
            sortable: $this->sortable,
            searchable: $searchable,
            linkRoute: $this->linkRoute,
            linkParams: $this->linkParams,
            colorAccessor: $this->colorAccessor,
            hidden: $this->hidden,
            fields: $this->fields,
        );
    }

    public function hidden(bool $hidden = true): self
    {
        return new self(
            accessor: $this->accessor,
            label: $this->label,
            type: $this->type,
            sortable: $this->sortable,
            searchable: $this->searchable,
            linkRoute: $this->linkRoute,
            linkParams: $this->linkParams,
            colorAccessor: $this->colorAccessor,
            hidden: $hidden,
            fields: $this->fields,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'accessor' => $this->accessor,
            'label' => $this->label,
            'type' => $this->type,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'linkRoute' => $this->linkRoute,
            'linkParams' => $this->linkParams,
            'colorAccessor' => $this->colorAccessor,
            'hidden' => $this->hidden,
        ];
    }
}
