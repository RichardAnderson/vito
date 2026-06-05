<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('private_networks', function (Blueprint $table): void {
            $table->dropUnique(['subnet']);
            $table->unique(['project_id', 'subnet']);
        });
    }

    public function down(): void
    {
        Schema::table('private_networks', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'subnet']);
            $table->unique(['subnet']);
        });
    }
};
