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
        Schema::create('player_match_aim_weapon_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->string('player_steam_id');
            $table->string('weapon_name');

            // Basic aim statistics
            $table->integer('shots_fired')->default(0);
            $table->integer('shots_hit')->default(0);
            $table->decimal('accuracy_all_shots', 5, 2)->default(0);

            // Spray statistics
            $table->integer('spraying_shots_fired')->default(0);
            $table->integer('spraying_shots_hit')->default(0);
            $table->decimal('spraying_accuracy', 5, 2)->default(0);

            // Crosshair placement
            $table->decimal('average_crosshair_placement_x', 8, 3)->default(0);
            $table->decimal('average_crosshair_placement_y', 8, 3)->default(0);

            // Headshot accuracy
            $table->decimal('headshot_accuracy', 5, 2)->default(0);

            // Reaction time
            $table->decimal('average_time_to_damage', 8, 4)->default(0);

            // Hit region breakdown
            $table->integer('head_hits_total')->default(0);
            $table->integer('upper_chest_hits_total')->default(0);
            $table->integer('chest_hits_total')->default(0);
            $table->integer('legs_hits_total')->default(0);

            $table->timestamps();

            $table->index(['match_id', 'player_steam_id', 'weapon_name'], 'pmawe_match_player_weapon_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_match_aim_weapon_events');
    }
};
