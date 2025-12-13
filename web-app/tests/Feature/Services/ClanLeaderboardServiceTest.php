<?php

namespace Tests\Feature\Services;

use App\Enums\LeaderboardType;
use App\Models\Clan;
use App\Models\ClanLeaderboard;
use App\Models\ClanMember;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Clans\ClanLeaderboardService;
use App\Services\Matches\PlayerComplexionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClanLeaderboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClanLeaderboardService $service;

    private Clan $clan;

    private User $user1;

    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ClanLeaderboardService(
            app(PlayerComplexionService::class)
        );

        $this->user1 = User::factory()->create(['steam_id' => '76561198011111111']);
        $this->user2 = User::factory()->create(['steam_id' => '76561198022222222']);

        $this->clan = Clan::factory()->create(['owned_by' => $this->user1->id]);
        ClanMember::factory()->create(['clan_id' => $this->clan->id, 'user_id' => $this->user1->id]);
        ClanMember::factory()->create(['clan_id' => $this->clan->id, 'user_id' => $this->user2->id]);
    }

    public function test_it_calculates_aim_leaderboard()
    {
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);
        $player2 = Player::factory()->create(['steam_id' => '76561198022222222']);

        $match = GameMatch::factory()->create([
            'match_start_time' => Carbon::now()->subDays(3),
        ]);
        $this->clan->matches()->attach($match->id);

        PlayerMatchAimEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player1->steam_id,
            'aim_rating' => 90.0,
        ]);
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player2->steam_id,
            'aim_rating' => 80.0,
        ]);

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $this->service->calculateLeaderboard(
            $this->clan,
            LeaderboardType::AIM->value,
            $startDate,
            $endDate
        );

        $leaderboard = ClanLeaderboard::where('clan_id', $this->clan->id)
            ->where('leaderboard_type', LeaderboardType::AIM->value)
            ->get();

        $this->assertCount(2, $leaderboard);
        $this->assertEquals(1, $leaderboard->where('user_id', $this->user1->id)->first()->position);
        $this->assertEquals(2, $leaderboard->where('user_id', $this->user2->id)->first()->position);
    }

    public function test_it_calculates_impact_leaderboard()
    {
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);
        $player2 = Player::factory()->create(['steam_id' => '76561198022222222']);

        $match = GameMatch::factory()->create([
            'match_start_time' => Carbon::now()->subDays(3),
        ]);
        $this->clan->matches()->attach($match->id);

        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player1->steam_id,
            'average_impact' => 50.0,
        ]);
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player2->steam_id,
            'average_impact' => 40.0,
        ]);

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $this->service->calculateLeaderboard(
            $this->clan,
            LeaderboardType::IMPACT->value,
            $startDate,
            $endDate
        );

        $leaderboard = ClanLeaderboard::where('clan_id', $this->clan->id)
            ->where('leaderboard_type', LeaderboardType::IMPACT->value)
            ->get();

        $this->assertCount(2, $leaderboard);
        $this->assertEquals(50.0, $leaderboard->where('user_id', $this->user1->id)->first()->value);
    }

    public function test_it_calculates_round_swing_leaderboard()
    {
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);

        $match = GameMatch::factory()->create([
            'match_start_time' => Carbon::now()->subDays(3),
        ]);
        $this->clan->matches()->attach($match->id);

        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player1->steam_id,
            'match_swing_percent' => 25.5,
        ]);

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $this->service->calculateLeaderboard(
            $this->clan,
            LeaderboardType::ROUND_SWING->value,
            $startDate,
            $endDate
        );

        $leaderboard = ClanLeaderboard::where('clan_id', $this->clan->id)
            ->where('leaderboard_type', LeaderboardType::ROUND_SWING->value)
            ->first();

        $this->assertNotNull($leaderboard);
        $this->assertEquals(25.5, $leaderboard->value);
    }

    public function test_it_retrieves_leaderboard()
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        ClanLeaderboard::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $this->user1->id,
            'leaderboard_type' => LeaderboardType::AIM->value,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'position' => 1,
            'value' => 90.0,
        ]);

        $leaderboard = $this->service->getLeaderboard(
            $this->clan,
            LeaderboardType::AIM->value,
            $startDate,
            $endDate
        );

        $this->assertCount(1, $leaderboard);
        $this->assertEquals(1, $leaderboard->first()->position);
    }
}
