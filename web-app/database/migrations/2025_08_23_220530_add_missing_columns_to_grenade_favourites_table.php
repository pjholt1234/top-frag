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
        Schema::table('grenade_favourites', function (Blueprint $table) {
            // Add player_side column
            $table->enum('player_side', ['CT', 'T'])->after('player_steam_id')->nullable();

            // Add flash duration columns
            $table->float('friendly_flash_duration')->after('flash_duration')->nullable();
            $table->float('enemy_flash_duration')->after('friendly_flash_duration')->nullable();

            // Add player affected counts
            $table->integer('friendly_players_affected')->after('enemy_flash_duration')->nullable();
            $table->integer('enemy_players_affected')->after('friendly_players_affected')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grenade_favourites', function (Blueprint $table) {
            $table->dropColumn([
                'player_side',
                'friendly_flash_duration',
                'enemy_flash_duration',
                'friendly_players_affected',
                'enemy_players_affected',
            ]);
        });
    }
};
