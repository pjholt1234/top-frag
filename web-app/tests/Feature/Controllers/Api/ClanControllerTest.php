<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'name' => 'Test Clan',
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
                    'name' => 'Test Clan',
                    'tag' => 'TC',
                ],
            ]);

        $this->assertDatabaseHas('clans', [
            'name' => 'Test Clan',
            'tag' => 'TC',
            'owned_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('clan_members', [
            'clan_id' => $response->json('data.id'),
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_create_clan_without_tag()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/clans', [
            'name' => 'Test Clan',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('clans', [
            'name' => 'Test Clan',
            'tag' => null,
        ]);
    }

    public function test_user_cannot_create_clan_without_authentication()
    {
        $response = $this->postJson('/api/clans', [
            'name' => 'Test Clan',
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
            'name' => 'Updated Clan Name',
            'tag' => 'UCN',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Clan Name',
                    'tag' => 'UCN',
                ],
            ]);

        $this->assertDatabaseHas('clans', [
            'id' => $clan->id,
            'name' => 'Updated Clan Name',
            'tag' => 'UCN',
        ]);
    }

    public function test_user_cannot_update_clan_they_dont_own()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $otherUser->id]);

        $response = $this->putJson("/api/clans/{$clan->id}", [
            'name' => 'Updated Clan Name',
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

        $response->assertStatus(200);
        $this->assertDatabaseMissing('clans', ['id' => $clan->id]);
    }

    public function test_user_cannot_delete_clan_they_dont_own()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $otherUser->id]);

        $response = $this->deleteJson("/api/clans/{$clan->id}");

        $response->assertStatus(403);
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
}
