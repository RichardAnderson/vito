<?php

use App\Enums\EnvironmentDomainStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The applications feature is unreleased, so `routes` was replaced in place inside
 * `create_application_tables`. This brings a database that already ran the earlier version
 * of that migration in line; on a fresh database every guard is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('environment_domains')) {
            Schema::create('environment_domains', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ssl_id')->nullable()->constrained('ssls')->nullOnDelete();
                $table->string('host');
                $table->string('ssl_method')->default('letsencrypt');
                $table->string('status')->default(EnvironmentDomainStatus::CREATING->value);
                $table->timestamps();

                $table->unique(['environment_id', 'host']);
                $table->index('host');
            });
        }

        Schema::dropIfExists('routes');
    }

    public function down(): void
    {
        //
    }
};
