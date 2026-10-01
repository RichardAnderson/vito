<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id')->nullable()->change();
            $table->unsignedBigInteger('deployment_script_id')->nullable()->change();
            $table->foreignId('component_id')->nullable()->after('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_deployment_id')->nullable()->after('component_id')->constrained()->cascadeOnDelete();
            $table->string('current_stage')->nullable()->after('status');
        });

        Schema::table('deployment_scripts', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id')->nullable()->change();
            $table->foreignId('environment_id')->nullable()->after('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->nullable()->after('environment_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('workers', function (Blueprint $table): void {
            $table->foreignId('component_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index('component_id');
        });

        Schema::table('cron_jobs', function (Blueprint $table): void {
            $table->foreignId('component_id')->nullable()->constrained()->cascadeOnDelete();
            $table->index('component_id');
        });
    }

    public function down(): void
    {
        Schema::table('cron_jobs', function (Blueprint $table): void {
            $table->dropIndex(['component_id']);
            $table->dropConstrainedForeignId('component_id');
        });

        Schema::table('workers', function (Blueprint $table): void {
            $table->dropIndex(['component_id']);
            $table->dropConstrainedForeignId('component_id');
        });

        Schema::table('deployment_scripts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('component_id');
            $table->dropConstrainedForeignId('environment_id');
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropColumn('current_stage');
            $table->dropConstrainedForeignId('environment_deployment_id');
            $table->dropConstrainedForeignId('component_id');
        });
    }
};
