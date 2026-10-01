<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('git_hooks', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id')->nullable()->change();
            $table->foreignId('environment_id')->nullable()->after('site_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('git_hooks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('environment_id');
        });
    }
};
