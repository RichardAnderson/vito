<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firewall_rules', function (Blueprint $table): void {
            $table->foreignId('private_network_member_id')
                ->nullable()
                ->after('server_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('protocol')->nullable()->change();
            $table->string('port', 11)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('firewall_rules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('private_network_member_id');
            $table->string('protocol')->nullable(false)->change();
            $table->string('port', 11)->nullable(false)->change();
        });
    }
};
