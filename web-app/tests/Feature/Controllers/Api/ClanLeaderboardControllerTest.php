<?php

namespace Tests\Feature\Controllers\Api;

use App\Enums\LeaderboardType;
use App\Models\Clan;
use App\Models\ClanLeaderboard;
use App\Models\ClanMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClanLeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->clan = Clan::factory()->create(['owned_by' => $this->user->id]);

        ClanMember::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_get_all_leaderboards()
    {
        Sanctum::actingAs($this->user);

        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $user2 = User::factory()->create();
        ClanMember::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $user2->id,
        ]);

        // Create some leaderboard entries
        foreach (LeaderboardType::cases() as $type) {
            ClanLeaderboard::factory()->create([
                'clan_id' => $this->clan->id,
                'user_id' => $this->user->id,
                'leaderboard_type' => $type->value,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'position' => 1,
                'value' => 100.0,
            ]);
        }

        $response = $this->getJson("/api/clans/{$this->clan->id}/leaderboards?period=week");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'aim' => [
                        '*' => [
                            'position',
                            'user_id',
                            'user_name',
                            'user_avatar',
                            'value',
                        ],
                    ],
                    'impact',
                    'round_swing',
                    'fragger',
                    'support',
                    'opener',
                    'closer',
                ],
            ]);
    }

    public function test_user_can_get_specific_leaderboard()
    {
        Sanctum::actingAs($this->user);

        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        ClanLeaderboard::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $this->user->id,
            'leaderboard_type' => LeaderboardType::AIM->value,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'position' => 1,
            'value' => 85.5,
        ]);

        $response = $this->getJson("/api/clans/{$this->clan->id}/leaderboards/aim?period=week");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'position',
                        'user_id',
                        'user_name',
                        'user_avatar',
                        'value',
                    ],
                ],
                'type',
                'start_date',
                'end_date',
            ]);
    }

    public function test_user_can_get_monthly_leaderboard()
    {
        Sanctum::actingAs($this->user);

        $startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        ClanLeaderboard::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $this->user->id,
            'leaderboard_type' => LeaderboardType::FRAGGER->value,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'position' => 1,
            'value' => 90.0,
        ]);

        $response = $this->getJson("/api/clans/{$this->clan->id}/leaderboards/fragger?period=month");

        $response->assertStatus(200)
            ->assertJson([
                'type' => 'fragger',
            ]);
    }

    public function test_user_can_get_leaderboard_with_type_query_parameter()
    {
        Sanctum::actingAs($this->user);

        $startDate = Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        ClanLeaderboard::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $this->user->id,
            'leaderboard_type' => LeaderboardType::IMPACT->value,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'position' => 1,
            'value' => 60.10,
        ]);

        $response = $this->getJson("/api/clans/{$this->clan->id}/leaderboards?type=impact&period=week");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'position',
                        'user_id',
                        'user_name',
                        'user_avatar',
                        'value',
                    ],
                ],
                'type',
                'start_date',
                'end_date',
            ])
            ->assertJson([
                'type' => 'impact',
            ]);

        // Verify data is an array (not an object)
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals(60.10, $data[0]['value']);
    }
}
