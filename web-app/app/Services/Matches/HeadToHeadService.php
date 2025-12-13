<?php

namespace App\Services\Matches;

use App\Models\GameMatch;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Infrastructure\MatchCacheManager;
use App\Services\Integrations\Steam\SteamAPIConnector;

class HeadToHeadService
{
    use MatchAccessTrait;

    public function __construct(
        private readonly PlayerComplexionService $playerComplexionService,
        private readonly UtilityAnalysisService $utilityAnalysisService,
        private readonly SteamAPIConnector $steamApiConnector
    ) {}

    public function getHeadToHead(User $user, int $matchId): array
    {
        // Check user access first
        if (! $this->hasUserAccessToMatch($user, $matchId)) {
            return [];
        }

        $cacheKey = 'head-to-head-base';

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $matchId) {
            return $this->buildHeadToHead($user, $matchId);
        });
    }

    public function getPlayerStats(User $user, int $matchId, string $playerSteamId): array
    {
        // Check user access first
        if (! $this->hasUserAccessToMatch($user, $matchId)) {
            return [];
        }

        $cacheKey = $this->getPlayerCacheKey($playerSteamId);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $matchId, $playerSteamId) {
            return $this->buildPlayerStats($user, $matchId, $playerSteamId);
        });
    }

    private function buildHeadToHead(User $user, int $matchId): array
    {
        $match = GameMatch::find($matchId);
        if (! $match) {
            return [];
        }

        return [
            'players' => $this->getAvailablePlayers($match),
            'current_user_steam_id' => $user->steam_id,
            'match_data' => [
                'game_mode' => $match->game_mode,
                'match_type' => $match->match_type,
            ],
        ];
    }

    private function buildPlayerStats(User $user, int $matchId, string $playerSteamId): array
    {
        $playerMatchEvent = PlayerMatchEvent::query()
            ->where('match_id', $matchId)
            ->where('player_steam_id', $playerSteamId)
            ->first();

        if (! $playerMatchEvent) {
            return [];
        }

        return [
            'basic_stats' => $this->getBasicStats($playerMatchEvent),
            'player_complexion' => $this->playerComplexionService->get($playerSteamId, $matchId),
            'role_stats' => $this->getRoleStats($playerMatchEvent),
            'utility_analysis' => $this->utilityAnalysisService->getAnalysis($user, $matchId, $playerSteamId),
            'rank_data' => $this->getRankData($playerMatchEvent),
        ];
    }

    private function getBasicStats(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            'kills' => (int) $playerMatchEvent->kills,
            'deaths' => (int) $playerMatchEvent->deaths,
            'adr' => (float) $playerMatchEvent->adr,
            'assists' => (int) $playerMatchEvent->assists,
            'headshots' => (int) $playerMatchEvent->headshots,
            'total_impact' => (float) $playerMatchEvent->total_impact,
            'impact_percentage' => (float) $playerMatchEvent->impact_percentage,
            'match_swing_percent' => (float) $playerMatchEvent->match_swing_percent,
        ];
    }

    private function getRoleStats(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            'opener' => [
                'First Kills' => [
                    'value' => $playerMatchEvent->first_kills,
                    'higherIsBetter' => true,
                ],
                'First Deaths' => [
                    'value' => $playerMatchEvent->first_deaths,
                    'higherIsBetter' => false,
                ],
                'Avg Time to Contact' => [
                    'value' => round($playerMatchEvent->average_time_to_contact, 1),
                    'higherIsBetter' => false,
                ],
                'Avg Time of Death' => [
                    'value' => round($playerMatchEvent->average_round_time_of_death, 1),
                    'higherIsBetter' => false,
                ],
                'Total Traded Deaths' => [
                    'value' => $playerMatchEvent->total_traded_deaths,
                    'higherIsBetter' => true,
                ],
                'Traded Death Success Rate' => [
                    'value' => round(calculatePercentage($playerMatchEvent->total_traded_deaths, $playerMatchEvent->total_possible_traded_deaths), 1),
                    'higherIsBetter' => true,
                ],
            ],
            'closer' => [
                'Clutch Wins' => [
                    'value' => $playerMatchEvent->clutch_wins,
                    'higherIsBetter' => true,
                ],
                'Clutch Attempts' => [
                    'value' => $playerMatchEvent->clutch_attempts,
                    'higherIsBetter' => true,
                ],
                'Clutch Win Rate' => [
                    'value' => round($playerMatchEvent->clutch_win_percentage, 1),
                    'higherIsBetter' => true,
                ],
                'Avg Time to Contact' => [
                    'value' => round($playerMatchEvent->average_time_to_contact, 1),
                    'higherIsBetter' => true,
                ],
                'Avg Time of Death' => [
                    'value' => round($playerMatchEvent->average_round_time_of_death, 1),
                    'higherIsBetter' => true,
                ],
            ],
            'support' => [
                'Grenades Thrown' => [
                    'value' => $playerMatchEvent->grenades_thrown,
                    'higherIsBetter' => true,
                ],
                'Damage from Grenades' => [
                    'value' => $playerMatchEvent->damage_dealt,
                    'higherIsBetter' => true,
                ],
                'Enemy Flash Duration' => [
                    'value' => round($playerMatchEvent->enemy_flash_duration, 1),
                    'higherIsBetter' => true,
                ],
                'Grenade Effectiveness' => [
                    'value' => round($playerMatchEvent->average_grenade_effectiveness, 1),
                    'higherIsBetter' => true,
                ],
                'Flashes Leading to Kills' => [
                    'value' => $playerMatchEvent->flashes_leading_to_kills,
                    'higherIsBetter' => true,
                ],
                'Total Enemies Flashed' => [
                    'value' => $playerMatchEvent->enemy_players_affected,
                    'higherIsBetter' => true,
                ],
                'Average Grenade Value Lost On Death' => [
                    'value' => $playerMatchEvent->average_grenade_value_lost,
                    'higherIsBetter' => false,
                ],
            ],
            'fragger' => [
                'Kills' => [
                    'value' => $playerMatchEvent->kills,
                    'higherIsBetter' => true,
                ],
                'Deaths' => [
                    'value' => $playerMatchEvent->deaths,
                    'higherIsBetter' => false,
                ],
                'ADR' => [
                    'value' => $playerMatchEvent->adr,
                    'higherIsBetter' => true,
                ],
                'Headshots' => [
                    'value' => $playerMatchEvent->headshots,
                    'higherIsBetter' => true,
                ],
                'Total Trade kills' => [
                    'value' => $playerMatchEvent->total_successful_trades,
                    'higherIsBetter' => true,
                ],
                'Trade Success Rate' => [
                    'value' => round(calculatePercentage($playerMatchEvent->total_successful_trades, $playerMatchEvent->total_possible_trades), 1),
                    'higherIsBetter' => true,
                ],
                'Kills with AWP' => [
                    'value' => $playerMatchEvent->kills_with_awp,
                    'higherIsBetter' => true,
                ],
                'Total Kills vs Eco' => [
                    'value' => $playerMatchEvent->kills_vs_eco,
                    'higherIsBetter' => false,
                ],
                'Percentage of Kills vs Eco' => [
                    'value' => round(calculatePercentage($playerMatchEvent->kills_vs_eco, $playerMatchEvent->kills), 1),
                    'higherIsBetter' => false,
                ],
                'Total Kills vs Force Buy' => [
                    'value' => $playerMatchEvent->kills_vs_force_buy,
                    'higherIsBetter' => true,
                ],
                'Percentage of Kills vs Force Buy' => [
                    'value' => round(calculatePercentage($playerMatchEvent->kills_vs_force_buy, $playerMatchEvent->kills), 1),
                    'higherIsBetter' => true,
                ],
                'Total Kills vs Full Buy' => [
                    'value' => $playerMatchEvent->kills_vs_full_buy,
                    'higherIsBetter' => true,
                ],
                'Percentage of Kills vs Full Buy' => [
                    'value' => round(calculatePercentage($playerMatchEvent->kills_vs_full_buy, $playerMatchEvent->kills), 1),
                    'higherIsBetter' => true,
                ],
            ],
        ];
    }

    private function getPlayerCacheKey(string $playerSteamId): string
    {
        return "head-to-head-player_{$playerSteamId}";
    }

    private function getRankData(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            'rank_value' => $playerMatchEvent->rank_value,
            'rank_type' => $playerMatchEvent->rank_type,
        ];
    }

    private function getAvailablePlayers(GameMatch $match): array
    {
        $players = $match->players->map(function ($player) {
            return [
                'steam_id' => $player->steam_id,
                'name' => $player->name,
            ];
        })->toArray();

        $steamIds = array_column($players, 'steam_id');

        // Get stored profile data for registered users
        $users = User::whereIn('steam_id', $steamIds)->get()->keyBy('steam_id');
        $registeredSteamIds = $users->pluck('steam_id')->toArray();
        $nonRegisteredSteamIds = array_diff($steamIds, $registeredSteamIds);

        // Enhance players with stored Steam profile data for registered users
        foreach ($players as &$player) {
            $steamId = $player['steam_id'];
            $user = $users->get($steamId);

            if ($user && $user->steam_avatar_full) {
                $player['steam_profile'] = [
                    'persona_name' => $user->steam_persona_name,
                    'profile_url' => $user->steam_profile_url,
                    'avatar' => $user->steam_avatar,
                    'avatar_medium' => $user->steam_avatar_medium,
                    'avatar_full' => $user->steam_avatar_full,
                    'persona_state' => $user->steam_persona_state,
                    'community_visibility_state' => $user->steam_community_visibility_state,
                ];
            }
        }

        // Fallback to Steam API for non-registered players
        if (! empty($nonRegisteredSteamIds)) {
            try {
                $steamProfiles = $this->steamApiConnector->getPlayerSummaries($nonRegisteredSteamIds);

                if ($steamProfiles) {
                    foreach ($players as &$player) {
                        $steamId = $player['steam_id'];

                        // Only add Steam profile if not already set (from registered user data)
                        if (! isset($player['steam_profile']) && isset($steamProfiles[$steamId])) {
                            $profile = $steamProfiles[$steamId];
                            $player['steam_profile'] = [
                                'persona_name' => $profile['persona_name'],
                                'profile_url' => $profile['profile_url'],
                                'avatar' => $profile['avatar'],
                                'avatar_medium' => $profile['avatar_medium'],
                                'avatar_full' => $profile['avatar_full'],
                                'persona_state' => $profile['persona_state'],
                                'community_visibility_state' => $profile['community_visibility_state'],
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log error but don't fail the entire request
                \Log::warning('Failed to fetch Steam profiles for non-registered players', [
                    'steam_ids' => $nonRegisteredSteamIds,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $players;
    }
}
