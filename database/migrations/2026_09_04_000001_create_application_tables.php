<?php

use App\Enums\ComponentStatus;
use App\Enums\EnvironmentDeploymentStatus;
use App\Enums\EnvironmentDomainStatus;
use App\Enums\EnvironmentDomainType;
use App\Enums\EnvironmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_control_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('repository')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        Schema::create('environments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('primary_component_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('branch')->nullable();
            $table->string('color')->default('slate');
            $table->boolean('auto_deploy')->default(false);
            $table->string('status')->default(EnvironmentStatus::READY->value);
            $table->timestamps();

            $table->unique(['application_id', 'name']);
            $table->unique(['application_id', 'slug']);
        });

        Schema::create('components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->restrictOnDelete();
            $table->foreignId('isolated_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('path')->nullable();
            $table->string('web_directory')->nullable();
            $table->string('php_version')->nullable();
            $table->json('type_data')->nullable();
            $table->string('status')->default(ComponentStatus::CREATING->value);
            $table->text('last_error')->nullable();
            $table->boolean('needs_redeploy')->default(false);
            $table->timestamps();

            $table->unique(['environment_id', 'name']);
            $table->index(['environment_id', 'type']);
            $table->index('server_id');
        });

        Schema::table('environments', function (Blueprint $table): void {
            $table->foreign('primary_component_id')->references('id')->on('components')->nullOnDelete();
        });

        Schema::create('component_functions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->string('function');
            $table->json('config')->nullable();
            $table->string('status')->default(ComponentStatus::CREATING->value);
            $table->timestamps();

            $table->unique(['component_id', 'function']);
        });

        Schema::create('environment_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ssl_id')->nullable()->constrained('ssls')->nullOnDelete();
            $table->unsignedBigInteger('redirect_to_domain_id')->nullable();
            $table->string('host');
            $table->string('type')->default(EnvironmentDomainType::ALIAS->value);
            $table->string('ssl_method')->default('letsencrypt');
            $table->string('status')->default(EnvironmentDomainStatus::CREATING->value);
            $table->timestamps();

            $table->unique(['environment_id', 'host']);
            $table->index('host');
        });

        Schema::table('environment_domains', function (Blueprint $table): void {
            $table->foreign('redirect_to_domain_id')->references('id')->on('environment_domains')->nullOnDelete();
        });

        Schema::create('environment_variables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['environment_id', 'component_id', 'key']);
        });

        Schema::create('environment_deployments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->string('commit_id')->nullable();
            $table->json('commit_data')->nullable();
            $table->string('status')->default(EnvironmentDeploymentStatus::PENDING->value);
            $table->string('current_stage')->nullable();
            $table->string('release')->nullable();
            $table->timestamps();

            $table->index(['environment_id', 'created_at']);
        });

        Schema::create('component_backends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_component_id')->constrained('components')->cascadeOnDelete();
            $table->foreignId('network_server_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('port');
            $table->timestamps();

            $table->unique(['component_id', 'target_component_id']);
        });

        Schema::create('environment_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->morphs('resource');
            $table->string('alias')->nullable();
            $table->timestamps();

            $table->unique(
                ['environment_id', 'resource_type', 'resource_id'],
                'environment_resources_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table): void {
            $table->dropForeign(['primary_component_id']);
        });

        Schema::dropIfExists('environment_resources');
        Schema::dropIfExists('component_backends');
        Schema::dropIfExists('environment_deployments');
        Schema::dropIfExists('environment_variables');
        Schema::table('environment_domains', function (Blueprint $table): void {
            $table->dropForeign(['redirect_to_domain_id']);
        });

        Schema::dropIfExists('environment_domains');
        Schema::dropIfExists('component_functions');
        Schema::dropIfExists('components');
        Schema::dropIfExists('environments');
        Schema::dropIfExists('applications');
    }
};
