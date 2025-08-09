<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    /**
     * Health check endpoint
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        Log::channel('parser')->info('Health check request received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        $response = [
            'status' => 'healthy',
            'message' => 'Top Frag API is running',
            'timestamp' => now()->toISOString()
        ];

        Log::channel('parser')->info('Health check response sent', [
            'response' => $response
        ]);

        return response()->json($response);
    }
}
