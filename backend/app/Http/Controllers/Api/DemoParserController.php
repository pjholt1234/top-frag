<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DemoParserController extends Controller
{
    /**
     * Handle incoming demo data events from the parser service
     * 
     * @param Request $request
     * @param string $jobId
     * @param string $eventName
     * @return JsonResponse
     */
    public function handleEvent(Request $request, string $jobId, string $eventName): JsonResponse
    {
        // Log the incoming request data to parser.log
        Log::channel('parser')->info('Demo parser event received', [
            'job_id' => $jobId,
            'event_name' => $eventName,
            'request_data' => $request->all(),
            'headers' => $request->headers->all(),
            'timestamp' => now()->toISOString(),
        ]);

        // Validate event name
        $validEventNames = ['round', 'gunfight', 'grenade', 'damage'];
        if (!in_array($eventName, $validEventNames)) {
            Log::channel('parser')->warning('Invalid event name received', [
                'job_id' => $jobId,
                'event_name' => $eventName,
                'valid_events' => $validEventNames,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid event name. Valid events: ' . implode(', ', $validEventNames),
            ], 400);
        }

        // Log successful event processing
        Log::channel('parser')->info('Demo parser event processed successfully', [
            'job_id' => $jobId,
            'event_name' => $eventName,
            'data_count' => count($request->input('data', [])),
            'batch_info' => [
                'batch_index' => $request->input('batch_index'),
                'is_last' => $request->input('is_last'),
                'total_batches' => $request->input('total_batches'),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event processed successfully',
            'job_id' => $jobId,
            'event_name' => $eventName,
        ]);
    }
}
