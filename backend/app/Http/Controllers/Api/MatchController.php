<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'List of matches',
            'data' => [],
            'meta' => [
                'total' => 0,
                'per_page' => 15,
                'current_page' => 1,
                'last_page' => 1
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Match created successfully',
            'data' => [
                'id' => 1,
                'match_hash' => 'sample_hash_123',
                'map' => 'de_dust2',
                'status' => 'pending'
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Match details',
            'data' => [
                'id' => $id,
                'match_hash' => 'sample_hash_123',
                'map' => 'de_dust2',
                'winning_team_score' => 16,
                'losing_team_score' => 14,
                'match_type' => 'mm',
                'total_rounds' => 30,
                'processing_status' => 'completed'
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Match updated successfully',
            'data' => [
                'id' => $id,
                'updated' => true
            ]
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Match deleted successfully',
            'data' => [
                'id' => $id,
                'deleted' => true
            ]
        ]);
    }
}
