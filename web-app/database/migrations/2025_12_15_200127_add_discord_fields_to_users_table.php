<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_id')->nullable()->after('steam_link_hash');
            $table->string('discord_link_hash', 64)->unique()->nullable()->after('discord_id');
            $table->index('discord_link_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['discord_link_hash']);
            $table->dropColumn(['discord_id', 'discord_link_hash']);
        });
    }
};
