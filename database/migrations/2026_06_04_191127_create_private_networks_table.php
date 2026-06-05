<?php

use App\Enums\PrivateNetworkStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_networks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subnet');
            $table->unsignedSmallInteger('mtu')->default(1420);
            $table->string('status')->default(PrivateNetworkStatus::READY->value);
            $table->timestamps();

            $table->unique(['project_id', 'name']);
            $table->unique('subnet');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_networks');
    }
};
