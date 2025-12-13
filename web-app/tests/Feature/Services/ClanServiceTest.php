<?php

namespace Tests\Feature\Services;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\User;
use App\Services\Clans\ClanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClanServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClanService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ClanService;
        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
        ]);
    }

    public function test_it_can_create_clan()
    {
        $clan = $this->service->create($this->user, [
            'name' => 'Test Clan',
            'tag' => 'TC',
        ]);

        $this->assertInstanceOf(Clan::class, $clan);
        $this->assertEquals('Test Clan', $clan->name);
        $this->assertEquals('TC', $clan->tag);
        $this->assertEquals($this->user->id, $clan->owned_by);
        $this->assertNotNull($clan->invite_link);

        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_can_join_clan_via_invite_link()
    {
        $owner = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $owner->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $owner->id,
        ]);

        $joinedClan = $this->service->join($this->user, $clan->invite_link);

        $this->assertEquals($clan->id, $joinedClan->id);
        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_can_leave_clan()
    {
        $owner = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $owner->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $owner->id,
        ]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $this->service->leave($this->user, $clan);

        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_can_regenerate_invite_link()
    {
        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        $oldInviteLink = $clan->invite_link;

        $newInviteLink = $this->service->updateInviteLink($clan);

        $this->assertNotEquals($oldInviteLink, $newInviteLink);
        $clan->refresh();
        $this->assertEquals($newInviteLink, $clan->invite_link);
    }

    public function test_it_finds_matches_for_clan_with_multiple_members()
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

        $this->service->findMatchesForClan($clan);

        $this->assertDatabaseHas('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match->id,
        ]);
    }

    public function test_it_does_not_find_matches_for_clan_with_single_member()
    {
        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $this->user->id]);

        $match = GameMatch::factory()->create();

        $this->service->findMatchesForClan($clan);

        $this->assertDatabaseMissing('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match->id,
        ]);
    }

    public function test_it_does_not_find_matches_where_members_are_on_different_teams()
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

        $this->service->findMatchesForClan($clan);

        $this->assertDatabaseMissing('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match->id,
        ]);
    }
}
