<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GrenadeFavourite>
 */
class GrenadeFavouriteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'user_id' => User::factory(),
            'round_number' => $this->faker->numberBetween(1, 30),
            'round_time' => $this->faker->randomFloat(2, 0, 300),
            'tick_timestamp' => $this->faker->numberBetween(0, 1000000),
            'player_steam_id' => 'STEAM_'.$this->faker->numberBetween(100000000, 999999999),
            'player_side' => $this->faker->randomElement(['T', 'CT']),
            'grenade_type' => $this->faker->randomElement(['flashbang', 'smoke', 'hegrenade', 'molotov', 'incendiary']),
            'player_x' => $this->faker->randomFloat(2, -2000, 2000),
            'player_y' => $this->faker->randomFloat(2, -2000, 2000),
            'player_z' => $this->faker->randomFloat(2, 0, 500),
            'player_aim_x' => $this->faker->randomFloat(2, -2000, 2000),
            'player_aim_y' => $this->faker->randomFloat(2, -2000, 2000),
            'player_aim_z' => $this->faker->randomFloat(2, 0, 500),
            'grenade_final_x' => $this->faker->randomFloat(2, -2000, 2000),
            'grenade_final_y' => $this->faker->randomFloat(2, -2000, 2000),
            'grenade_final_z' => $this->faker->randomFloat(2, 0, 500),
            'damage_dealt' => $this->faker->randomFloat(2, 0, 100),
            'flash_duration' => $this->faker->optional()->randomFloat(2, 0, 10),
            'friendly_flash_duration' => $this->faker->optional()->randomFloat(2, 0, 10),
            'enemy_flash_duration' => $this->faker->optional()->randomFloat(2, 0, 10),
            'friendly_players_affected' => $this->faker->optional()->numberBetween(0, 5),
            'enemy_players_affected' => $this->faker->optional()->numberBetween(0, 5),
            'throw_type' => $this->faker->randomElement(['pop', 'run', 'jump', 'walk']),
            'effectiveness_rating' => $this->faker->optional()->randomFloat(1, 0, 10),
        ];
    }
}
