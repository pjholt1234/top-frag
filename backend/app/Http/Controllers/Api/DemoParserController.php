<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DemoParserEventRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DemoParserService;

class DemoParserController extends Controller
{
    public function __construct(private readonly DemoParserService $demoParserService) {}

    public function handleEvent(DemoParserEventRequest $request, string $jobId, string $eventName): JsonResponse
    {
        // Log the incoming request data to parser.log
        // Log::channel('parser')->info('Demo parser event received', [
        //     'job_id' => $jobId,
        //     'event_name' => $eventName,
        //     'request_data' => $request->validated(),
        //     'headers' => $request->headers->all(),
        //     'timestamp' => now()->toISOString(),
        // ]);

        // // Log successful event processing
        // Log::channel('parser')->info('Demo parser event processed successfully', [
        //     'job_id' => $jobId,
        //     'event_name' => $eventName,
        //     'data_count' => count($request->input('data', [])),
        //     'batch_info' => [
        //         'batch_index' => $request->input('batch_index'),
        //         'is_last' => $request->input('is_last'),
        //         'total_batches' => $request->input('total_batches'),
        //     ],
        // ]);

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
            'job_id' => $validated['job_id']
        ], 200);
    }

    public function completionCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_id' => 'required|string',
            'status' => 'required|string',
            'progress' => 'nullable|integer|min:0|max:100',
            'current_step' => 'nullable|string',
            'error' => 'nullable|string'
        ]);

        $this->demoParserService->updateProcessingJob($validated['job_id'], $validated, true);

        return response()->json([
            'success' => true,
            'message' => 'Completion update received',
            'job_id' => $validated['job_id']
        ], 200);
    }
}
