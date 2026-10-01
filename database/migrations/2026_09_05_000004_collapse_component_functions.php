<?php

use App\Support\ComponentFunctions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `queue`, `daemon` and `websocket` differed only by the command they installed, so they
 * collapse into one `processes` function. Workers and cron jobs already created from the old
 * functions are left alone — they keep running exactly as before.
 */
return new class extends Migration
{
    private const array COLLAPSED = ['queue', 'daemon', 'websocket'];

    public function up(): void
    {
        $rows = DB::table('component_functions')
            ->whereIn('function', self::COLLAPSED)
            ->orderBy('component_id')
            ->orderBy('id')
            ->get();

        $seen = DB::table('component_functions')
            ->where('function', ComponentFunctions::PROCESSES)
            ->pluck('component_id')
            ->flip()
            ->toArray();

        foreach ($rows as $row) {
            if (array_key_exists($row->component_id, $seen)) {
                DB::table('component_functions')->where('id', $row->id)->delete();

                continue;
            }

            DB::table('component_functions')
                ->where('id', $row->id)
                ->update(['function' => ComponentFunctions::PROCESSES]);

            $seen[$row->component_id] = true;
        }
    }

    public function down(): void
    {
        DB::table('component_functions')
            ->where('function', ComponentFunctions::PROCESSES)
            ->update(['function' => 'queue']);
    }
};
