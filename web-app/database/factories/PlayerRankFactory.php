<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerRank>
 */
class PlayerRankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'rank_type' => $this->faker->randomElement(['competitive', 'premier', 'faceit']),
            'map' => null,
            'rank' => $this->faker->randomElement(['Silver I', 'Silver II', 'Gold Nova', 'Master Guardian', 'Legendary Eagle', 'Global Elite']),
            'rank_value' => $this->faker->numberBetween(1, 18),
        ];
    }

    /**
     * Indicate that the rank is for competitive mode with a map.
     */
    public function competitive(): static
    {
        return $this->state(fn (array $attributes) => [
            'rank_type' => 'competitive',
            'map' => $this->faker->randomElement(['de_dust2', 'de_mirage', 'de_inferno', 'de_ancient', 'de_anubis']),
        ]);
    }

    /**
     * Indicate that the rank is for premier mode.
     */
    public function premier(): static
    {
        return $this->state(fn (array $attributes) => [
            'rank_type' => 'premier',
            'map' => null,
        ]);
    }

    /**
     * Indicate that the rank is for faceit.
     */
    public function faceit(): static
    {
        return $this->state(fn (array $attributes) => [
            'rank_type' => 'faceit',
            'map' => null,
        ]);
    }
}
