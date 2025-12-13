<?php

namespace Database\Factories;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClanMember>
 */
class ClanMemberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ClanMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clan_id' => Clan::factory(),
            'user_id' => User::factory(),
        ];
    }
}
