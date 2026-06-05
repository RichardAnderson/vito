<?php

use App\Enums\MemberStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_network_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('private_network_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('overlay_ip');
            $table->string('interface');
            $table->unsignedInteger('listen_port');
            $table->text('public_key')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('status')->default(MemberStatus::JOINING->value);
            $table->json('meta')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['private_network_id', 'server_id']);
            $table->unique(['private_network_id', 'overlay_ip']);
            $table->unique(['server_id', 'interface']);
            $table->unique(['server_id', 'listen_port']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_network_members');
    }
};
