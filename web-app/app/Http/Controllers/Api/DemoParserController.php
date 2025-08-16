<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ]);

        $this->demoParserService->createMatchEvent($jobId, $request->input('data', []), $eventName);

        return response()->json([
            'success' => true,
            'message' => 'Event processed successfully',
            'job_id' => $jobId,
            'event_name' => $eventName,
        ]);
    }

    public function progressCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_id' => 'required|string',
            'status' => 'required|string',
            'progress' => 'required|integer|min:0|max:100',
            'current_step' => 'required|string',
            'error_message' => 'nullable|string',
            'match' => 'nullable|array',
            'players' => 'nullable|array',
        ]);

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

    public function completionCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_id' => 'required|string',
            'status' => 'required|string',
            'progress' => 'nullable|integer|min:0|max:100',
            'current_step' => 'nullable|string',
            'error' => 'nullable|string',
        ]);

        $this->demoParserService->updateProcessingJob($validated['job_id'], $validated, true);

        return response()->json([
            'success' => true,
            'message' => 'Completion update received',
            'job_id' => $validated['job_id'],
        ], 200);
    }
}
