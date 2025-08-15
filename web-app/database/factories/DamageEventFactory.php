<?php

namespace Database\Factories;

use App\Models\DamageEvent;
use App\Models\GameMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DamageEvent>
 */
class DamageEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DamageEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'armor_damage' => $this->faker->numberBetween(0, 100),
            'attacker_steam_id' => 'STEAM_'.$this->faker->numberBetween(100000000, 999999999),
            'damage' => $this->faker->numberBetween(1, 150),
            'headshot' => $this->faker->boolean(),
            'health_damage' => $this->faker->numberBetween(1, 100),
            'round_number' => $this->faker->numberBetween(1, 30),
            'round_time' => $this->faker->numberBetween(0, 120),
            'tick_timestamp' => $this->faker->numberBetween(1000, 100000),
            'victim_steam_id' => 'STEAM_'.$this->faker->numberBetween(100000000, 999999999),
            'weapon' => $this->faker->randomElement(['ak47', 'm4a1', 'awp', 'deagle', 'usp']),
        ];
    }
}
