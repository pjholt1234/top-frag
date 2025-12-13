<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClanMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Clan $clan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->clan = Clan::factory()->create(['owned_by' => $this->user->id]);

        ClanMember::factory()->create([
            'clan_id' => $this->clan->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_list_clan_matches()
    {
        Sanctum::actingAs($this->user);

        $match1 = GameMatch::factory()->create();
        $match2 = GameMatch::factory()->create();

        $this->clan->matches()->attach([$match1->id, $match2->id]);

        // Ensure data is committed
        $this->clan->refresh();

        // Verify matches are attached
        $this->assertEquals(2, $this->clan->matches()->count());

        $response = $this->getJson("/api/clans/{$this->clan->id}/matches");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_clan_matches_are_paginated()
    {
        Sanctum::actingAs($this->user);

        $matches = GameMatch::factory()->count(15)->create();
        $matchIds = $matches->pluck('id')->toArray();
        $this->clan->matches()->attach($matchIds);

        $response = $this->getJson("/api/clans/{$this->clan->id}/matches?per_page=10&page=1");

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'pagination' => [
                    'per_page' => 10,
                    'current_page' => 1,
                    'total' => 15,
                ],
            ]);
    }
}
