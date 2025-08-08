<?php

namespace Database\Factories;

use App\Models\GrenadeEvent;
use App\Models\GameMatch;
use App\Models\Player;
use App\Enums\GrenadeType;
use App\Enums\ThrowType;
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
            'player_id' => Player::factory(),
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
            'flash_duration' => $this->faker->randomFloat(2, 0, 5),
            'affected_players' => $this->faker->optional()->randomElements(['player1', 'player2', 'player3', 'player4', 'player5'], $this->faker->numberBetween(0, 3)),
            'throw_type' => $this->faker->randomElement(ThrowType::cases()),
            'effectiveness_rating' => $this->faker->numberBetween(1, 10),
        ];
    }
}
