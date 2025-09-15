<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProcessingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDemoRequest;
use App\Http\Resources\InProgressJobsResource;
use App\Jobs\ParseDemo;
use App\Services\ParserServiceConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function __construct(private readonly ParserServiceConnector $parserServiceConnector) {}

    public function userDemo(UploadDemoRequest $request): JsonResponse
    {
        try {
            $file = $request->file('demo');

            $fileName = time().'_'.Str::random(10).'.'.$file->getClientOriginalExtension();
            $publicPath = $file->storeAs('demos', $fileName, 'public');
            $fullPath = storage_path("app/public/{$publicPath}");

            ParseDemo::dispatch($fullPath, Auth::user());

            return response()->json([
                'success' => true,
                'message' => config('messaging.parsing.demo.success') ?? 'Demo process received',
            ]);
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
                'message' => config('messaging.parsing.demo.error') ?? 'An unexpected error occurred while uploading the demo',
            ], 500);
        }
    }

    public function getInProgressJobs(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $jobs = $user
            ->demoProcessingJobs()
            ->where('progress_percentage', '!=', 100)
            ->where('processing_status', '!=', ProcessingStatus::COMPLETED->value)
            ->get();

        return response()->json([
            'success' => true,
            'jobs' => InProgressJobsResource::collection($jobs),
        ]);
    }
}
