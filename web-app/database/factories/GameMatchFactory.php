<?php

namespace Database\Factories;

use App\Enums\MatchType;
use App\Models\GameMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameMatch>
 */
class GameMatchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GameMatch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('-1 month', '-1 day');
        $endTime = $this->faker->dateTimeBetween($startTime, 'now');

        return [
            'match_hash' => $this->faker->unique()->regexify('[A-Za-z0-9]{32}'),
            'map' => $this->faker->randomElement(['de_dust2', 'de_mirage', 'de_inferno', 'de_overpass', 'de_nuke', 'de_ancient', 'de_vertigo']),
            'winning_team_score' => $this->faker->numberBetween(16, 30),
            'losing_team_score' => $this->faker->numberBetween(0, 15),
            'match_type' => $this->faker->randomElement(MatchType::cases()),
            'start_timestamp' => $startTime,
            'end_timestamp' => $endTime,
            'total_rounds' => $this->faker->numberBetween(15, 50),
            'total_fight_events' => $this->faker->numberBetween(50, 300),
            'total_grenade_events' => $this->faker->numberBetween(20, 150),
        ];
    }
}
