<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ParserServiceConnectorException;
use App\Http\Controllers\Controller;
use App\Services\ParserServiceConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function __construct(private readonly ParserServiceConnector $parserServiceConnector) {}

    public function demo(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'demo' => 'required|file|mimes:dem|max:102400', // 100MB max
            ]);

            $file = $request->file('demo');

            // Store file temporarily
            $tempPath = $file->store('temp', 'local');
            $fullPath = storage_path("app/{$tempPath}");

            try {
                // Generate a UUID for the job
                $jobId = Str::uuid()->toString();

                // Upload the demo to parser service
                $response = $this->parserServiceConnector->uploadDemo($fullPath, $jobId);

                Log::channel('parser')->info('Demo upload successful via API', [
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'job_id' => $jobId,
                    'response' => $response,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Demo uploaded successfully',
                    'data' => $response,
                ], 200);
            } finally {
                // Clean up temporary file
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        } catch (ParserServiceConnectorException $e) {
            // The exception is already logged by the exception class
            Log::channel('parser')->error('Demo upload failed via API', [
                'file_name' => $request->file('demo')?->getClientOriginalName(),
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Parser service error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel('parser')->warning('Demo upload validation failed', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Validation error',
                'message' => 'Invalid demo file',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::channel('parser')->error('Unexpected error in demo upload', [
                'file_name' => $request->file('demo')?->getClientOriginalName(),
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
