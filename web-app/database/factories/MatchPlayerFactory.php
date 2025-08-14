<?php

namespace Database\Factories;

use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchPlayer>
 */
class MatchPlayerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MatchPlayer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'player_id' => Player::factory(),
            'team' => $this->faker->randomElement(Team::cases()),
        ];
    }
}
