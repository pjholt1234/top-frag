<?php

namespace Tests\Feature\Models;

use App\Models\Clan;
use App\Models\ClanLeaderboard;
use App\Models\ClanMember;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClanTest extends TestCase
{
    use RefreshDatabase;

    public function test_clan_has_owner_relationship()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);

        $this->assertInstanceOf(User::class, $clan->owner);
        $this->assertEquals($user->id, $clan->owner->id);
    }

    public function test_clan_has_members_relationship()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $member1->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $member2->id]);

        $this->assertCount(3, $clan->members);
    }

    public function test_clan_has_users_relationship()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $clan->users()->attach([$user->id, $member1->id, $member2->id]);

        $this->assertCount(3, $clan->users);
    }

    public function test_clan_has_matches_relationship()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);

        $match1 = GameMatch::factory()->create();
        $match2 = GameMatch::factory()->create();

        $clan->matches()->attach([$match1->id, $match2->id]);

        $this->assertCount(2, $clan->matches);
    }

    public function test_clan_has_leaderboards_relationship()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);

        ClanLeaderboard::factory()->count(3)->create(['clan_id' => $clan->id]);

        $this->assertCount(3, $clan->leaderboards);
    }

    public function test_clan_can_generate_invite_link()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);
        $oldInviteLink = $clan->invite_link;

        $newInviteLink = $clan->generateInviteLink();

        $this->assertNotEquals($oldInviteLink, $newInviteLink);
        $this->assertEquals($newInviteLink, $clan->invite_link);
    }

    public function test_clan_can_check_if_user_is_owner()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);

        $this->assertTrue($clan->isOwner($user));

        $otherUser = User::factory()->create();
        $this->assertFalse($clan->isOwner($otherUser));
    }

    public function test_clan_can_check_if_user_is_member()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user->id]);

        $this->assertTrue($clan->isMember($user));

        $otherUser = User::factory()->create();
        $this->assertFalse($clan->isMember($otherUser));
    }
}
