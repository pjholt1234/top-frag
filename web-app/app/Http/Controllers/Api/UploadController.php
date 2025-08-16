<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ParseDemo;
use App\Services\ParserServiceConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function __construct(private readonly ParserServiceConnector $parserServiceConnector) {}

    public function userDemo(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'demo' => 'required|file|max:1073741824', // 1GB max
            ]);

            $file = $request->file('demo');

            // Store file in public directory
            $fileName = time().'_'.Str::random(10).'.'.$file->getClientOriginalExtension();
            $publicPath = $file->storeAs('demos', $fileName, 'public');
            $fullPath = storage_path("app/public/{$publicPath}");

            ParseDemo::dispatch($fullPath);

            return response()->json([
                'success' => true,
                'message' => 'Demo uploaded successfully',
                'file_path' => $publicPath,
                'file_url' => asset("storage/{$publicPath}"),
            ], 200);
        } catch (\Exception $e) {
            Log::channel('parser')->error('Unexpected error in demo upload via user API', [
                'file_name' => $request->file('demo')?->getClientOriginalName(),
                'user_id' => $request->user()?->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while uploading the demo',
            ], 500);
        }
    }
}
