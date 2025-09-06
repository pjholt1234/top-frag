<?php

namespace Database\Factories;

use App\Enums\GrenadeType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GrenadeEvent>
 */
class GrenadeEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GrenadeEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'round_number' => $this->faker->numberBetween(1, 30),
            'round_time' => $this->faker->numberBetween(0, 115),
            'tick_timestamp' => $this->faker->numberBetween(100000000, 999999999),
            'player_steam_id' => Player::factory(),
            'player_side' => $this->faker->randomElement(['CT', 'T']),
            'grenade_type' => $this->faker->randomElement(GrenadeType::cases()),
            'player_x' => $this->faker->randomFloat(2, -2000, 2000),
            'player_y' => $this->faker->randomFloat(2, -2000, 2000),
            'player_z' => $this->faker->randomFloat(2, 0, 200),
            'player_aim_x' => $this->faker->randomFloat(2, -2000, 2000),
            'player_aim_y' => $this->faker->randomFloat(2, -2000, 2000),
            'player_aim_z' => $this->faker->randomFloat(2, 0, 200),
            'grenade_final_x' => $this->faker->randomFloat(2, -2000, 2000),
            'grenade_final_y' => $this->faker->randomFloat(2, -2000, 2000),
            'grenade_final_z' => $this->faker->randomFloat(2, 0, 200),
            'damage_dealt' => $this->faker->numberBetween(0, 100),
            'friendly_flash_duration' => $this->faker->randomFloat(2, 0, 5),
            'enemy_flash_duration' => $this->faker->randomFloat(2, 0, 5),
            'friendly_players_affected' => $this->faker->numberBetween(0, 5),
            'enemy_players_affected' => $this->faker->numberBetween(0, 5),
            'throw_type' => $this->faker->randomElement(['lineup', 'reaction', 'pre_aim', 'utility']),
            'effectiveness_rating' => $this->faker->numberBetween(1, 10),
            'flash_leads_to_kill' => $this->faker->boolean(20), // 20% chance of leading to kill
            'flash_leads_to_death' => $this->faker->boolean(10), // 10% chance of leading to death
        ];
    }
}
