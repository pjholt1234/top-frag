<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\SanctumAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    private SanctumAuth $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SanctumAuth;
    }

    public function test_middleware_allows_authenticated_user()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $request = Request::create('/api/protected-route', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', json_decode($response->getContent(), true)['message']);
    }

    public function test_middleware_blocks_unauthenticated_user()
    {
        $request = Request::create('/api/protected-route', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });

        $this->assertEquals(401, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthenticated', $responseData['error']);
        $this->assertEquals('Valid Sanctum token is required', $responseData['message']);
    }

    public function test_middleware_logs_authentication_attempts()
    {
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->with('Sanctum authentication request received', \Mockery::any())
            ->once();

        Log::shouldReceive('warning')
            ->with('Unauthenticated Sanctum request', \Mockery::any())
            ->once();

        $request = Request::create('/api/protected-route', 'GET');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });
    }

    public function test_middleware_logs_successful_authentication()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->with('Sanctum authentication request received', \Mockery::any())
            ->once();

        Log::shouldReceive('info')
            ->with('Sanctum authentication response sent', \Mockery::any())
            ->once();

        $request = Request::create('/api/protected-route', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });
    }

    public function test_middleware_preserves_request_headers()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $request = Request::create('/api/protected-route', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->headers->set('X-Custom-Header', 'test-value');

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json([
                'custom_header' => $req->header('X-Custom-Header'),
                'user_id' => $req->user()->id,
            ]);
        });

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('test-value', $responseData['custom_header']);
        $this->assertEquals($user->id, $responseData['user_id']);
    }

    public function test_middleware_handles_different_http_methods()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = Request::create('/api/protected-route', $method);
            $request->setUserResolver(fn () => $user);

            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['method' => $req->method()]);
            });

            $this->assertEquals(200, $response->getStatusCode());
            $responseData = json_decode($response->getContent(), true);
            $this->assertEquals($method, $responseData['method']);
        }
    }

    public function test_middleware_blocks_unauthenticated_user_for_all_methods()
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = Request::create('/api/protected-route', $method);

            $response = $this->middleware->handle($request, function ($req) {
                return response()->json(['message' => 'success']);
            });

            $this->assertEquals(401, $response->getStatusCode());
        }
    }

    public function test_middleware_logs_request_details()
    {
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->with('Sanctum authentication request received', \Mockery::on(function ($data) {
                return isset($data['method']) &&
                    isset($data['url']) &&
                    isset($data['headers']) &&
                    isset($data['ip']) &&
                    isset($data['user_agent']);
            }))
            ->once();

        $request = Request::create('/api/protected-route', 'POST');
        $request->headers->set('User-Agent', 'Test Browser');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });
    }

    public function test_middleware_handles_null_user_gracefully()
    {
        $request = Request::create('/api/protected-route', 'GET');
        $request->setUserResolver(fn () => null);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['message' => 'success']);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_middleware_preserves_response_from_next_middleware()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $expectedResponse = response()->json(['data' => 'test data'], 201);

        $request = Request::create('/api/protected-route', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, function ($req) use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertEquals($expectedResponse->getStatusCode(), $response->getStatusCode());
        $this->assertEquals($expectedResponse->getContent(), $response->getContent());
    }
}
