<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessClanMatch;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\User;
use App\Services\Clans\ClanMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessClanMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_adds_match_to_clan_when_multiple_members_played()
    {
        $user1 = User::factory()->create(['steam_id' => '76561198011111111']);
        $user2 = User::factory()->create(['steam_id' => '76561198022222222']);
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);
        $player2 = Player::factory()->create(['steam_id' => '76561198022222222']);

        $clan = Clan::factory()->create(['owned_by' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user2->id]);

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

        $job = new ProcessClanMatch($match->id);
        $job->handle(app(ClanMatchService::class));

        $this->assertDatabaseHas('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match->id,
        ]);
    }

    public function test_job_does_not_add_match_when_members_on_different_teams()
    {
        $user1 = User::factory()->create(['steam_id' => '76561198011111111']);
        $user2 = User::factory()->create(['steam_id' => '76561198022222222']);
        $player1 = Player::factory()->create(['steam_id' => '76561198011111111']);
        $player2 = Player::factory()->create(['steam_id' => '76561198022222222']);

        $clan = Clan::factory()->create(['owned_by' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user2->id]);

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

        $job = new ProcessClanMatch($match->id);
        $job->handle(app(ClanMatchService::class));

        $this->assertDatabaseMissing('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match->id,
        ]);
    }
}
