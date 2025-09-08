<?php

namespace Tests\Feature\Models;

use App\Enums\MatchType;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\GunfightEvent;
use App\Models\MatchPlayer;
use App\Models\MatchSummary;
use App\Models\Player;
use App\Models\PlayerMatchSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameMatchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_game_match()
    {
        $match = GameMatch::factory()->create([
            'match_hash' => 'abc123',
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => MatchType::MATCHMAKING,
            'start_timestamp' => '2023-01-01 12:00:00',
            'end_timestamp' => '2023-01-01 13:30:00',
            'total_rounds' => 30,
            'total_fight_events' => 150,
            'total_grenade_events' => 75,
        ]);

        $this->assertInstanceOf(GameMatch::class, $match);
        $this->assertEquals('abc123', $match->match_hash);
        $this->assertEquals('de_dust2', $match->map);
        $this->assertEquals(16, $match->winning_team_score);
        $this->assertEquals(14, $match->losing_team_score);
        $this->assertEquals(MatchType::MATCHMAKING, $match->match_type);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $match = new GameMatch;
        $expectedFillable = [
            'match_hash',
            'map',
            'winning_team',
            'winning_team_score',
            'losing_team_score',
            'match_type',
            'start_timestamp',
            'end_timestamp',
            'total_rounds',
            'total_fight_events',
            'total_grenade_events',
            'playback_ticks',
        ];
        $this->assertEquals($expectedFillable, $match->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $match = GameMatch::factory()->create([
            'match_type' => MatchType::MATCHMAKING,
            'start_timestamp' => '2023-01-01 12:00:00',
            'end_timestamp' => '2023-01-01 13:30:00',
            'total_rounds' => 30,
            'total_fight_events' => 150,
            'total_grenade_events' => 75,
        ]);

        $this->assertInstanceOf(MatchType::class, $match->match_type);
        $this->assertInstanceOf(\Carbon\Carbon::class, $match->start_timestamp);
        $this->assertInstanceOf(\Carbon\Carbon::class, $match->end_timestamp);
        $this->assertIsInt($match->total_rounds);
        $this->assertIsInt($match->total_fight_events);
        $this->assertIsInt($match->total_grenade_events);
    }

    #[Test]
    public function it_has_many_match_players()
    {
        $match = GameMatch::factory()->create();
        $matchPlayer1 = MatchPlayer::factory()->create(['match_id' => $match->id]);
        $matchPlayer2 = MatchPlayer::factory()->create(['match_id' => $match->id]);

        $this->assertCount(2, $match->matchPlayers);
        $this->assertInstanceOf(MatchPlayer::class, $match->matchPlayers->first());
    }

    #[Test]
    public function it_has_many_players_through_pivot()
    {
        $match = GameMatch::factory()->create();
        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();

        // Create pivot records
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player1->id,
            'team' => Team::TEAM_A,
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player2->id,
            'team' => Team::TEAM_B,
        ]);

        $this->assertCount(2, $match->players);
        $this->assertInstanceOf(Player::class, $match->players->first());
        $this->assertArrayHasKey('team', $match->players->first()->pivot->toArray());
    }

    #[Test]
    public function it_has_many_gunfight_events()
    {
        $match = GameMatch::factory()->create();
        $gunfightEvent1 = GunfightEvent::factory()->create(['match_id' => $match->id]);
        $gunfightEvent2 = GunfightEvent::factory()->create(['match_id' => $match->id]);

        $this->assertCount(2, $match->gunfightEvents);
        $this->assertInstanceOf(GunfightEvent::class, $match->gunfightEvents->first());
    }

    #[Test]
    public function it_has_many_grenade_events()
    {
        $match = GameMatch::factory()->create();
        $grenadeEvent1 = GrenadeEvent::factory()->create(['match_id' => $match->id]);
        $grenadeEvent2 = GrenadeEvent::factory()->create(['match_id' => $match->id]);

        $this->assertCount(2, $match->grenadeEvents);
        $this->assertInstanceOf(GrenadeEvent::class, $match->grenadeEvents->first());
    }

    #[Test]
    public function it_has_one_match_summary()
    {
        $match = GameMatch::factory()->create();
        $summary = MatchSummary::factory()->create(['match_id' => $match->id]);

        $this->assertInstanceOf(MatchSummary::class, $match->matchSummary);
        $this->assertEquals($summary->id, $match->matchSummary->id);
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $match = new GameMatch;
        $this->assertEquals('matches', $match->getTable());
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $match = GameMatch::factory()->create();

        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'match_hash' => $match->match_hash,
            'map' => $match->map,
        ]);
    }
}
