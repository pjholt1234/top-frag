<?php

namespace Database\Factories;

use App\Enums\AchievementType;
use App\Models\Achievement;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

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
            'award_name' => $this->faker->randomElement([
                AchievementType::FRAGGER->value,
                AchievementType::SUPPORT->value,
                AchievementType::OPENER->value,
                AchievementType::CLOSER->value,
                AchievementType::TOP_AIMER->value,
                AchievementType::IMPACT_PLAYER->value,
                AchievementType::DIFFERENCE_MAKER->value,
            ]),
        ];
    }
}
