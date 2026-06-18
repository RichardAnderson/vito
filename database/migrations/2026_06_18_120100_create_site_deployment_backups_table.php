<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_deployment_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('folders')->nullable();
            $table->json('databases')->nullable();
            $table->foreignId('storage_id')->nullable()->constrained('storage_providers')->nullOnDelete();
            $table->unsignedInteger('keep')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_deployment_backups');
    }
};
