<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Clan;
use App\Models\ClanLeaderboard;
use App\Models\ClanMember;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
        ]);
    }

    public function test_user_can_create_clan()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/clans', [
            'name' => 'TestClan',
            'tag' => 'TC',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'tag',
                    'owned_by',
                    'invite_link',
                ],
            ])
            ->assertJson([
                'message' => 'Clan created successfully',
                'data' => [
                    'name' => 'TestClan',
                    'tag' => 'TC',
                ],
            ]);

        $this->assertDatabaseHas('clans', [
            'name' => 'TestClan',
            'tag' => 'TC',
            'owned_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $response->json('data.id'),
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_cannot_create_clan_without_tag()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/clans', [
            'name' => 'TestClan',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag']);
    }

    public function test_user_cannot_create_clan_without_authentication()
    {
        $response = $this->postJson('/api/clans', [
            'name' => 'TestClan',
            'tag' => 'TC',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_cannot_create_clan_without_name()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/clans', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_list_their_clans()
    {
        Sanctum::actingAs($this->user);

        $clan1 = Clan::factory()->create(['owned_by' => $this->user->id]);
        $clan2 = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan1->id,
            'user_id' => $this->user->id,
        ]);
        ClanMember::factory()->create([
            'clan_id' => $clan2->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/clans');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_clan_details()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/clans/{$clan->id}");

        $response->assertStatus(200);

        $responseData = $response->json('data');
        $this->assertArrayHasKey('name', $responseData);
        $this->assertEquals($clan->id, $responseData['id']);
        $this->assertEquals($clan->name, $responseData['name']);
    }

    public function test_user_can_update_clan()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/clans/{$clan->id}", [
            'name' => 'UpdatedClanName',
            'tag' => 'UCN',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'UpdatedClanName',
                    'tag' => 'UCN',
                ],
            ]);

        $this->assertDatabaseHas('clans', [
            'id' => $clan->id,
            'name' => 'UpdatedClanName',
            'tag' => 'UCN',
        ]);
    }

    public function test_user_cannot_update_clan_they_dont_own()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $otherUser->id]);

        $response = $this->putJson("/api/clans/{$clan->id}", [
            'name' => 'UpdatedClanName',
            'tag' => 'UCN',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_clan()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/clans/{$clan->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Clan deleted successfully',
            ]);
        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);
    }

    public function test_user_cannot_delete_clan_they_dont_own()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $otherUser->id]);

        $response = $this->deleteJson("/api/clans/{$clan->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    public function test_unauthenticated_user_cannot_delete_clan()
    {
        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);

        $response = $this->deleteJson("/api/clans/{$clan->id}");

        $response->assertStatus(401);
    }

    public function test_deleting_clan_cascades_to_clan_members()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $member1->id,
        ]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $member2->id,
        ]);

        $response = $this->deleteJson("/api/clans/{$clan->id}");

        $response->assertStatus(200);

        // Verify clan is deleted
        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);

        // Verify all clan members are deleted (cascade)
        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $member1->id,
        ]);
        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $member2->id,
        ]);
    }

    public function test_deleting_clan_cascades_to_clan_matches()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $match1 = GameMatch::factory()->create();
        $match2 = GameMatch::factory()->create();

        // Create clan matches via pivot table
        $clan->matches()->attach([$match1->id, $match2->id]);

        // Verify matches exist before deletion
        $this->assertDatabaseHas('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match1->id,
        ]);
        $this->assertDatabaseHas('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match2->id,
        ]);

        $response = $this->deleteJson("/api/clans/{$clan->id}");

        $response->assertStatus(200);

        // Verify clan is deleted
        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);

        // Verify all clan matches are deleted (cascade)
        $this->assertDatabaseMissing('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match1->id,
        ]);
        $this->assertDatabaseMissing('clan_matches', [
            'clan_id' => $clan->id,
            'match_id' => $match2->id,
        ]);

        // Verify the matches themselves are not deleted (they're not owned by the clan)
        $this->assertDatabaseHas('matches', ['id' => $match1->id]);
        $this->assertDatabaseHas('matches', ['id' => $match2->id]);
    }

    public function test_deleting_clan_cascades_to_clan_leaderboards()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $leaderboard1 = ClanLeaderboard::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
        $leaderboard2 = ClanLeaderboard::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        // Verify leaderboards exist before deletion
        $this->assertDatabaseHas('clan_leaderboards', ['id' => $leaderboard1->id]);
        $this->assertDatabaseHas('clan_leaderboards', ['id' => $leaderboard2->id]);

        $response = $this->deleteJson("/api/clans/{$clan->id}");

        $response->assertStatus(200);

        // Verify clan is deleted
        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);

        // Verify all clan leaderboards are deleted (cascade)
        $this->assertDatabaseMissing('clan_leaderboards', ['id' => $leaderboard1->id]);
        $this->assertDatabaseMissing('clan_leaderboards', ['id' => $leaderboard2->id]);
    }

    public function test_deleting_clan_deletes_all_related_data()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        // Create clan members
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $member1->id,
        ]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $member2->id,
        ]);

        // Create clan matches
        $match1 = GameMatch::factory()->create();
        $match2 = GameMatch::factory()->create();
        $clan->matches()->attach([$match1->id, $match2->id]);

        // Create clan leaderboards
        $leaderboard1 = ClanLeaderboard::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
        $leaderboard2 = ClanLeaderboard::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $member1->id,
        ]);

        $response = $this->deleteJson("/api/clans/{$clan->id}");

        $response->assertStatus(200);

        // Verify clan is deleted
        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);

        // Verify all related data is deleted
        $this->assertEquals(0, ClanMember::where('clan_id', $clan->id)->count());
        $this->assertEquals(0, DB::table('clan_matches')->where('clan_id', $clan->id)->count());
        $this->assertEquals(0, ClanLeaderboard::where('clan_id', $clan->id)->count());
    }

    public function test_user_can_regenerate_invite_link()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
        $oldInviteLink = $clan->invite_link;

        $response = $this->postJson("/api/clans/{$clan->id}/regenerate-invite-link");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'invite_link',
            ]);

        $clan->refresh();
        $this->assertNotEquals($oldInviteLink, $clan->invite_link);
    }

    public function test_user_can_join_clan_via_invite_link()
    {
        $owner = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $owner->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/clans/join', [
            'invite_link' => $clan->invite_link,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Joined clan successfully',
            ]);

        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_cannot_join_clan_with_invalid_invite_link()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/clans/join', [
            'invite_link' => 'invalid-uuid',
        ]);

        $response->assertStatus(400);
    }

    public function test_user_cannot_join_clan_they_are_already_in()
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

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/clans/join', [
            'invite_link' => $clan->invite_link,
        ]);

        $response->assertStatus(400);
    }

    public function test_user_can_leave_clan()
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

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/clans/{$clan->id}/leave");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_owner_cannot_leave_clan()
    {
        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/clans/{$clan->id}/leave");

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'leave_failed',
            ]);

        // Verify owner is still a member
        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_owner_can_transfer_ownership_to_member()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $newOwner = User::factory()->create();
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $newOwner->id,
        ]);

        // Verify initial ownership
        $this->assertDatabaseHas('clans', [
            'id' => $clan->id,
            'owned_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/clans/{$clan->id}/transfer-ownership/{$newOwner->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Ownership transferred successfully',
            ]);

        // Verify ownership was transferred
        $this->assertDatabaseHas('clans', [
            'id' => $clan->id,
            'owned_by' => $newOwner->id,
        ]);

        // Verify old owner is still a member
        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        // Verify new owner is still a member
        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $clan->id,
            'user_id' => $newOwner->id,
        ]);
    }

    public function test_owner_cannot_transfer_ownership_to_themselves()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/clans/{$clan->id}/transfer-ownership/{$this->user->id}");

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'transfer_failed',
            ]);

        // Verify ownership was not changed
        $this->assertDatabaseHas('clans', [
            'id' => $clan->id,
            'owned_by' => $this->user->id,
        ]);
    }

    public function test_owner_cannot_transfer_ownership_to_non_member()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $nonMember = User::factory()->create();

        $response = $this->postJson("/api/clans/{$clan->id}/transfer-ownership/{$nonMember->id}");

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'transfer_failed',
            ]);

        // Verify ownership was not changed
        $this->assertDatabaseHas('clans', [
            'id' => $clan->id,
            'owned_by' => $this->user->id,
        ]);
    }

    public function test_non_owner_cannot_transfer_ownership()
    {
        $owner = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $owner->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $owner->id,
        ]);

        $member = User::factory()->create();
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $member->id,
        ]);

        $newOwner = User::factory()->create();
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $newOwner->id,
        ]);

        Sanctum::actingAs($member);

        $response = $this->postJson("/api/clans/{$clan->id}/transfer-ownership/{$newOwner->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized',
            ]);

        // Verify ownership was not changed
        $this->assertDatabaseHas('clans', [
            'id' => $clan->id,
            'owned_by' => $owner->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_transfer_ownership()
    {
        $owner = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $owner->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $owner->id,
        ]);

        $newOwner = User::factory()->create();
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $newOwner->id,
        ]);

        $response = $this->postJson("/api/clans/{$clan->id}/transfer-ownership/{$newOwner->id}");

        $response->assertStatus(401);

        // Verify ownership was not changed
        $this->assertDatabaseHas('clans', [
            'id' => $clan->id,
            'owned_by' => $owner->id,
        ]);
    }

    public function test_transfer_ownership_returns_updated_clan_data()
    {
        Sanctum::actingAs($this->user);

        $clan = Clan::factory()->create(['owned_by' => $this->user->id]);
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $this->user->id,
        ]);

        $newOwner = User::factory()->create();
        ClanMember::factory()->create([
            'clan_id' => $clan->id,
            'user_id' => $newOwner->id,
        ]);

        $response = $this->postJson("/api/clans/{$clan->id}/transfer-ownership/{$newOwner->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'owned_by',
                    'owner' => [
                        'id',
                        'name',
                    ],
                ],
            ]);

        // Verify the returned data shows the new owner
        $responseData = $response->json('data');
        $this->assertEquals($newOwner->id, $responseData['owned_by']);
        $this->assertEquals($newOwner->id, $responseData['owner']['id']);
    }
}
