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
        Schema::table('clans', function (Blueprint $table) {
            $table->string('discord_guild_id')->nullable()->after('tag');
            $table->unique('discord_guild_id');
            $table->index('discord_guild_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropIndex(['discord_guild_id']);
            $table->dropUnique(['discord_guild_id']);
            $table->dropColumn('discord_guild_id');
        });
    }
};
