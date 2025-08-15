<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private AuthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AuthController;
    }

    public function test_register_creates_user_with_hashed_password()
    {
        $requestData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $request = Request::create('/api/auth/register', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $response = $this->controller->register($request);

        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User registered successfully', $responseData['message']);
        $this->assertEquals('John Doe', $responseData['user']['name']);
        $this->assertEquals('john@example.com', $responseData['user']['email']);
        $this->assertArrayHasKey('token', $responseData);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_register_validates_required_fields()
    {
        $requestData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ];

        $request = Request::create('/api/auth/register', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->controller->register($request);
    }

    public function test_register_validates_unique_email()
    {
        User::factory()->create(['email' => 'john@example.com']);

        $requestData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $request = Request::create('/api/auth/register', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->controller->register($request);
    }

    public function test_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $requestData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $request = Request::create('/api/auth/login', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $response = $this->controller->login($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Login successful', $responseData['message']);
        $this->assertEquals($user->id, $responseData['user']['id']);
        $this->assertEquals($user->name, $responseData['user']['name']);
        $this->assertEquals($user->email, $responseData['user']['email']);
        $this->assertArrayHasKey('token', $responseData);
    }

    public function test_login_with_invalid_credentials_throws_exception()
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $requestData = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ];

        $request = Request::create('/api/auth/login', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(ValidationException::class);

        $this->controller->login($request);
    }

    public function test_login_with_nonexistent_email_throws_exception()
    {
        $requestData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $request = Request::create('/api/auth/login', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(ValidationException::class);

        $this->controller->login($request);
    }

    public function test_login_validates_required_fields()
    {
        $requestData = [
            'email' => 'invalid-email',
            'password' => '',
        ];

        $request = Request::create('/api/auth/login', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->controller->login($request);
    }

    public function test_logout_deletes_current_token()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a token
        $token = $user->createToken('auth_token');

        $request = Request::create('/api/auth/logout', 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->logout($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Successfully logged out', $responseData['message']);

        // Verify token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_user_returns_authenticated_user_data()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        Sanctum::actingAs($user);

        $request = Request::create('/api/auth/user', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->user($request);

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals($user->id, $responseData['user']['id']);
        $this->assertEquals($user->name, $responseData['user']['name']);
        $this->assertEquals($user->email, $responseData['user']['email']);
    }

    public function test_register_creates_personal_access_token()
    {
        $requestData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $request = Request::create('/api/auth/register', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $response = $this->controller->register($request);

        $responseData = json_decode($response->getContent(), true);
        $token = $responseData['token'];

        // Verify token exists in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'auth_token',
        ]);

        // Verify token is valid
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->tokens()->where('name', 'auth_token')->exists());
    }

    public function test_login_creates_new_token_each_time()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $requestData = [
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        // First login
        $request1 = Request::create('/api/auth/login', 'POST', $requestData);
        $request1->setLaravelSession(app('session.store'));
        $response1 = $this->controller->login($request1);
        $token1 = json_decode($response1->getContent(), true)['token'];

        // Second login
        $request2 = Request::create('/api/auth/login', 'POST', $requestData);
        $request2->setLaravelSession(app('session.store'));
        $response2 = $this->controller->login($request2);
        $token2 = json_decode($response2->getContent(), true)['token'];

        // Tokens should be different
        $this->assertNotEquals($token1, $token2);

        // Both tokens should exist in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'auth_token',
            'tokenable_id' => $user->id,
        ]);

        $tokenCount = $user->tokens()->where('name', 'auth_token')->count();
        $this->assertEquals(2, $tokenCount);
    }

    public function test_password_confirmation_validation()
    {
        $requestData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
        ];

        $request = Request::create('/api/auth/register', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->controller->register($request);
    }

    public function test_email_format_validation()
    {
        $requestData = [
            'name' => 'John Doe',
            'email' => 'invalid-email-format',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $request = Request::create('/api/auth/register', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->controller->register($request);
    }

    public function test_password_minimum_length_validation()
    {
        $requestData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        $request = Request::create('/api/auth/register', 'POST', $requestData);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->controller->register($request);
    }
}
