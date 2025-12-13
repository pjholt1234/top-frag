<?php

namespace Tests\Feature\Services;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\User;
use App\Services\Clans\ClanMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClanMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClanMatchService $service;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ClanMatchService;

        $user1 = User::factory()->create(['steam_id' => '76561198011111111']);
        $user2 = User::factory()->create(['steam_id' => '76561198022222222']);

        $this->clan = Clan::factory()->create(['owned_by' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $this->clan->id, 'user_id' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $this->clan->id, 'user_id' => $user2->id]);
    }

    public function test_it_finds_matches_with_multiple_clan_members()
    {
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);
        $player2 = Player::factory()->create(['steam_id' => '76561198022222222']);

        $match = GameMatch::factory()->create();
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player1->id,
            'team' => 'A',
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player2->id,
            'team' => 'A',
        ]);

        $matches = $this->service->findMatchesWithMultipleClanMembers($this->clan);

        $this->assertCount(1, $matches);
        $this->assertEquals($match->id, $matches->first()->id);
    }

    public function test_it_checks_and_adds_match_when_members_played_together()
    {
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);
        $player2 = Player::factory()->create(['steam_id' => '76561198022222222']);

        $match = GameMatch::factory()->create();
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player1->id,
            'team' => 'A',
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player2->id,
            'team' => 'A',
        ]);

        $added = $this->service->checkAndAddMatch($this->clan, $match);

        $this->assertTrue($added);
        $this->assertDatabaseHas('clan_matches', [
            'clan_id' => $this->clan->id,
            'match_id' => $match->id,
        ]);
    }

    public function test_it_does_not_add_match_when_members_on_different_teams()
    {
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);
        $player2 = Player::factory()->create(['steam_id' => '76561198022222222']);

        $match = GameMatch::factory()->create();
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player1->id,
            'team' => 'A',
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player2->id,
            'team' => 'B',
        ]);

        $added = $this->service->checkAndAddMatch($this->clan, $match);

        $this->assertFalse($added);
        $this->assertDatabaseMissing('clan_matches', [
            'clan_id' => $this->clan->id,
            'match_id' => $match->id,
        ]);
    }

    public function test_it_does_not_add_duplicate_match()
    {
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);
        $player2 = Player::factory()->create(['steam_id' => '76561198022222222']);

        $match = GameMatch::factory()->create();
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player1->id,
            'team' => 'A',
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player2->id,
            'team' => 'A',
        ]);

        // Add match first time
        $this->service->checkAndAddMatch($this->clan, $match);

        // Try to add again
        $added = $this->service->checkAndAddMatch($this->clan, $match);

        $this->assertFalse($added);
    }
}
