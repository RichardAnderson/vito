<?php

namespace App\Pages;

use Illuminate\Database\Eloquent\Model;

/**
 * Declarative descriptor for one model in an Area's context chain. The root binding
 * resolves a model from a route/input param; child bindings additionally verify the
 * resolved model belongs to its parent (foreign key match → 404 on mismatch). This
 * is the single scoping mechanism shared by Areas, page actions, data endpoints and
 * extension actions, making cross-project/IDOR access structural rather than a check
 * each handler must remember to write.
 */
final class Binding
{
    /**
     * @param  class-string<Model>  $model
     */
    private function __construct(
        public readonly string $param,
        public readonly string $model,
        public readonly ?string $scopedTo = null,
        private readonly ?string $foreignKey = null,
    ) {}

    /**
     * @param  class-string<Model>  $model
     */
    public static function root(string $param, string $model): self
    {
        return new self($param, $model);
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function make(string $param, string $model, string $scopedTo, ?string $foreignKey = null): self
    {
        return new self($param, $model, $scopedTo, $foreignKey);
    }

    public function isRoot(): bool
    {
        return $this->scopedTo === null;
    }

    public function foreignKeyColumn(): string
    {
        return $this->foreignKey ?? $this->scopedTo.'_id';
    }

    /**
     * Resolve this binding's model from a raw param value (id) or an already-bound
     * model instance, verifying parentage for child bindings.
     *
     * @param  array<string, Model>  $resolved  Models resolved earlier in the chain
     */
    public function resolve(mixed $value, array $resolved): Model
    {
        $model = $value instanceof Model
            ? $value
            : $this->model::query()->findOrFail($value);

        if (! $this->isRoot()) {
            $parent = $resolved[$this->scopedTo] ?? abort(404);

            abort_unless(
                (int) $model->getAttribute($this->foreignKeyColumn()) === (int) $parent->getKey(),
                404,
            );
        }

        return $model;
    }
}
