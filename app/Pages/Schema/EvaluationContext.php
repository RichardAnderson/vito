<?php

namespace App\Pages\Schema;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The per-request evaluation context threaded through schema serialization and
 * behaviour dispatch. It is the sole carrier of request state into deferred
 * closures (`fn (Site $site) => …`), so component trees can be built model-free
 * and only see models/user/request/record/input at evaluate time.
 */
final class EvaluationContext
{
    /**
     * @param  array<string, Model>  $models
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly array $models = [],
        public readonly ?User $user = null,
        public readonly ?Request $request = null,
        public readonly ?Model $record = null,
        public readonly array $input = [],
    ) {}

    /**
     * @param  array<string, Model>  $models
     */
    public static function make(array $models = [], ?User $user = null, ?Request $request = null): self
    {
        return new self($models, $user, $request);
    }

    public function withRecord(Model $record): self
    {
        return new self($this->models, $this->user, $this->request, $record, $this->input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function withInput(array $input): self
    {
        return new self($this->models, $this->user, $this->request, $this->record, $input);
    }
}
