<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerMatchEvent>
 */
class PlayerMatchEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => \App\Models\GameMatch::factory(),
            'player_steam_id' => 'STEAM_'.$this->faker->randomNumber(9),
            'kills' => $this->faker->numberBetween(0, 30),
            'assists' => $this->faker->numberBetween(0, 20),
            'deaths' => $this->faker->numberBetween(0, 30),
            'damage' => $this->faker->numberBetween(0, 5000),
            'adr' => $this->faker->randomFloat(1, 0, 150),
            'headshots' => $this->faker->numberBetween(0, 20),
            'first_kills' => $this->faker->numberBetween(0, 10),
            'first_deaths' => $this->faker->numberBetween(0, 10),
            'average_round_time_of_death' => $this->faker->randomFloat(2, 0, 120),
            'kills_with_awp' => $this->faker->numberBetween(0, 10),
            'damage_dealt' => $this->faker->numberBetween(0, 5000),
            'flashes_thrown' => $this->faker->numberBetween(0, 50),
            'friendly_flash_duration' => $this->faker->randomFloat(2, 0, 100),
            'enemy_flash_duration' => $this->faker->randomFloat(2, 0, 100),
            'friendly_players_affected' => $this->faker->numberBetween(0, 5),
            'enemy_players_affected' => $this->faker->numberBetween(0, 5),
            'flashes_leading_to_kills' => $this->faker->numberBetween(0, 10),
            'flashes_leading_to_deaths' => $this->faker->numberBetween(0, 10),
            'average_grenade_effectiveness' => $this->faker->randomFloat(2, 0, 1),
            'smoke_blocking_duration' => $this->faker->numberBetween(0, 5000),
            'total_successful_trades' => $this->faker->numberBetween(0, 20),
            'total_possible_trades' => $this->faker->numberBetween(0, 20),
            'total_traded_deaths' => $this->faker->numberBetween(0, 20),
            'total_possible_traded_deaths' => $this->faker->numberBetween(0, 20),
            'clutch_wins_1v1' => $this->faker->numberBetween(0, 5),
            'clutch_wins_1v2' => $this->faker->numberBetween(0, 5),
            'clutch_wins_1v3' => $this->faker->numberBetween(0, 5),
            'clutch_wins_1v4' => $this->faker->numberBetween(0, 5),
            'clutch_wins_1v5' => $this->faker->numberBetween(0, 5),
            'clutch_attempts_1v1' => $this->faker->numberBetween(0, 10),
            'clutch_attempts_1v2' => $this->faker->numberBetween(0, 10),
            'clutch_attempts_1v3' => $this->faker->numberBetween(0, 10),
            'clutch_attempts_1v4' => $this->faker->numberBetween(0, 10),
            'clutch_attempts_1v5' => $this->faker->numberBetween(0, 10),
            'average_time_to_contact' => $this->faker->randomFloat(2, 0, 120),
            'kills_vs_eco' => $this->faker->numberBetween(0, 10),
            'kills_vs_force_buy' => $this->faker->numberBetween(0, 10),
            'kills_vs_full_buy' => $this->faker->numberBetween(0, 20),
            'average_grenade_value_lost' => $this->faker->randomFloat(2, 0, 1000),
            'matchmaking_rank' => $this->faker->numberBetween(1, 18),
            'total_impact' => $this->faker->randomFloat(2, 0, 100),
            'average_impact' => $this->faker->randomFloat(2, 0, 100),
            'match_swing_percent' => $this->faker->randomFloat(2, 0, 100),
            'impact_percentage' => $this->faker->randomFloat(2, 0, 100),
            'rank_value' => $this->faker->numberBetween(1, 18),
            'rank_type' => $this->faker->randomElement(['DMG', 'LEM', 'SMFC', 'GE']),
        ];
    }
}
