<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Player::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'steam_id' => 'STEAM_'.$this->faker->numberBetween(100000000, 999999999),
            'name' => $this->faker->userName(),
            'first_seen_at' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
            'last_seen_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'total_matches' => $this->faker->numberBetween(1, 100),
        ];
    }
}
