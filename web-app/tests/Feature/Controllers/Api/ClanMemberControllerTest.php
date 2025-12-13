<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClanMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->clan = Clan::factory()->create(['owned_by' => $this->owner->id]);

        // Ensure owner is a member
        ClanMember::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $this->owner->id,
        ]);
        ClanMember::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_user_can_list_clan_members()
    {
        Sanctum::actingAs($this->owner);

        // Refresh to ensure relationships are loaded
        $this->clan->refresh();

        // Verify members exist
        $this->assertEquals(2, $this->clan->members()->count());

        $response = $this->getJson("/api/clans/{$this->clan->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_owner_can_remove_member()
    {
        Sanctum::actingAs($this->owner);

        // Ensure clan is fresh from database
        $this->clan->refresh();
        $this->assertTrue($this->clan->isOwner($this->owner), 'Owner check should pass');

        $response = $this->deleteJson("/api/clans/{$this->clan->id}/members/{$this->member->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('clan_members', [
            'clan_id' => $this->clan->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_non_owner_cannot_remove_member()
    {
        Sanctum::actingAs($this->member);

        $otherMember = User::factory()->create();
        ClanMember::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $otherMember->id,
        ]);

        $response = $this->deleteJson("/api/clans/{$this->clan->id}/members/{$otherMember->id}");

        $response->assertStatus(403);
    }

    public function test_owner_cannot_remove_themselves()
    {
        Sanctum::actingAs($this->owner);

        // Ensure clan is fresh from database
        $this->clan->refresh();
        $this->assertTrue($this->clan->isOwner($this->owner), 'Owner check should pass');

        $response = $this->deleteJson("/api/clans/{$this->clan->id}/members/{$this->owner->id}");

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'cannot_remove_owner',
            ]);
    }
}
