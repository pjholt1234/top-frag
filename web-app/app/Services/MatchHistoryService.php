<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use App\Services\Matches\MatchDetailsService;

class MatchHistoryService
{
    private ?Player $player;

    private ?User $user;

    public function __construct(
        private readonly MatchDetailsService $matchDetailsService,
    ) {}

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->player = $user->player;
    }

    /**
     * Get paginated match history for better performance with large datasets
     */
    public function getPaginatedMatchHistory(User $user, int $perPage = 10, int $page = 1, array $filters = []): array
    {
        $this->setUser($user);

        if (! $this->player) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ];
        }

        $completedMatches = $this->getCompletedMatches($user, $filters);
        $inProgressJobs = $this->getInProgressJobs($user, $filters);

        $allMatches = collect([...$completedMatches, ...$inProgressJobs])
            ->sortByDesc('created_at')
            ->values();

        $total = $allMatches->count();
        $offset = ($page - 1) * $perPage;
        $paginatedMatches = $allMatches->slice($offset, $perPage);

        return [
            'data' => $paginatedMatches->toArray(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * Get completed matches with filters
     */
    private function getCompletedMatches(User $user, array $filters = []): array
    {
        $matchIds = $this->getFilteredMatchIds($filters);
        $completedMatches = [];

        foreach ($matchIds as $matchId) {
            $completedMatches[] = $this->matchDetailsService->getDetails($user, $matchId);
        }

        return $completedMatches;
    }

    /**
     * Get filtered match IDs without loading full match data
     */
    private function getFilteredMatchIds(array $filters = []): array
    {
        $query = $this->player->matches()->select('matches.id');

        if (! empty($filters['map'])) {
            $query->where('map', 'like', '%'.$filters['map'].'%');
        }

        if (! empty($filters['match_type'])) {
            $query->where('match_type', $filters['match_type']);
        }

        if (isset($filters['player_was_participant']) && $filters['player_was_participant'] !== '') {
            $query->whereHas('players', function ($q) {
                $q->where('steam_id', $this->player->steam_id);
            });
        }

        if (isset($filters['player_won_match']) && $filters['player_won_match'] !== '') {
            $isWin = $filters['player_won_match'] === 'true';
            $query->where(function ($q) use ($isWin) {
                if ($isWin) {
                    $q->where('winning_team', 'A')->whereHas('players', function ($pq) {
                        $pq->where('steam_id', $this->player->steam_id)->where('team', 'A');
                    })->orWhere('winning_team', 'B')->whereHas('players', function ($pq) {
                        $pq->where('steam_id', $this->player->steam_id)->where('team', 'B');
                    });
                } else {
                    $q->where(function ($subQ) {
                        $subQ->where('winning_team', 'A')->whereHas('players', function ($pq) {
                            $pq->where('steam_id', $this->player->steam_id)->where('team', 'B');
                        })->orWhere('winning_team', 'B')->whereHas('players', function ($pq) {
                            $pq->where('steam_id', $this->player->steam_id)->where('team', 'A');
                        });
                    });
                }
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        return $query->orderBy('matches.created_at', 'desc')->pluck('id')->toArray();
    }

    /**
     * Get in-progress jobs with filters
     */
    private function getInProgressJobs(User $user, array $filters = []): array
    {
        $query = $user->demoProcessingJobs()
            ->where('progress_percentage', '<', 100)
            ->where('processing_status', '!=', \App\Enums\ProcessingStatus::COMPLETED->value)
            ->with('match');

        // Apply filters that work for in-progress jobs
        if (! empty($filters['map'])) {
            $query->whereHas('match', function ($q) use ($filters) {
                $q->where('map', 'like', '%'.$filters['map'].'%');
            });
        }

        if (! empty($filters['match_type'])) {
            $query->whereHas('match', function ($q) use ($filters) {
                $q->where('match_type', $filters['match_type']);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $jobs = $query->orderBy('created_at', 'desc')->get();

        return $jobs->map(function ($job) {
            $match = $job->match;

            $matchDetails = null;
            if ($match) {
                $matchDetails = [
                    'id' => $match->id,
                    'map' => $match->map,
                    'winning_team_score' => $match->winning_team_score,
                    'losing_team_score' => $match->losing_team_score,
                    'winning_team' => $match->winning_team,
                    'match_type' => $match->match_type,
                    'created_at' => $match->created_at,
                ];
            }

            return [
                'id' => $job->id,
                'created_at' => $job->created_at,
                'is_completed' => false,
                'match_details' => $matchDetails,
                'player_stats' => null, // Not available for in-progress jobs
                'processing_status' => $job->processing_status,
                'progress_percentage' => $job->progress_percentage,
                'current_step' => $job->current_step,
                'error_message' => $job->error_message,
            ];
        })->toArray();
    }

    /**
     * Get a single in-progress job by ID
     */
    private function getInProgressJobById(User $user, int $jobId): ?array
    {
        $job = $user->demoProcessingJobs()
            ->where('id', $jobId)
            ->where('progress_percentage', '<', 100)
            ->where('processing_status', '!=', \App\Enums\ProcessingStatus::COMPLETED->value)
            ->with('match')
            ->first();

        if (! $job) {
            return null;
        }

        $match = $job->match;

        $matchDetails = null;
        if ($match) {
            $matchDetails = [
                'id' => $match->id,
                'map' => $match->map,
                'winning_team_score' => $match->winning_team_score,
                'losing_team_score' => $match->losing_team_score,
                'winning_team' => $match->winning_team,
                'match_type' => $match->match_type,
                'created_at' => $match->created_at,
            ];
        }

        return [
            'id' => $job->id,
            'created_at' => $job->created_at,
            'is_completed' => false,
            'match_details' => $matchDetails,
            'player_stats' => null, // Not available for in-progress jobs
            'processing_status' => $job->processing_status,
            'progress_percentage' => $job->progress_percentage,
            'current_step' => $job->current_step,
            'error_message' => $job->error_message,
        ];
    }

    /**
     * Legacy method for backward compatibility
     */
    public function getAllPlayerGunfightEvents(GameMatch $match, Player $player)
    {
        return $match
            ->gunfightEvents()
            ->where(function ($query) use ($player) {
                $query->where('player_1_steam_id', $player->steam_id)
                    ->orWhere('player_2_steam_id', $player->steam_id);
            })
            ->get();
    }
}
