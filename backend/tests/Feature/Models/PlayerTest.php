<?php

namespace Tests\Unit\Models;

use App\Models\Player;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\GunfightEvent;
use App\Models\GrenadeEvent;
use App\Models\PlayerMatchSummary;
use App\Enums\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_player()
    {
        $player = Player::factory()->create([
            'steam_id' => 'STEAM_123456789',
            'name' => 'TestPlayer',
            'first_seen_at' => '2023-01-01 12:00:00',
            'last_seen_at' => '2023-01-02 12:00:00',
            'total_matches' => 10,
        ]);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals('STEAM_123456789', $player->steam_id);
        $this->assertEquals('TestPlayer', $player->name);
        $this->assertEquals(10, $player->total_matches);
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $player = new Player();

        $expectedFillable = ['steam_id', 'name', 'first_seen_at', 'last_seen_at', 'total_matches'];
        $this->assertEquals($expectedFillable, $player->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $player = Player::factory()->create([
            'first_seen_at' => '2023-01-01 12:00:00',
            'last_seen_at' => '2023-01-02 12:00:00',
            'total_matches' => 15,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $player->first_seen_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $player->last_seen_at);
        $this->assertIsInt($player->total_matches);
        $this->assertEquals(15, $player->total_matches);
    }

    /** @test */
    public function it_has_many_match_players()
    {
        $player = Player::factory()->create();
        $matchPlayer1 = MatchPlayer::factory()->create(['player_id' => $player->id]);
        $matchPlayer2 = MatchPlayer::factory()->create(['player_id' => $player->id]);

        $this->assertCount(2, $player->matchPlayers);
        $this->assertInstanceOf(MatchPlayer::class, $player->matchPlayers->first());
    }

    /** @test */
    public function it_belongs_to_many_matches()
    {
        $player = Player::factory()->create();
        $match1 = GameMatch::factory()->create();
        $match2 = GameMatch::factory()->create();

        // Create pivot records
        MatchPlayer::factory()->create([
            'player_id' => $player->id,
            'match_id' => $match1->id,
            'team' => Team::TERRORIST,
            'side_start' => Team::TERRORIST,
        ]);
        MatchPlayer::factory()->create([
            'player_id' => $player->id,
            'match_id' => $match2->id,
            'team' => Team::COUNTER_TERRORIST,
            'side_start' => Team::COUNTER_TERRORIST,
        ]);

        $this->assertCount(2, $player->matches);
        $this->assertInstanceOf(GameMatch::class, $player->matches->first());
        $this->assertArrayHasKey('team', $player->matches->first()->pivot->toArray());
        $this->assertArrayHasKey('side_start', $player->matches->first()->pivot->toArray());
    }

    /** @test */
    public function it_has_gunfight_events_as_player1()
    {
        $player = Player::factory()->create();
        $gunfightEvent = GunfightEvent::factory()->create(['player_1_id' => $player->id]);

        $this->assertCount(1, $player->gunfightEventsAsPlayer1);
        $this->assertInstanceOf(GunfightEvent::class, $player->gunfightEventsAsPlayer1->first());
    }

    /** @test */
    public function it_has_gunfight_events_as_player2()
    {
        $player = Player::factory()->create();
        $gunfightEvent = GunfightEvent::factory()->create(['player_2_id' => $player->id]);

        $this->assertCount(1, $player->gunfightEventsAsPlayer2);
        $this->assertInstanceOf(GunfightEvent::class, $player->gunfightEventsAsPlayer2->first());
    }

    /** @test */
    public function it_has_gunfight_events_as_victor()
    {
        $player = Player::factory()->create();
        $gunfightEvent = GunfightEvent::factory()->create(['victor_id' => $player->id]);

        $this->assertCount(1, $player->gunfightEventsAsVictor);
        $this->assertInstanceOf(GunfightEvent::class, $player->gunfightEventsAsVictor->first());
    }

    /** @test */
    public function it_has_grenade_events()
    {
        $player = Player::factory()->create();
        $grenadeEvent = GrenadeEvent::factory()->create(['player_id' => $player->id]);

        $this->assertCount(1, $player->grenadeEvents);
        $this->assertInstanceOf(GrenadeEvent::class, $player->grenadeEvents->first());
    }

    /** @test */
    public function it_has_player_match_summaries()
    {
        $player = Player::factory()->create();
        $summary = PlayerMatchSummary::factory()->create(['player_id' => $player->id]);

        $this->assertCount(1, $player->playerMatchSummaries);
        $this->assertInstanceOf(PlayerMatchSummary::class, $player->playerMatchSummaries->first());
    }

    /** @test */
    public function it_can_be_created_with_factory()
    {
        $player = Player::factory()->create();

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'steam_id' => $player->steam_id,
            'name' => $player->name,
        ]);
    }
}
