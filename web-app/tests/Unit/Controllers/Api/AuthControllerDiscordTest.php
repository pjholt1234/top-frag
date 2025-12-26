<?php

namespace Tests\Unit\Controllers\Api;

use App\Actions\FetchAndStoreFaceITProfileAction;
use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use App\Services\Integrations\Steam\SteamAPIConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthControllerDiscordTest extends TestCase
{
    use RefreshDatabase;

    private AuthController $controller;

    private $fetchAndStoreFaceITProfileAction;

    protected function setUp(): void
    {
        parent::setUp();
        $steamApiConnector = Mockery::mock(SteamAPIConnector::class);
        $this->fetchAndStoreFaceITProfileAction = Mockery::mock(FetchAndStoreFaceITProfileAction::class);
        $this->controller = new AuthController($steamApiConnector, $this->fetchAndStoreFaceITProfileAction);
    }

    public function test_discord_redirect_returns_redirect_response()
    {
        $redirectResponse = Mockery::mock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
        Socialite::shouldReceive('driver')
            ->with('discord')
            ->andReturnSelf();
        Socialite::shouldReceive('redirect')
            ->andReturn($redirectResponse);

        $response = $this->controller->discordRedirect();

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function test_discord_callback_handles_existing_user()
    {
        $discordUser = Mockery::mock(SocialiteUser::class);
        $discordUser->shouldReceive('getId')->andReturn('123456789012345678');
        $discordUser->shouldReceive('getName')->andReturn('TestUser');
        $discordUser->shouldReceive('getNickname')->andReturn('TestUser');
        $discordUser->shouldReceive('getEmail')->andReturn('test@example.com');

        Socialite::shouldReceive('driver')
            ->with('discord')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($discordUser);

        // Create existing user
        $user = User::factory()->create(['discord_id' => '123456789012345678']);

        $response = $this->controller->discordCallback();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('discord-callback?token=', $response->getTargetUrl());
    }

    public function test_discord_callback_handles_account_linking()
    {
        $discordUser = Mockery::mock(SocialiteUser::class);
        $discordUser->shouldReceive('getId')->andReturn('123456789012345678');
        $discordUser->shouldReceive('getName')->andReturn('TestUser');
        $discordUser->shouldReceive('getNickname')->andReturn('TestUser');
        $discordUser->shouldReceive('getEmail')->andReturn('test@example.com');

        Socialite::shouldReceive('driver')
            ->with('discord')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($discordUser);

        // Create user with link hash
        $user = User::factory()->create(['discord_link_hash' => 'test-hash']);

        // Set link hash in session (instead of query parameter)
        session(['discord_link_hash' => 'test-hash']);

        $response = $this->controller->discordCallback();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('discord-callback?success=true', $response->getTargetUrl());

        // Verify discord_id was set
        $user->refresh();
        $this->assertEquals('123456789012345678', $user->discord_id);
    }

    public function test_discord_callback_handles_new_user_creation()
    {
        $discordUser = Mockery::mock(SocialiteUser::class);
        $discordUser->shouldReceive('getId')->andReturn('123456789012345678');
        $discordUser->shouldReceive('getName')->andReturn('TestUser');
        $discordUser->shouldReceive('getNickname')->andReturn('TestUser');
        $discordUser->shouldReceive('getEmail')->andReturn('test@example.com');

        Socialite::shouldReceive('driver')
            ->with('discord')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($discordUser);

        $response = $this->controller->discordCallback();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('discord-callback?token=', $response->getTargetUrl());

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'discord_id' => '123456789012345678',
            'name' => 'TestUser',
        ]);
    }

    public function test_discord_callback_handles_exception()
    {
        Socialite::shouldReceive('driver')
            ->with('discord')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andThrow(new \Exception('Discord authentication failed'));

        $response = $this->controller->discordCallback();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('discord-callback?error=authentication_failed', $response->getTargetUrl());
    }

    public function test_link_discord_returns_redirect_url()
    {
        // Set Discord config for test
        config(['services.discord.client_id' => 'test-client-id']);
        config(['services.discord.client_secret' => 'test-client-secret']);

        $user = User::factory()->create();
        $request = Request::create('/api/auth/discord/link', 'POST');
        $request->setUserResolver(fn () => $user);

        // Start session for storing link hash
        $this->startSession();

        $redirectResponse = Mockery::mock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
        $redirectResponse->shouldReceive('getTargetUrl')->andReturn('https://discord.com/api/oauth2/authorize');

        Socialite::shouldReceive('driver')
            ->with('discord')
            ->andReturnSelf();
        Socialite::shouldReceive('redirect')
            ->andReturn($redirectResponse);

        $response = $this->controller->linkDiscord($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('discord_redirect_url', $responseData);
    }

    public function test_link_discord_returns_401_for_unauthenticated_user()
    {
        $request = Request::create('/api/auth/discord/link', 'POST');
        $request->setUserResolver(fn () => null);

        $response = $this->controller->linkDiscord($request);

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User not authenticated', $responseData['message']);
    }

    public function test_link_discord_returns_409_for_user_with_existing_discord_id()
    {
        $user = User::factory()->create(['discord_id' => '123456789012345678']);
        $request = Request::create('/api/auth/discord/link', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->linkDiscord($request);

        $this->assertEquals(409, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User already has a Discord account linked', $responseData['message']);
        $this->assertEquals('discord_already_linked', $responseData['error']);
    }

    public function test_unlink_discord_removes_discord_id()
    {
        $user = User::factory()->create(['discord_id' => '123456789012345678']);
        $request = Request::create('/api/auth/discord/unlink', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->unlinkDiscord($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Discord account unlinked successfully', $responseData['message']);

        // Verify discord_id was removed
        $user->refresh();
        $this->assertNull($user->discord_id);
    }

    public function test_unlink_discord_returns_401_for_unauthenticated_user()
    {
        $request = Request::create('/api/auth/discord/unlink', 'POST');
        $request->setUserResolver(fn () => null);

        $response = $this->controller->unlinkDiscord($request);

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User not authenticated', $responseData['message']);
    }

    public function test_unlink_discord_returns_400_for_user_without_discord_id()
    {
        $user = User::factory()->create(['discord_id' => null]);
        $request = Request::create('/api/auth/discord/unlink', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->unlinkDiscord($request);

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('No Discord account linked to this user', $responseData['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
