<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerDiscordTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_link_discord_account()
    {
        // Set Discord config for test
        config(['services.discord.client_id' => 'test-client-id']);
        config(['services.discord.client_secret' => 'test-client-secret']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Mock Socialite to avoid actual OAuth redirect
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $redirectResponse = \Mockery::mock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
            $redirectResponse->shouldReceive('getTargetUrl')
                ->andReturn('https://discord.com/api/oauth2/authorize');

            $socialiteDriver = \Mockery::mock();
            $socialiteDriver->shouldReceive('redirect')
                ->andReturn($redirectResponse);

            $mock->shouldReceive('driver')
                ->with('discord')
                ->andReturn($socialiteDriver);
        });

        $response = $this->postJson('/api/auth/discord/link');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'discord_redirect_url',
            ])
            ->assertJson([
                'message' => 'Redirect to Discord for account linking',
            ]);
    }

    public function test_unauthenticated_user_cannot_link_discord_account()
    {
        $response = $this->postJson('/api/auth/discord/link');

        $response->assertStatus(401);
    }

    public function test_user_with_existing_discord_id_cannot_link_again()
    {
        $user = User::factory()->create(['discord_id' => '123456789012345678']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/discord/link');

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'User already has a Discord account linked',
                'error' => 'discord_already_linked',
            ]);
    }

    public function test_authenticated_user_can_unlink_discord_account()
    {
        $user = User::factory()->create(['discord_id' => '123456789012345678']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/discord/unlink');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Discord account unlinked successfully',
            ]);

        // Verify discord_id was removed
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'discord_id' => null,
        ]);
    }

    public function test_unauthenticated_user_cannot_unlink_discord_account()
    {
        $response = $this->postJson('/api/auth/discord/unlink');

        $response->assertStatus(401);
    }

    public function test_user_without_discord_id_cannot_unlink()
    {
        $user = User::factory()->create(['discord_id' => null]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/discord/unlink');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'No Discord account linked to this user',
                'error' => 'no_discord_linked',
            ]);
    }

    public function test_discord_redirect_endpoint_returns_redirect()
    {
        // Mock Socialite to avoid actual OAuth redirect
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $redirectResponse = \Mockery::mock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
            $redirectResponse->shouldReceive('getStatusCode')->andReturn(302);
            $redirectResponse->shouldReceive('getTargetUrl')->andReturn('https://discord.com/api/oauth2/authorize');

            $socialiteDriver = \Mockery::mock();
            $socialiteDriver->shouldReceive('redirect')
                ->andReturn($redirectResponse);

            $mock->shouldReceive('driver')
                ->with('discord')
                ->andReturn($socialiteDriver);
        });

        $response = $this->get('/api/auth/discord/redirect');

        $response->assertStatus(302); // Redirect response
    }

    public function test_discord_callback_handles_linking_flow()
    {
        $user = User::factory()->create(['discord_link_hash' => 'test-hash']);

        // Mock Socialite to return a Discord user
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $discordUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
            $discordUser->shouldReceive('getId')->andReturn('123456789012345678');
            $discordUser->shouldReceive('getName')->andReturn('TestUser');
            $discordUser->shouldReceive('getNickname')->andReturn('TestUser');
            $discordUser->shouldReceive('getEmail')->andReturn('test@example.com');

            $socialiteDriver = \Mockery::mock();
            $socialiteDriver->shouldReceive('user')
                ->andReturn($discordUser);

            $mock->shouldReceive('driver')
                ->with('discord')
                ->andReturn($socialiteDriver);
        });

        // Set link hash in session (instead of query parameter)
        session(['discord_link_hash' => 'test-hash']);

        $response = $this->get('/api/auth/discord/callback');

        $response->assertStatus(302); // Redirect response
        $this->assertStringContainsString('discord-callback?success=true', $response->getTargetUrl());

        // Verify discord_id was set
        $user->refresh();
        $this->assertEquals('123456789012345678', $user->discord_id);
    }

    public function test_discord_callback_handles_new_user_creation()
    {
        // Mock Socialite to return a Discord user
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $discordUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
            $discordUser->shouldReceive('getId')->andReturn('123456789012345678');
            $discordUser->shouldReceive('getName')->andReturn('TestUser');
            $discordUser->shouldReceive('getNickname')->andReturn('TestUser');
            $discordUser->shouldReceive('getEmail')->andReturn('test@example.com');

            $socialiteDriver = \Mockery::mock();
            $socialiteDriver->shouldReceive('user')
                ->andReturn($discordUser);

            $mock->shouldReceive('driver')
                ->with('discord')
                ->andReturn($socialiteDriver);
        });

        $response = $this->get('/api/auth/discord/callback');

        $response->assertStatus(302); // Redirect response
        $this->assertStringContainsString('discord-callback?token=', $response->getTargetUrl());

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'discord_id' => '123456789012345678',
            'name' => 'TestUser',
        ]);
    }

    public function test_discord_callback_handles_existing_user_login()
    {
        $user = User::factory()->create(['discord_id' => '123456789012345678']);

        // Mock Socialite to return a Discord user
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $discordUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
            $discordUser->shouldReceive('getId')->andReturn('123456789012345678');
            $discordUser->shouldReceive('getName')->andReturn('TestUser');
            $discordUser->shouldReceive('getNickname')->andReturn('TestUser');
            $discordUser->shouldReceive('getEmail')->andReturn('test@example.com');

            $socialiteDriver = \Mockery::mock();
            $socialiteDriver->shouldReceive('user')
                ->andReturn($discordUser);

            $mock->shouldReceive('driver')
                ->with('discord')
                ->andReturn($socialiteDriver);
        });

        $response = $this->get('/api/auth/discord/callback');

        $response->assertStatus(302); // Redirect response
        $this->assertStringContainsString('discord-callback?token=', $response->getTargetUrl());
    }

    public function test_discord_callback_handles_invalid_link_hash()
    {
        // Mock Socialite to return a Discord user
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $discordUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
            $discordUser->shouldReceive('getId')->andReturn('123456789012345678');
            $discordUser->shouldReceive('getName')->andReturn('TestUser');
            $discordUser->shouldReceive('getNickname')->andReturn('TestUser');
            $discordUser->shouldReceive('getEmail')->andReturn('test@example.com');

            $socialiteDriver = \Mockery::mock();
            $socialiteDriver->shouldReceive('user')
                ->andReturn($discordUser);

            $mock->shouldReceive('driver')
                ->with('discord')
                ->andReturn($socialiteDriver);
        });

        // Set invalid link hash in session
        session(['discord_link_hash' => 'invalid-hash']);

        $response = $this->get('/api/auth/discord/callback');

        $response->assertStatus(302); // Redirect response
        $this->assertStringContainsString('discord-callback?error=user_not_found', $response->getTargetUrl());
    }

    public function test_discord_callback_handles_already_linked_discord_id()
    {
        $existingUser = User::factory()->create(['discord_id' => '123456789012345678']);
        $linkingUser = User::factory()->create(['discord_link_hash' => 'test-hash']);

        // Mock Socialite to return a Discord user
        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
            $discordUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
            $discordUser->shouldReceive('getId')->andReturn('123456789012345678');
            $discordUser->shouldReceive('getName')->andReturn('TestUser');
            $discordUser->shouldReceive('getNickname')->andReturn('TestUser');
            $discordUser->shouldReceive('getEmail')->andReturn('test@example.com');

            $socialiteDriver = \Mockery::mock();
            $socialiteDriver->shouldReceive('user')
                ->andReturn($discordUser);

            $mock->shouldReceive('driver')
                ->with('discord')
                ->andReturn($socialiteDriver);
        });

        // Set link hash in session
        session(['discord_link_hash' => 'test-hash']);

        $response = $this->get('/api/auth/discord/callback');

        $response->assertStatus(302); // Redirect response
        $this->assertStringContainsString('discord-callback?error=discord_already_linked', $response->getTargetUrl());

        // Verify linking user's discord_id was not set
        $linkingUser->refresh();
        $this->assertNull($linkingUser->discord_id);
    }
}
