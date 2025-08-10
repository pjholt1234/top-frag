<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Http\Requests\DemoParserEventRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class DemoParserEventRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the route parameters for testing
        Route::post('/api/job/{jobId}/event/{eventName}', function () {
            return response()->json(['success' => true]);
        })->name('demo.parser.event');
    }

    public function test_round_event_validation_passes_with_valid_data()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/round',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'tick_timestamp' => 12345,
                        'event_type' => 'start',
                        'winner' => null,
                        'duration' => null,
                    ],
                    [
                        'round_number' => 1,
                        'tick_timestamp' => 67890,
                        'event_type' => 'end',
                        'winner' => 'CT',
                        'duration' => 120,
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'round');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_gunfight_event_validation_passes_with_valid_data()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/gunfight',
            'POST',
            [
                'batch_index' => 1,
                'is_last' => false,
                'total_batches' => 3,
                'data' => [
                    [
                        'round_number' => 1,
                        'round_time' => 30,
                        'tick_timestamp' => 12345,
                        'player_1_steam_id' => 'steam_123',
                        'player_2_steam_id' => 'steam_456',
                        'player_1_hp_start' => 100,
                        'player_2_hp_start' => 100,
                        'player_1_armor' => 100,
                        'player_2_armor' => 0,
                        'player_1_flashed' => false,
                        'player_2_flashed' => false,
                        'player_1_weapon' => 'ak47',
                        'player_2_weapon' => 'm4a1',
                        'player_1_equipment_value' => 2700,
                        'player_2_equipment_value' => 3100,
                        'player_1_position' => ['x' => 100.5, 'y' => 200.3, 'z' => 50.0],
                        'player_2_position' => ['x' => 150.2, 'y' => 180.7, 'z' => 50.0],
                        'distance' => 52.3,
                        'headshot' => true,
                        'wallbang' => false,
                        'penetrated_objects' => 0,
                        'victor_steam_id' => 'steam_123',
                        'damage_dealt' => 100,
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'gunfight');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_grenade_event_validation_passes_with_valid_data()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/grenade',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'round_time' => 15,
                        'tick_timestamp' => 12345,
                        'player_steam_id' => 'steam_123',
                        'grenade_type' => 'hegrenade',
                        'player_position' => ['x' => 100.5, 'y' => 200.3, 'z' => 50.0],
                        'player_aim' => ['x' => 0.8, 'y' => 0.6, 'z' => 0.0],
                        'grenade_final_position' => ['x' => 150.2, 'y' => 180.7, 'z' => 50.0],
                        'damage_dealt' => 45,
                        'flash_duration' => null,
                        'affected_players' => [],
                        'throw_type' => 'utility',
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'grenade');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_damage_event_validation_passes_with_valid_data()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/damage',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'round_time' => 25,
                        'tick_timestamp' => 12345,
                        'attacker_steam_id' => 'steam_123',
                        'victim_steam_id' => 'steam_456',
                        'damage' => 35,
                        'armor_damage' => 15,
                        'health_damage' => 20,
                        'headshot' => false,
                        'weapon' => 'ak47',
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'damage');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_validation_fails_without_data()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/round',
            'POST',
            []
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'round');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('data'));
    }

    public function test_validation_fails_with_invalid_round_event_type()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/round',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'tick_timestamp' => 12345,
                        'event_type' => 'invalid_type',
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'round');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('data.0.event_type'));
    }

    public function test_validation_fails_with_invalid_grenade_type()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/grenade',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'round_time' => 15,
                        'tick_timestamp' => 12345,
                        'player_steam_id' => 'steam_123',
                        'grenade_type' => 'invalid_grenade',
                        'player_position' => ['x' => 100.5, 'y' => 200.3, 'z' => 50.0],
                        'player_aim' => ['x' => 0.8, 'y' => 0.6, 'z' => 0.0],
                        'throw_type' => 'utility',
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'grenade');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('data.0.grenade_type'));
    }

    public function test_validation_fails_with_invalid_throw_type()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/grenade',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'round_time' => 15,
                        'tick_timestamp' => 12345,
                        'player_steam_id' => 'steam_123',
                        'grenade_type' => 'hegrenade',
                        'player_position' => ['x' => 100.5, 'y' => 200.3, 'z' => 50.0],
                        'player_aim' => ['x' => 0.8, 'y' => 0.6, 'z' => 0.0],
                        'throw_type' => 'invalid_throw',
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'grenade');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('data.0.throw_type'));
    }

    public function test_validation_fails_with_invalid_winner()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/round',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'tick_timestamp' => 12345,
                        'event_type' => 'end',
                        'winner' => 'INVALID',
                        'duration' => 120,
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'round');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('data.0.winner'));
    }

    public function test_validation_fails_with_invalid_duration()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/round',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'tick_timestamp' => 12345,
                        'event_type' => 'end',
                        'winner' => 'CT',
                        'duration' => 400, // Exceeds max of 300
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'round');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('data.0.duration'));
    }

    public function test_gunfight_event_requires_batch_fields()
    {
        $request = DemoParserEventRequest::create(
            '/api/job/test-123/event/gunfight',
            'POST',
            [
                'data' => [
                    [
                        'round_number' => 1,
                        'round_time' => 30,
                        'tick_timestamp' => 12345,
                        'player_1_steam_id' => 'steam_123',
                        'player_2_steam_id' => 'steam_456',
                        'player_1_hp_start' => 100,
                        'player_2_hp_start' => 100,
                        'player_1_armor' => 100,
                        'player_2_armor' => 0,
                        'player_1_flashed' => false,
                        'player_2_flashed' => false,
                        'player_1_weapon' => 'ak47',
                        'player_2_weapon' => 'm4a1',
                        'player_1_equipment_value' => 2700,
                        'player_2_equipment_value' => 3100,
                        'player_1_position' => ['x' => 100.5, 'y' => 200.3, 'z' => 50.0],
                        'player_2_position' => ['x' => 150.2, 'y' => 180.7, 'z' => 50.0],
                        'distance' => 52.3,
                        'headshot' => true,
                        'wallbang' => false,
                        'penetrated_objects' => 0,
                        'victor_steam_id' => 'steam_123',
                        'damage_dealt' => 100,
                    ],
                ],
            ]
        );

        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('POST', '/api/job/{jobId}/event/{eventName}', []);
            $route->setParameter('eventName', 'gunfight');
            return $route;
        });

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('batch_index'));
        $this->assertTrue($validator->errors()->has('is_last'));
        $this->assertTrue($validator->errors()->has('total_batches'));
    }
}
