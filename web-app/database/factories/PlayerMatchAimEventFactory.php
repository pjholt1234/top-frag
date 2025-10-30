<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerMatchAimEvent>
 */
class PlayerMatchAimEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shotsFired = $this->faker->numberBetween(50, 500);
        $shotsHit = $this->faker->numberBetween(10, $shotsFired);
        $accuracyAllShots = ($shotsFired > 0) ? round(($shotsHit / $shotsFired) * 100, 2) : 0;

        $sprayingShotsFired = $this->faker->numberBetween(20, 200);
        $sprayingShotsHit = $this->faker->numberBetween(5, $sprayingShotsFired);
        $sprayingAccuracy = ($sprayingShotsFired > 0) ? round(($sprayingShotsHit / $sprayingShotsFired) * 100, 2) : 0;

        $headHits = $this->faker->numberBetween(5, $shotsHit);
        $headshotAccuracy = ($shotsHit > 0) ? round(($headHits / $shotsHit) * 100, 2) : 0;

        return [
            'match_id' => \App\Models\GameMatch::factory(),
            'player_steam_id' => 'STEAM_' . $this->faker->randomNumber(9),
            'shots_fired' => $shotsFired,
            'shots_hit' => $shotsHit,
            'accuracy_all_shots' => $accuracyAllShots,
            'spraying_shots_fired' => $sprayingShotsFired,
            'spraying_shots_hit' => $sprayingShotsHit,
            'spraying_accuracy' => $sprayingAccuracy,
            'average_crosshair_placement_x' => $this->faker->randomFloat(3, -10, 10),
            'average_crosshair_placement_y' => $this->faker->randomFloat(3, -10, 10),
            'headshot_accuracy' => $headshotAccuracy,
            'average_time_to_damage' => $this->faker->randomFloat(4, 0.1, 2.0),
            'head_hits_total' => $headHits,
            'upper_chest_hits_total' => $this->faker->numberBetween(5, 50),
            'chest_hits_total' => $this->faker->numberBetween(10, 80),
            'legs_hits_total' => $this->faker->numberBetween(5, 40),
            'aim_rating' => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
