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
        Schema::table('gunfight_events', function (Blueprint $table) {
            // Impact Rating Fields
            $table->decimal('player_1_team_strength', 10, 2)->default(0);
            $table->decimal('player_2_team_strength', 10, 2)->default(0);
            $table->decimal('player_1_impact', 10, 2)->default(0);
            $table->decimal('player_2_impact', 10, 2)->default(0);
            $table->decimal('assister_impact', 10, 2)->default(0);
            $table->decimal('flash_assister_impact', 10, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gunfight_events', function (Blueprint $table) {
            $table->dropColumn([
                'player_1_team_strength',
                'player_2_team_strength',
                'player_1_impact',
                'player_2_impact',
                'assister_impact',
                'flash_assister_impact',
            ]);
        });
    }
};
