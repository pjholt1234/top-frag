<?php

namespace Database\Factories;

use App\Models\PlayerMatchSummary;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerMatchSummary>
 */
class PlayerMatchSummaryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PlayerMatchSummary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kills = $this->faker->numberBetween(0, 50);
        $deaths = $this->faker->numberBetween(0, 50);
        $headshots = $this->faker->numberBetween(0, $kills);
        $clutches_1v1_attempted = $this->faker->numberBetween(0, 5);
        $clutches_1v1_successful = $this->faker->numberBetween(0, $clutches_1v1_attempted);
        $clutches_1v2_attempted = $this->faker->numberBetween(0, 4);
        $clutches_1v2_successful = $this->faker->numberBetween(0, $clutches_1v2_attempted);
        $clutches_1v3_attempted = $this->faker->numberBetween(0, 3);
        $clutches_1v3_successful = $this->faker->numberBetween(0, $clutches_1v3_attempted);
        $clutches_1v4_attempted = $this->faker->numberBetween(0, 2);
        $clutches_1v4_successful = $this->faker->numberBetween(0, $clutches_1v4_attempted);
        $clutches_1v5_attempted = $this->faker->numberBetween(0, 1);
        $clutches_1v5_successful = $this->faker->numberBetween(0, $clutches_1v5_attempted);

        $total_clutches_attempted = $clutches_1v1_attempted + $clutches_1v2_attempted + $clutches_1v3_attempted + $clutches_1v4_attempted + $clutches_1v5_attempted;
        $total_clutches_successful = $clutches_1v1_successful + $clutches_1v2_successful + $clutches_1v3_successful + $clutches_1v4_successful + $clutches_1v5_successful;

        $kd_ratio = $deaths > 0 ? round($kills / $deaths, 2) : ($kills > 0 ? $kills : 0);
        $headshot_percentage = $kills > 0 ? round(($headshots / $kills) * 100, 2) : 0;
        $clutch_success_rate = $total_clutches_attempted > 0 ? round(($total_clutches_successful / $total_clutches_attempted) * 100, 2) : 0;

        return [
            'match_id' => GameMatch::factory(),
            'player_id' => Player::factory(),
            'kills' => $kills,
            'deaths' => $deaths,
            'assists' => $this->faker->numberBetween(0, 20),
            'headshots' => $headshots,
            'wallbangs' => $this->faker->numberBetween(0, 10),
            'first_kills' => $this->faker->numberBetween(0, 10),
            'first_deaths' => $this->faker->numberBetween(0, 10),
            'total_damage' => $this->faker->numberBetween(0, 8000),
            'average_damage_per_round' => $this->faker->randomFloat(2, 0, 300),
            'damage_taken' => $this->faker->numberBetween(0, 8000),
            'he_damage' => $this->faker->numberBetween(0, 1000),
            'effective_flashes' => $this->faker->numberBetween(0, 15),
            'smokes_used' => $this->faker->numberBetween(0, 10),
            'molotovs_used' => $this->faker->numberBetween(0, 8),
            'flashbangs_used' => $this->faker->numberBetween(0, 12),
            'clutches_1v1_attempted' => $clutches_1v1_attempted,
            'clutches_1v1_successful' => $clutches_1v1_successful,
            'clutches_1v2_attempted' => $clutches_1v2_attempted,
            'clutches_1v2_successful' => $clutches_1v2_successful,
            'clutches_1v3_attempted' => $clutches_1v3_attempted,
            'clutches_1v3_successful' => $clutches_1v3_successful,
            'clutches_1v4_attempted' => $clutches_1v4_attempted,
            'clutches_1v4_successful' => $clutches_1v4_successful,
            'clutches_1v5_attempted' => $clutches_1v5_attempted,
            'clutches_1v5_successful' => $clutches_1v5_successful,
            'kd_ratio' => $kd_ratio,
            'headshot_percentage' => $headshot_percentage,
            'clutch_success_rate' => $clutch_success_rate,
        ];
    }
}
