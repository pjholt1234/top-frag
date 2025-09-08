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
        Schema::create('player_match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->string('player_steam_id');

            // Gun Fight fields
            $table->integer('kills')->default(0);
            $table->integer('assists')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('damage')->default(0);
            $table->decimal('adr')->default(0);
            $table->integer('headshots')->default(0);
            $table->integer('first_kills')->default(0);
            $table->integer('first_deaths')->default(0);
            $table->decimal('average_round_time_of_death')->default(0);
            $table->integer('kills_with_awp')->default(0);

            // Grenade fields
            $table->integer('damage_dealt')->default(0);
            $table->integer('flashes_thrown')->default(0);
            $table->decimal('friendly_flash_duration')->default(0);
            $table->decimal('enemy_flash_duration')->default(0);
            $table->integer('friendly_players_affected')->default(0);
            $table->integer('enemy_players_affected')->default(0);
            $table->integer('flashes_leading_to_kills')->default(0);
            $table->integer('flashes_leading_to_deaths')->default(0);
            $table->decimal('average_grenade_effectiveness')->default(0);

            // Trade fields
            $table->integer('total_successful_trades')->default(0);
            $table->integer('total_possible_trades')->default(0);
            $table->integer('total_traded_deaths')->default(0);
            $table->integer('total_possible_traded_deaths')->default(0);
            $table->integer('clutch_wins_1v1')->default(0);
            $table->integer('clutch_wins_1v2')->default(0);
            $table->integer('clutch_wins_1v3')->default(0);
            $table->integer('clutch_wins_1v4')->default(0);
            $table->integer('clutch_wins_1v5')->default(0);
            $table->integer('clutch_attempts_1v1')->default(0);
            $table->integer('clutch_attempts_1v2')->default(0);
            $table->integer('clutch_attempts_1v3')->default(0);
            $table->integer('clutch_attempts_1v4')->default(0);
            $table->integer('clutch_attempts_1v5')->default(0);

            $table->decimal('average_time_to_contact')->default(0);
            $table->integer('kills_vs_eco')->default(0);
            $table->integer('kills_vs_force_buy')->default(0);
            $table->integer('kills_vs_full_buy')->default(0);
            $table->integer('average_grenade_value_lost')->default(0);
            $table->string('matchmaking_rank')->nullable();

            // Indexes for performance
            $table->index(['match_id', 'player_steam_id']);
            $table->index('match_id');
            $table->index('player_steam_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_match_events');
    }
};
