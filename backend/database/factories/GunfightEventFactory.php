<?php

namespace Database\Factories;

use App\Models\GunfightEvent;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GunfightEvent>
 */
class GunfightEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GunfightEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $player1 = Player::factory();
        $player2 = Player::factory();

        return [
            'match_id' => GameMatch::factory(),
            'round_number' => $this->faker->numberBetween(1, 30),
            'round_time' => $this->faker->numberBetween(0, 115),
            'tick_timestamp' => $this->faker->numberBetween(100000000, 999999999),
            'player_1_id' => $player1,
            'player_2_id' => $player2,
            'player_1_hp_start' => $this->faker->numberBetween(1, 100),
            'player_2_hp_start' => $this->faker->numberBetween(1, 100),
            'player_1_armor' => $this->faker->numberBetween(0, 100),
            'player_2_armor' => $this->faker->numberBetween(0, 100),
            'player_1_flashed' => $this->faker->boolean(),
            'player_2_flashed' => $this->faker->boolean(),
            'player_1_weapon' => $this->faker->randomElement(['ak47', 'm4a1', 'awp', 'deagle', 'usp', 'glock']),
            'player_2_weapon' => $this->faker->randomElement(['ak47', 'm4a1', 'awp', 'deagle', 'usp', 'glock']),
            'player_1_equipment_value' => $this->faker->numberBetween(0, 10000),
            'player_2_equipment_value' => $this->faker->numberBetween(0, 10000),
            'player_1_x' => $this->faker->randomFloat(2, -2000, 2000),
            'player_1_y' => $this->faker->randomFloat(2, -2000, 2000),
            'player_1_z' => $this->faker->randomFloat(2, 0, 200),
            'player_2_x' => $this->faker->randomFloat(2, -2000, 2000),
            'player_2_y' => $this->faker->randomFloat(2, -2000, 2000),
            'player_2_z' => $this->faker->randomFloat(2, 0, 200),
            'distance' => $this->faker->randomFloat(2, 0, 500),
            'headshot' => $this->faker->boolean(),
            'wallbang' => $this->faker->boolean(),
            'penetrated_objects' => $this->faker->numberBetween(0, 5),
            'victor_id' => $this->faker->randomElement([$player1, $player2]),
            'damage_dealt' => $this->faker->numberBetween(1, 100),
        ];
    }
}
