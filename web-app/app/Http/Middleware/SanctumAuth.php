<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SanctumAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::channel('parser')->info('Sanctum authentication request received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ]);

        // Check if user is authenticated via Sanctum
        if (! $request->user()) {
            Log::channel('parser')->warning('Unauthenticated Sanctum request', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Valid Sanctum token is required',
            ], 401);
        }

        $response = $next($request);

        Log::channel('parser')->info('Sanctum authentication response sent', [
            'user_id' => $request->user()?->id,
            'response' => $response,
        ]);

        return $response;
    }
}
