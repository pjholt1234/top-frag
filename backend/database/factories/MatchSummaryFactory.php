<?php

namespace Database\Factories;

use App\Models\MatchSummary;
use App\Models\GameMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchSummary>
 */
class MatchSummaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MatchSummary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'total_kills' => $this->faker->numberBetween(0, 200),
            'total_deaths' => $this->faker->numberBetween(0, 200),
            'total_assists' => $this->faker->numberBetween(0, 100),
            'total_headshots' => $this->faker->numberBetween(0, 100),
            'total_wallbangs' => $this->faker->numberBetween(0, 50),
            'total_damage' => $this->faker->numberBetween(0, 30000),
            'total_he_damage' => $this->faker->numberBetween(0, 5000),
            'total_effective_flashes' => $this->faker->numberBetween(0, 50),
            'total_smokes_used' => $this->faker->numberBetween(0, 40),
            'total_molotovs_used' => $this->faker->numberBetween(0, 30),
            'total_first_kills' => $this->faker->numberBetween(0, 20),
            'total_first_deaths' => $this->faker->numberBetween(0, 20),
            'total_clutches_1v1_attempted' => $this->faker->numberBetween(0, 10),
            'total_clutches_1v1_successful' => $this->faker->numberBetween(0, 10),
            'total_clutches_1v2_attempted' => $this->faker->numberBetween(0, 8),
            'total_clutches_1v2_successful' => $this->faker->numberBetween(0, 8),
            'total_clutches_1v3_attempted' => $this->faker->numberBetween(0, 6),
            'total_clutches_1v3_successful' => $this->faker->numberBetween(0, 6),
            'total_clutches_1v4_attempted' => $this->faker->numberBetween(0, 4),
            'total_clutches_1v4_successful' => $this->faker->numberBetween(0, 4),
            'total_clutches_1v5_attempted' => $this->faker->numberBetween(0, 2),
            'total_clutches_1v5_successful' => $this->faker->numberBetween(0, 2),
        ];
    }
}
