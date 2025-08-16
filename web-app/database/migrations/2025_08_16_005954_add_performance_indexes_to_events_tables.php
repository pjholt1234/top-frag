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
        // Add composite indexes for better performance in UserMatchHistoryService
        Schema::table('gunfight_events', function (Blueprint $table) {
            // Composite index for match_id + victor_steam_id (for kill statistics)
            $table->index(['match_id', 'victor_steam_id'], 'gunfight_match_victor_idx');

            // Composite index for match_id + player_1_steam_id (for death statistics)
            $table->index(['match_id', 'player_1_steam_id'], 'gunfight_match_player1_idx');

            // Composite index for match_id + player_2_steam_id (for death statistics)
            $table->index(['match_id', 'player_2_steam_id'], 'gunfight_match_player2_idx');

            // Composite index for match_id + is_first_kill (for first kill statistics)
            $table->index(['match_id', 'is_first_kill'], 'gunfight_match_first_kill_idx');
        });

        Schema::table('damage_events', function (Blueprint $table) {
            // Composite index for match_id + attacker_steam_id (for damage statistics)
            $table->index(['match_id', 'attacker_steam_id'], 'damage_match_attacker_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gunfight_events', function (Blueprint $table) {
            $table->dropIndex('gunfight_match_victor_idx');
            $table->dropIndex('gunfight_match_player1_idx');
            $table->dropIndex('gunfight_match_player2_idx');
            $table->dropIndex('gunfight_match_first_kill_idx');
        });

        Schema::table('damage_events', function (Blueprint $table) {
            $table->dropIndex('damage_match_attacker_idx');
        });
    }
};
