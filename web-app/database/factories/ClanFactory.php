<?php

namespace Database\Factories;

use App\Models\Clan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Clan>
 */
class ClanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Clan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owned_by' => User::factory(),
            'invite_link' => (string) Str::uuid(),
            'name' => $this->faker->company().' Clan',
            'tag' => $this->faker->optional()->lexify('???'),
        ];
    }
}
