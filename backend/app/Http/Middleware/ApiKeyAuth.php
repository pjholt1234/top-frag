<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');

        // Remove 'Bearer ' prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        // Check if API key is provided
        if (! $apiKey) {
            return response()->json([
                'error' => 'API key is required',
                'message' => 'Please provide a valid API key in the X-API-Key or Authorization header',
            ], 401);
        }

        // Validate API key against environment variable
        $validApiKey = config('app.api_key') ?? env('API_KEY');

        if (! $validApiKey) {
            // If no API key is configured, reject all requests
            return response()->json([
                'error' => 'API authentication not configured',
                'message' => 'Please configure API_KEY in your environment',
            ], 500);
        }

        if ($apiKey !== $validApiKey) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid',
            ], 401);
        }

        return $next($request);
    }
}
