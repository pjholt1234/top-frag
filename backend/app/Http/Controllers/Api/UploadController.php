<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UploadController extends Controller
{
    /**
     * In-memory storage for job progress data.
     * In production, this should be replaced with a database table.
     */
    private static array $jobProgress = [];

    /**
     * Upload a demo file.
     */
    public function demo(Request $request): JsonResponse
    {
        Log::channel('parser')->info('Demo upload request received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'has_file' => $request->hasFile('demo'),
            'all_data' => $request->all()
        ]);

        $jobId = 'job_' . uniqid();
        $uploadId = uniqid();

        $response = [
            'message' => 'Demo upload initiated',
            'data' => [
                'job_id' => $jobId,
                'status' => 'pending',
                'estimated_processing_time' => '5-10 minutes',
                'upload_id' => $uploadId
            ]
        ];

        Log::channel('parser')->info('Demo upload response sent', [
            'job_id' => $jobId,
            'upload_id' => $uploadId,
            'response' => $response
        ]);

        return response()->json($response, 202);
    }

    /**
     * Get upload processing status.
     */
    public function status(string $job): JsonResponse
    {
        Log::channel('parser')->info('Upload status request received', [
            'job_id' => $job,
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'headers' => request()->headers->all(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        // Get stored progress data for this job
        $progressData = self::$jobProgress[$job] ?? null;

        if (!$progressData) {
            $response = [
                'message' => 'Job not found',
                'data' => [
                    'job_id' => $job,
                    'status' => 'not_found',
                    'progress' => 0,
                    'current_step' => 'Job not found',
                    'error_message' => 'Job not found or no progress data available'
                ]
            ];
        } else {
            $response = [
                'message' => 'Upload status',
                'data' => [
                    'job_id' => $job,
                    'status' => $progressData['status'] ?? 'unknown',
                    'progress' => $progressData['progress'] ?? 0,
                    'current_step' => $progressData['current_step'] ?? 'Unknown',
                    'error_message' => $progressData['error_message'] ?? null,
                    'last_updated' => $progressData['timestamp'] ?? null
                ]
            ];
        }

        Log::channel('parser')->info('Upload status response sent', [
            'job_id' => $job,
            'response' => $response
        ]);

        return response()->json($response);
    }

    /**
     * Handle progress updates from the parser service.
     */
    public function progressCallback(Request $request): JsonResponse
    {
        Log::channel('parser')->info('Progress callback received from parser service', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'all_data' => $request->all()
        ]);

        // Validate the request
        $validated = $request->validate([
            'job_id' => 'required|string',
            'status' => 'required|string',
            'progress' => 'required|integer|min:0|max:100',
            'current_step' => 'required|string',
            'error_message' => 'nullable|string'
        ]);

        $this->logProgressUpdate($validated);

        // Store progress data for retrieval by status endpoint
        self::$jobProgress[$validated['job_id']] = [
            'status' => $validated['status'],
            'progress' => $validated['progress'],
            'current_step' => $validated['current_step'],
            'error_message' => $validated['error_message'] ?? null,
            'timestamp' => now()->toISOString()
        ];

        $response = [
            'success' => true,
            'message' => 'Progress update received',
            'job_id' => $validated['job_id']
        ];

        Log::channel('parser')->info('Progress callback response sent', [
            'job_id' => $validated['job_id'],
            'response' => $response
        ]);

        return response()->json($response, 200);
    }

    /**
     * Handle completion updates from the parser service.
     */
    public function completionCallback(Request $request): JsonResponse
    {
        Log::channel('parser')->info('Completion callback received from parser service', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'all_data' => $request->all()
        ]);

        // Validate the request - make job_id optional since parser service might not send it
        $validated = $request->validate([
            'job_id' => 'nullable|string',
            'status' => 'required|string',
            'match_data' => 'nullable|array',
            'error' => 'nullable|string'
        ]);

        // Generate a job_id if not provided
        if (empty($validated['job_id'])) {
            $validated['job_id'] = 'unknown-job-' . uniqid();
            Log::channel('parser')->warning('Completion callback missing job_id, generated one', [
                'generated_job_id' => $validated['job_id'],
                'original_data' => $request->all()
            ]);
        }

        // Log the completion update to a dedicated file
        $this->logCompletionUpdate($validated);

        // Store completion data for retrieval by status endpoint
        self::$jobProgress[$validated['job_id']] = [
            'status' => $validated['status'],
            'progress' => 100, // Completion means 100% progress
            'current_step' => 'Completed',
            'error_message' => $validated['error'] ?? null,
            'timestamp' => now()->toISOString(),
            'completed' => true
        ];

        // Return success response to parser service
        $response = [
            'success' => true,
            'message' => 'Completion update received',
            'job_id' => $validated['job_id']
        ];

        Log::channel('parser')->info('Completion callback response sent', [
            'job_id' => $validated['job_id'],
            'response' => $response
        ]);

        return response()->json($response, 200);
    }

    /**
     * Log progress updates to a dedicated file.
     */
    private function logProgressUpdate(array $data): void
    {
        $timestamp = now()->toISOString();

        $logEntry = [
            'timestamp' => $timestamp,
            'type' => 'progress',
            'job_id' => $data['job_id'],
            'status' => $data['status'],
            'progress' => $data['progress'],
            'current_step' => $data['current_step'],
            'error_message' => $data['error_message'] ?? null
        ];

        // Log to Laravel's main log
        Log::channel('parser')->info('Parser progress update', $logEntry);
    }

    /**
     * Log completion updates to a dedicated file.
     */
    private function logCompletionUpdate(array $data): void
    {
        $timestamp = now()->toISOString();

        $logEntry = [
            'timestamp' => $timestamp,
            'type' => 'completion',
            'job_id' => $data['job_id'],
            'status' => $data['status'],
            'has_match_data' => isset($data['match_data']),
            'match_data_keys' => isset($data['match_data']) ? array_keys($data['match_data']) : null,
            'error' => $data['error'] ?? null
        ];

        // Log to Laravel's main log
        Log::channel('parser')->info('Parser completion update', $logEntry);

        // If there's match data, log it separately for analysis
        if (isset($data['match_data'])) {
            $matchDataEntry = [
                'timestamp' => $timestamp,
                'job_id' => $data['job_id'],
                'match_data' => $data['match_data']
            ];

            Log::channel('parser')->info('Parser match data', $matchDataEntry);
        }
    }
}
