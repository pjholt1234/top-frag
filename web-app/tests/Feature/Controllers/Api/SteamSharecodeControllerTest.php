<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SteamSharecodeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_store_sharecode(): void
    {
        $response = $this->postJson('/api/steam-sharecode', [
            'steam_sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'steam_game_auth_code' => 'AAAA-AAAAA-AAAA',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_store_valid_sharecode(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/steam-sharecode', [
            'steam_sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'steam_game_auth_code' => 'AAAA-AAAAA-AAAA',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Steam sharecode saved successfully',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'steam_sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'steam_game_auth_code' => 'AAAA-AAAAA-AAAA',
        ]);

        $this->assertNotNull($user->fresh()->steam_sharecode_added_at);
    }

    public function test_store_sharecode_validates_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/steam-sharecode', [
            'steam_sharecode' => 'invalid-sharecode',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['steam_sharecode']);
    }

    public function test_store_sharecode_requires_sharecode(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/steam-sharecode', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['steam_sharecode']);
    }

    public function test_authenticated_user_can_check_sharecode_status(): void
    {
        $user = User::factory()->create([
            'steam_sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'steam_sharecode_added_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/steam-sharecode/has-sharecode');

        $response->assertStatus(200);
        $response->assertJson([
            'has_sharecode' => true,
        ]);
        $this->assertNotNull($response->json('steam_sharecode_added_at'));
    }

    public function test_has_sharecode_returns_false_when_no_sharecode(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/steam-sharecode/has-sharecode');

        $response->assertStatus(200);
        $response->assertJson([
            'has_sharecode' => false,
        ]);
        $this->assertNull($response->json('steam_sharecode_added_at'));
    }

    public function test_unauthenticated_user_cannot_check_sharecode_status(): void
    {
        $response = $this->getJson('/api/steam-sharecode/has-sharecode');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_remove_sharecode(): void
    {
        $user = User::factory()->create([
            'steam_sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'steam_sharecode_added_at' => now(),
            'steam_match_processing_enabled' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/steam-sharecode');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Steam sharecode removed successfully',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'steam_sharecode' => null,
            'steam_sharecode_added_at' => null,
            'steam_match_processing_enabled' => false,
        ]);
    }

    public function test_remove_sharecode_fails_when_no_sharecode(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/steam-sharecode');

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'no_sharecode',
        ]);
    }

    public function test_unauthenticated_user_cannot_remove_sharecode(): void
    {
        $response = $this->deleteJson('/api/steam-sharecode');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_toggle_processing(): void
    {
        $user = User::factory()->create([
            'steam_sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'steam_game_auth_code' => 'AAAA-AAAAA-AAAA',
            'steam_match_processing_enabled' => false,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/steam-sharecode/toggle-processing');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Steam match processing enabled',
            'steam_match_processing_enabled' => true,
        ]);

        $this->assertTrue($user->fresh()->steam_match_processing_enabled);
    }

    public function test_toggle_processing_fails_without_sharecode(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/steam-sharecode/toggle-processing');

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'incomplete_setup',
        ]);
    }

    public function test_unauthenticated_user_cannot_toggle_processing(): void
    {
        $response = $this->postJson('/api/steam-sharecode/toggle-processing');

        $response->assertStatus(401);
    }
}
