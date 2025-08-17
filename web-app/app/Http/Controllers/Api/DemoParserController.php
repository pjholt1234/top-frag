<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompletionCallbackRequest;
use App\Http\Requests\ProgressCallbackRequest;
use App\Services\DemoParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DemoParserController extends Controller
{
    public function __construct(private readonly DemoParserService $demoParserService) {}

    public function handleEvent(Request $request, string $jobId, string $eventName): JsonResponse
    {
        Log::channel('parser')->info('Demo parser event received', [
            'job_id' => $jobId,
            'event_name' => $eventName,
            'data' => $request->input('data', []),
        ]);

        $this->demoParserService->createMatchEvent($jobId, $request->input('data', []), $eventName);

        return response()->json([
            'success' => true,
            'message' => 'Event processed successfully',
            'job_id' => $jobId,
            'event_name' => $eventName,
        ]);
    }

    public function progressCallback(ProgressCallbackRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->demoParserService->updateProcessingJob($validated['job_id'], $validated);

        if (isset($validated['match'])) {
            $playersData = $validated['players'] ?? null;
            $this->demoParserService->createMatchWithPlayers($validated['job_id'], $validated['match'], $playersData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Progress update received',
            'job_id' => $validated['job_id'],
        ], 200);
    }

    public function completionCallback(CompletionCallbackRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->demoParserService->updateProcessingJob($validated['job_id'], $validated, true);

        return response()->json([
            'success' => true,
            'message' => 'Completion update received',
            'job_id' => $validated['job_id'],
        ], 200);
    }
}
