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
        Schema::table('grenade_events', function (Blueprint $table) {
            // Drop the affected_players JSON column
            $table->dropColumn('affected_players');

            // Add new integer columns for player counts
            $table->integer('friendly_players_affected')->default(0)->after('enemy_flash_duration');
            $table->integer('enemy_players_affected')->default(0)->after('friendly_players_affected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grenade_events', function (Blueprint $table) {
            // Drop the new columns
            $table->dropColumn(['friendly_players_affected', 'enemy_players_affected']);

            // Re-add the affected_players JSON column
            $table->json('affected_players')->nullable()->after('enemy_flash_duration');
        });
    }
};
