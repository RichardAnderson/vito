<?php

use App\Enums\EnvironmentDomainType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redirects moved onto `environment_domains` as a type, so the separate table goes. Both changes
 * are folded into `create_application_tables` for a fresh database; this reconciles one that
 * already ran the earlier shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('environment_domains', 'type')) {
            Schema::table('environment_domains', function (Blueprint $table): void {
                $table->unsignedBigInteger('redirect_to_domain_id')->nullable()->after('ssl_id');
                $table->string('type')->default(EnvironmentDomainType::ALIAS->value)->after('host');
            });

            Schema::table('environment_domains', function (Blueprint $table): void {
                $table->foreign('redirect_to_domain_id')->references('id')->on('environment_domains')->nullOnDelete();
            });
        }

        Schema::dropIfExists('environment_redirects');
    }

    public function down(): void
    {
        Schema::table('environment_domains', function (Blueprint $table): void {
            $table->dropForeign(['redirect_to_domain_id']);
            $table->dropColumn(['redirect_to_domain_id', 'type']);
        });
    }
};
