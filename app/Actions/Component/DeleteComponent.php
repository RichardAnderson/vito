<?php

namespace App\Actions\Component;

use App\Enums\ComponentStatus;
use App\Jobs\Component\DeleteComponentJob;
use App\Models\Component;
use Illuminate\Support\Facades\DB;

class DeleteComponent
{
    public function delete(Component $component): void
    {
        $component->status = ComponentStatus::DELETING;
        $component->save();

        DB::afterCommit(fn () => dispatch(new DeleteComponentJob($component->id, $component->server_id))->onQueue('ssh'));
    }
}
