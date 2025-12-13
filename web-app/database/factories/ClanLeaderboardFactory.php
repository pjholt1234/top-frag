<?php

namespace Database\Factories;

use App\Enums\LeaderboardType;
use App\Models\Clan;
use App\Models\ClanLeaderboard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClanLeaderboard>
 */
class ClanLeaderboardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ClanLeaderboard::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays(7);

        return [
            'clan_id' => Clan::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'leaderboard_type' => $this->faker->randomElement(LeaderboardType::cases())->value,
            'user_id' => User::factory(),
            'position' => $this->faker->numberBetween(1, 10),
            'value' => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
