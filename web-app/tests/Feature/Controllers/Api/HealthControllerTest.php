<?php

namespace Tests\Feature\Controllers\Api;

use App\Services\ParserServiceConnector;
use App\Services\SteamAPIConnector;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_health_check_returns_healthy_when_all_services_healthy(): void
    {
        $steamApiService = $this->createMock(SteamAPIConnector::class);
        $parserServiceConnector = $this->createMock(ParserServiceConnector::class);

        $steamApiService->expects($this->once())
            ->method('checkServiceHealth')
            ->willReturn(true);

        $parserServiceConnector->expects($this->once())
            ->method('checkServiceHealth');

        $this->app->instance(SteamAPIConnector::class, $steamApiService);
        $this->app->instance(ParserServiceConnector::class, $parserServiceConnector);

        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
        ]);
        $response->assertJsonStructure([
            'status',
            'message',
            'timestamp',
            'services' => [
                'steam_api' => [
                    'status',
                    'message',
                ],
                'parser_service' => [
                    'status',
                    'message',
                ],
            ],
        ]);
    }

    public function test_health_check_returns_degraded_when_steam_api_unhealthy(): void
    {
        $steamApiService = $this->createMock(SteamAPIConnector::class);
        $parserServiceConnector = $this->createMock(ParserServiceConnector::class);

        $steamApiService->expects($this->once())
            ->method('checkServiceHealth')
            ->willReturn(false);

        $parserServiceConnector->expects($this->once())
            ->method('checkServiceHealth');

        $this->app->instance(SteamAPIConnector::class, $steamApiService);
        $this->app->instance(ParserServiceConnector::class, $parserServiceConnector);

        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'degraded',
        ]);
        $response->assertJsonPath('services.steam_api.status', 'unhealthy');
        $response->assertJsonPath('services.parser_service.status', 'healthy');
    }

    public function test_health_check_returns_degraded_when_parser_service_unhealthy(): void
    {
        $steamApiService = $this->createMock(SteamAPIConnector::class);
        $parserServiceConnector = $this->createMock(ParserServiceConnector::class);

        $steamApiService->expects($this->once())
            ->method('checkServiceHealth')
            ->willReturn(true);

        $parserServiceConnector->expects($this->once())
            ->method('checkServiceHealth')
            ->willThrowException(new \Exception('Parser service unavailable'));

        $this->app->instance(SteamAPIConnector::class, $steamApiService);
        $this->app->instance(ParserServiceConnector::class, $parserServiceConnector);

        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'degraded',
        ]);
        $response->assertJsonPath('services.steam_api.status', 'healthy');
        $response->assertJsonPath('services.parser_service.status', 'unhealthy');
    }
}
