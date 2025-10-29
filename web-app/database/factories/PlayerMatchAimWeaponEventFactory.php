<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerMatchAimWeaponEvent>
 */
class PlayerMatchAimWeaponEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shotsFired = $this->faker->numberBetween(20, 200);
        $shotsHit = $this->faker->numberBetween(5, $shotsFired);
        $accuracyAllShots = ($shotsFired > 0) ? round(($shotsHit / $shotsFired) * 100, 2) : 0;

        $sprayingShotsFired = $this->faker->numberBetween(10, 100);
        $sprayingShotsHit = $this->faker->numberBetween(2, $sprayingShotsFired);
        $sprayingAccuracy = ($sprayingShotsFired > 0) ? round(($sprayingShotsHit / $sprayingShotsFired) * 100, 2) : 0;

        $headHits = $this->faker->numberBetween(2, $shotsHit);
        $headshotAccuracy = ($shotsHit > 0) ? round(($headHits / $shotsHit) * 100, 2) : 0;

        $weapons = [
            'ak47',
            'awp',
            'm4a1',
            'm4a1_silencer',
            'glock',
            'usp_silencer',
            'deagle',
            'p250',
            'famas',
            'galilar',
            'aug',
            'sg556',
        ];

        return [
            'match_id' => \App\Models\GameMatch::factory(),
            'player_steam_id' => 'STEAM_'.$this->faker->randomNumber(9),
            'weapon_name' => $this->faker->randomElement($weapons),
            'shots_fired' => $shotsFired,
            'shots_hit' => $shotsHit,
            'accuracy_all_shots' => $accuracyAllShots,
            'spraying_shots_fired' => $sprayingShotsFired,
            'spraying_shots_hit' => $sprayingShotsHit,
            'spraying_accuracy' => $sprayingAccuracy,
            'average_crosshair_placement_x' => $this->faker->randomFloat(3, -10, 10),
            'average_crosshair_placement_y' => $this->faker->randomFloat(3, -10, 10),
            'headshot_accuracy' => $headshotAccuracy,
            'head_hits_total' => $headHits,
            'upper_chest_hits_total' => $this->faker->numberBetween(2, 20),
            'chest_hits_total' => $this->faker->numberBetween(5, 40),
            'legs_hits_total' => $this->faker->numberBetween(2, 15),
        ];
    }
}
