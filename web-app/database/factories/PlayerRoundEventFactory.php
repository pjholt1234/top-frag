<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerRoundEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerRoundEvent>
 */
class PlayerRoundEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PlayerRoundEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'player_steam_id' => Player::factory(),
            'round_number' => $this->faker->numberBetween(1, 30),

            // Gun Fight fields
            'kills' => $this->faker->numberBetween(0, 5),
            'assists' => $this->faker->numberBetween(0, 3),
            'died' => $this->faker->boolean(),
            'damage' => $this->faker->numberBetween(0, 500),
            'headshots' => $this->faker->numberBetween(0, 2),
            'first_kill' => $this->faker->boolean(),
            'first_death' => $this->faker->boolean(),
            'round_time_of_death' => $this->faker->optional()->numberBetween(1, 115),
            'kills_with_awp' => $this->faker->numberBetween(0, 2),

            // Grenade fields
            'damage_dealt' => $this->faker->numberBetween(0, 300),
            'flashes_thrown' => $this->faker->numberBetween(0, 5),
            'friendly_flash_duration' => $this->faker->randomFloat(3, 0, 2),
            'enemy_flash_duration' => $this->faker->randomFloat(3, 0, 5),
            'friendly_players_affected' => $this->faker->numberBetween(0, 4),
            'enemy_players_affected' => $this->faker->numberBetween(0, 5),
            'flashes_leading_to_kill' => $this->faker->numberBetween(0, 2),
            'flashes_leading_to_death' => $this->faker->numberBetween(0, 1),
            'grenade_effectiveness' => $this->faker->randomFloat(4, 0, 1),

            // Trade fields
            'successful_trades' => $this->faker->numberBetween(0, 3),
            'total_possible_trades' => $this->faker->numberBetween(0, 5),
            'successful_traded_deaths' => $this->faker->numberBetween(0, 2),
            'total_possible_traded_deaths' => $this->faker->numberBetween(0, 3),

            // Clutch fields
            'clutch_attempts_1v1' => $this->faker->numberBetween(0, 1),
            'clutch_attempts_1v2' => $this->faker->numberBetween(0, 1),
            'clutch_attempts_1v3' => $this->faker->numberBetween(0, 1),
            'clutch_attempts_1v4' => $this->faker->numberBetween(0, 1),
            'clutch_attempts_1v5' => $this->faker->numberBetween(0, 1),
            'clutch_wins_1v1' => $this->faker->numberBetween(0, 1),
            'clutch_wins_1v2' => $this->faker->numberBetween(0, 1),
            'clutch_wins_1v3' => $this->faker->numberBetween(0, 1),
            'clutch_wins_1v4' => $this->faker->numberBetween(0, 1),
            'clutch_wins_1v5' => $this->faker->numberBetween(0, 1),

            'time_to_contact' => $this->faker->randomFloat(3, 0, 60),

            // Economy fields
            'is_eco' => $this->faker->boolean(),
            'is_force_buy' => $this->faker->boolean(),
            'is_full_buy' => $this->faker->boolean(),
            'kills_vs_eco' => $this->faker->numberBetween(0, 3),
            'kills_vs_force_buy' => $this->faker->numberBetween(0, 2),
            'kills_vs_full_buy' => $this->faker->numberBetween(0, 2),
            'grenade_value_lost_on_death' => $this->faker->numberBetween(0, 2000),
        ];
    }
}
