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
        Schema::create('player_round_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->string('player_steam_id');
            $table->integer('round_number');

            // Gun Fight fields
            $table->integer('kills')->default(0);
            $table->integer('assists')->default(0);
            $table->boolean('died')->default(false);
            $table->integer('damage')->default(0);
            $table->integer('headshots')->default(0);
            $table->boolean('first_kill')->default(false);
            $table->boolean('first_death')->default(false);
            $table->integer('round_time_of_death')->nullable();
            $table->integer('kills_with_awp')->default(0);

            // Grenade fields
            $table->integer('damage_dealt')->default(0);
            $table->integer('flashes_thrown')->default(0);
            $table->decimal('friendly_flash_duration', 8, 3)->default(0);
            $table->decimal('enemy_flash_duration', 8, 3)->default(0);
            $table->integer('friendly_players_affected')->default(0);
            $table->integer('enemy_players_affected')->default(0);
            $table->integer('flashes_leading_to_kill')->default(0);
            $table->integer('flashes_leading_to_death')->default(0);
            $table->integer('grenade_effectiveness')->default(0);

            // Trade fields
            $table->integer('successful_trades')->default(0);
            $table->integer('total_possible_trades')->default(0);
            $table->integer('successful_traded_deaths')->default(0);
            $table->integer('total_possible_traded_deaths')->default(0);

            // Clutch fields
            $table->integer('clutch_attempts_1v1')->default(0);
            $table->integer('clutch_attempts_1v2')->default(0);
            $table->integer('clutch_attempts_1v3')->default(0);
            $table->integer('clutch_attempts_1v4')->default(0);
            $table->integer('clutch_attempts_1v5')->default(0);
            $table->integer('clutch_wins_1v1')->default(0);
            $table->integer('clutch_wins_1v2')->default(0);
            $table->integer('clutch_wins_1v3')->default(0);
            $table->integer('clutch_wins_1v4')->default(0);
            $table->integer('clutch_wins_1v5')->default(0);

            $table->decimal('time_to_contact', 8, 3)->default(0);

            // Economy fields
            $table->boolean('is_eco')->default(false);
            $table->boolean('is_force_buy')->default(false);
            $table->boolean('is_full_buy')->default(false);
            $table->integer('kills_vs_eco')->default(0);
            $table->integer('kills_vs_force_buy')->default(0);
            $table->integer('kills_vs_full_buy')->default(0);
            $table->integer('grenade_value_lost_on_death')->default(0);

            $table->timestamps();

            // Indexes for performance
            $table->index(['match_id', 'player_steam_id', 'round_number']);
            $table->index('match_id');
            $table->index('player_steam_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_round_events');
    }
};
