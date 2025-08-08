<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    /**
     * Upload a demo file.
     */
    public function demo(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Demo upload initiated',
            'data' => [
                'job_id' => 'job_' . uniqid(),
                'status' => 'pending',
                'estimated_processing_time' => '5-10 minutes',
                'upload_id' => uniqid()
            ]
        ], 202);
    }

    /**
     * Get upload processing status.
     */
    public function status(string $job): JsonResponse
    {
        return response()->json([
            'message' => 'Upload status',
            'data' => [
                'job_id' => $job,
                'status' => 'processing',
                'progress' => 45,
                'current_step' => 'Analyzing gunfight events',
                'estimated_completion' => '2024-12-01T12:30:00Z',
                'match_id' => null
            ]
        ]);
    }
}
