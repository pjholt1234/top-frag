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

class AuthControllerSteamTest extends TestCase
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

    public function test_steam_redirect_returns_redirect_response()
    {
        $redirectResponse = Mockery::mock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
        Socialite::shouldReceive('driver')
            ->with('steam')
            ->andReturnSelf();
        Socialite::shouldReceive('redirect')
            ->andReturn($redirectResponse);

        $response = $this->controller->steamRedirect();

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function test_steam_callback_handles_existing_user()
    {
        $steamUser = Mockery::mock(SocialiteUser::class);
        $steamUser->shouldReceive('getId')->andReturn('76561198012345678');
        $steamUser->shouldReceive('getNickname')->andReturn('TestUser');
        $steamUser->shouldReceive('getEmail')->andReturn('test@example.com');

        Socialite::shouldReceive('driver')
            ->with('steam')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($steamUser);

        // Create existing user
        $user = User::factory()->create(['steam_id' => '76561198012345678']);

        $this->fetchAndStoreFaceITProfileAction->shouldReceive('execute')
            ->once()
            ->with(Mockery::type(User::class));

        $response = $this->controller->steamCallback();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('steam-callback?token=', $response->getTargetUrl());
    }

    public function test_steam_callback_handles_account_linking()
    {
        $steamUser = Mockery::mock(SocialiteUser::class);
        $steamUser->shouldReceive('getId')->andReturn('76561198012345678');
        $steamUser->shouldReceive('getNickname')->andReturn('TestUser');
        $steamUser->shouldReceive('getEmail')->andReturn('test@example.com');

        Socialite::shouldReceive('driver')
            ->with('steam')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($steamUser);

        // Create user with link hash
        $user = User::factory()->create(['steam_link_hash' => 'test-hash']);

        // Mock request with link parameter
        $request = Request::create('/api/auth/steam/callback', 'GET', ['link' => 'test-hash']);
        $this->app->instance('request', $request);

        $this->fetchAndStoreFaceITProfileAction->shouldReceive('execute')
            ->once()
            ->with(Mockery::type(User::class));

        $response = $this->controller->steamCallback();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('steam-callback?success=true', $response->getTargetUrl());
    }

    public function test_steam_callback_handles_new_user_creation()
    {
        $steamUser = Mockery::mock(SocialiteUser::class);
        $steamUser->shouldReceive('getId')->andReturn('76561198012345678');
        $steamUser->shouldReceive('getNickname')->andReturn('TestUser');
        $steamUser->shouldReceive('getEmail')->andReturn('test@example.com');

        Socialite::shouldReceive('driver')
            ->with('steam')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($steamUser);

        $this->fetchAndStoreFaceITProfileAction->shouldReceive('execute')
            ->once()
            ->with(Mockery::type(User::class));

        $response = $this->controller->steamCallback();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('steam-callback?token=', $response->getTargetUrl());

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'steam_id' => '76561198012345678',
            'name' => 'TestUser',
        ]);
    }

    public function test_steam_callback_handles_exception()
    {
        Socialite::shouldReceive('driver')
            ->with('steam')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andThrow(new \Exception('Steam authentication failed'));

        $response = $this->controller->steamCallback();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('steam-callback?error=authentication_failed', $response->getTargetUrl());
    }

    public function test_link_steam_returns_redirect_url()
    {
        $user = User::factory()->create();
        $request = Request::create('/api/auth/steam/link', 'POST');
        $request->setUserResolver(fn () => $user);

        $redirectResponse = Mockery::mock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
        $redirectResponse->shouldReceive('getTargetUrl')->andReturn('https://steamcommunity.com/openid/login');

        Socialite::shouldReceive('driver')
            ->with('steam')
            ->andReturnSelf();
        Socialite::shouldReceive('redirect')
            ->andReturn($redirectResponse);

        $response = $this->controller->linkSteam($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('steam_redirect_url', $responseData);
    }

    public function test_link_steam_returns_401_for_unauthenticated_user()
    {
        $request = Request::create('/api/auth/steam/link', 'POST');
        $request->setUserResolver(fn () => null);

        $response = $this->controller->linkSteam($request);

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User not authenticated', $responseData['message']);
    }

    public function test_link_steam_returns_409_for_user_with_existing_steam_id()
    {
        $user = User::factory()->create(['steam_id' => '76561198012345678']);
        $request = Request::create('/api/auth/steam/link', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->linkSteam($request);

        $this->assertEquals(409, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User already has a Steam account linked', $responseData['message']);
        $this->assertEquals('steam_already_linked', $responseData['error']);
    }

    public function test_unlink_steam_removes_steam_id()
    {
        $user = User::factory()->create(['steam_id' => '76561198012345678']);
        $request = Request::create('/api/auth/steam/unlink', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->unlinkSteam($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Steam account unlinked successfully', $responseData['message']);

        // Verify steam_id was removed
        $user->refresh();
        $this->assertNull($user->steam_id);
    }

    public function test_unlink_steam_returns_401_for_unauthenticated_user()
    {
        $request = Request::create('/api/auth/steam/unlink', 'POST');
        $request->setUserResolver(fn () => null);

        $response = $this->controller->unlinkSteam($request);

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User not authenticated', $responseData['message']);
    }

    public function test_unlink_steam_returns_400_for_user_without_steam_id()
    {
        $user = User::factory()->create(['steam_id' => null]);
        $request = Request::create('/api/auth/steam/unlink', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->unlinkSteam($request);

        $this->assertEquals(400, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('No Steam account linked to this user', $responseData['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
