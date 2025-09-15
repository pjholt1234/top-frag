<?php

namespace App\Services;

use App\Enums\ProcessingStatus;
use App\Exceptions\PlayerNotFound;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use App\Services\Matches\MatchDetailsService;
use Illuminate\Contracts\Database\Query\Builder;

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
     * @throws PlayerNotFound
     */
    public function getPaginatedMatchHistory(User $user, int $perPage = 10, int $page = 1, array $filters = []): array
    {
        $this->setUser($user);

        $completedMatches = $this->getCompletedMatches($filters);
        $inProgressJobs = $this->getInProgressJobs($filters);

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

    private function getCompletedMatches(array $filters = []): array
    {
        $matchIds = $this->getFilteredMatchIds($filters);
        $completedMatches = [];

        foreach ($matchIds as $matchId) {
            $completedMatches[] = $this->matchDetailsService->getDetails($this->user, $matchId);
        }

        return $completedMatches;
    }

    private function getFilteredMatchIds(array $filters = []): array
    {
        $matchIds = [];
        if ($this->player) {
            $matchIds = $this->getMatchIds($this->player?->matches(), $filters);
        }

        if ($this->user) {
            $matchIds = [
                ...$matchIds,
                ...$this->getMatchIds($this->user?->uploadedGames(), $filters),
            ];
        }

        if (! empty($matchIds)) {
            return GameMatch::whereIn('id', array_unique($matchIds))
                ->whereHas('demoProcessingJob', function ($query) {
                    $query->whereIn('processing_status', [ProcessingStatus::COMPLETED->value, ProcessingStatus::FAILED->value]);
                })
                ->orderBy('created_at', 'desc')
                ->pluck('id')
                ->toArray();
        }

        return [];
    }

    private function getMatchIds(Builder $query, array $filters = []): array
    {
        $this->applyFiltersToQuery($query, $filters);

        return $query->pluck('matches.id')->toArray();
    }

    private function applyFiltersToQuery($query, array $filters): void
    {
        if (! empty($filters['map'])) {
            $query->where('map', 'like', '%'.$filters['map'].'%');
        }

        if (! empty($filters['match_type'])) {
            $query->where('match_type', $filters['match_type']);
        }

        if (! empty($filters['player_was_participant'])) {
            $isParticipant = $this->convertToBoolean($filters['player_was_participant']);
            if ($isParticipant) {
                $query->whereHas('players', function ($q) {
                    $q->where('steam_id', $this->player->steam_id);
                });
            } else {
                $query->whereDoesntHave('players', function ($q) {
                    $q->where('steam_id', $this->player->steam_id);
                });
            }
        }

        if (! empty($filters['player_won_match'])) {
            $isWin = $this->convertToBoolean($filters['player_won_match']);
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
            $query->where('matches.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('matches.created_at', '<=', $filters['date_to'].' 23:59:59');
        }
    }

    private function getInProgressJobs(array $filters = []): array
    {
        $query = $this->user->demoProcessingJobs()
            ->where('progress_percentage', '<', 100)
            ->where('processing_status', '!=', ProcessingStatus::COMPLETED->value)
            ->with('match');

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
            $query->where('demo_processing_jobs.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('demo_processing_jobs.created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $jobs = $query->orderBy('demo_processing_jobs.created_at', 'desc')->get();

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
     * Convert various string representations to boolean
     */
    private function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return false;
    }
}
