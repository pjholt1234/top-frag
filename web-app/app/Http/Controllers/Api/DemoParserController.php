<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DemoParserJobNotFoundException;
use App\Exceptions\DemoParserMatchNotFoundException;
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
        try {
            $this->demoParserService->createMatchEvent($jobId, $request->input('data', []), $eventName);
        } catch (DemoParserMatchNotFoundException|DemoParserJobNotFoundException $e) {
            Log::warning($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'job_id' => $jobId,
                'event_name' => $eventName,
            ], 404);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'job_id' => $jobId,
                'event_name' => $eventName,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => config('messaging.parsing.events.created'),
            'job_id' => $jobId,
            'event_name' => $eventName,
        ]);
    }

    public function progressCallback(ProgressCallbackRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->demoParserService->updateProcessingJob($validated['job_id'], $validated);

            if (isset($validated['match'])) {
                $playersData = $validated['players'] ?? null;
                $this->demoParserService->createMatchWithPlayers($validated['job_id'], $validated['match'], $playersData);
            }
        } catch (DemoParserJobNotFoundException $e) {
            Log::warning($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'job_id' => $validated['job_id'],
            ], 404);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'job_id' => $validated['job_id'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => config('messaging.parsing.progress.updated'),
            'job_id' => $validated['job_id'],
        ], 200);
    }

    public function completionCallback(CompletionCallbackRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->demoParserService->updateProcessingJob($validated['job_id'], $validated, true);
        } catch (DemoParserJobNotFoundException $e) {
            Log::warning($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'job_id' => $validated['job_id'],
            ], 404);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'job_id' => $validated['job_id'],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => config('messaging.parsing.progress.completed'),
            'job_id' => $validated['job_id'],
        ]);
    }
}
