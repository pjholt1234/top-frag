<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * Health check endpoint
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        return response()->json([
            'status' => 'healthy',
            'message' => config('messaging.healthy'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
