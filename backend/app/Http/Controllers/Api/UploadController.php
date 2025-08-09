<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UploadController extends Controller
{
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
}
