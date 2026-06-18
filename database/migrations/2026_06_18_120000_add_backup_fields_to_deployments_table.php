<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->boolean('has_backups')->default(false)->after('active');
            $table->json('backup_manifest')->nullable()->after('has_backups');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropColumn(['has_backups', 'backup_manifest']);
        });
    }
};
