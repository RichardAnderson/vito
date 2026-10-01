<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An attached database is referenced by an alias, so the alias is recorded on the link itself
 * rather than only inferred from the DB_{ALIAS}_* variables it wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('environment_resources', 'alias')) {
            return;
        }

        Schema::table('environment_resources', function (Blueprint $table): void {
            $table->string('alias')->nullable()->after('resource_id');
        });
    }

    public function down(): void
    {
        Schema::table('environment_resources', function (Blueprint $table): void {
            $table->dropColumn('alias');
        });
    }
};
