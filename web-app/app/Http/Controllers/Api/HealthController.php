<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ParserServiceConnector;
use App\Services\SteamApiService;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function check(Request $request)
    {
        $health = [
            'status' => 'healthy',
            'message' => config('messaging.healthy'),
            'timestamp' => now()->toISOString(),
            'services' => [],
        ];

        $this->checkParserService($health);
        $this->checkSteamApi($health);

        $overallHealthy = collect($health['services'])->every(fn ($service) => $service['status'] === 'healthy');
        $health['status'] = $overallHealthy ? 'healthy' : 'degraded';

        return response()->json($health);
    }

    private function checkParserService(array &$health): void
    {
        try {
            $parserService = app(ParserServiceConnector::class);
            $parserService->checkServiceHealth();

            $health['services']['parser_service'] = [
                'status' => 'healthy',
                'message' => 'Parser service is responding',
            ];
        } catch (\Exception $e) {
            $health['services']['parser_service'] = [
                'status' => 'unhealthy',
                'message' => 'Parser service is not responding',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkSteamApi(array &$health): void
    {
        try {
            $steamApi = app(SteamApiService::class);
            $isHealthy = $steamApi->checkServiceHealth();

            $health['services']['steam_api'] = [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'message' => $isHealthy ? 'Steam API is responding' : 'Steam API is not responding',
            ];
        } catch (\Exception $e) {
            $health['services']['steam_api'] = [
                'status' => 'unhealthy',
                'message' => 'Steam API check failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
