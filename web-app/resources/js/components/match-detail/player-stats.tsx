import { useState, useEffect, useCallback } from 'react';
import { api } from '@/lib/api';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { PlayerStatsFilters } from './player-stats/player-stats-filters';
import { ComplexionStats } from './player-stats/complexion-stats';
import { DeepDiveStats } from './player-stats/deep-dive-stats';
import { Info } from 'lucide-react';

// Import chart components
import { TradeStatsChart } from './player-stats/trade-stats-chart';
import { ClutchChart } from './player-stats/clutch-chart';

interface PlayerComplexion {
  opener: number;
  closer: number;
  support: number;
  fragger: number;
}

interface TradesData {
  total_successful_trades: number;
  total_possible_trades: number;
  total_traded_deaths: number;
  total_possible_traded_deaths: number;
}

interface ClutchData {
  '1v1': {
    clutch_wins_1v1: number;
    clutch_attempts_1v1: number;
    clutch_win_percentage_1v1: number;
  };
  '1v2': {
    clutch_wins_1v2: number;
    clutch_attempts_1v2: number;
    clutch_win_percentage_1v2: number;
  };
  '1v3': {
    clutch_wins_1v3: number;
    clutch_attempts_1v3: number;
    clutch_win_percentage_1v3: number;
  };
  '1v4': {
    clutch_wins_1v4: number;
    clutch_attempts_1v4: number;
    clutch_win_percentage_1v4: number;
  };
  '1v5': {
    clutch_wins_1v5: number;
    clutch_attempts_1v5: number;
    clutch_win_percentage_1v5: number;
  };
}

interface OpeningDuelsData {
  first_kills: number;
  first_deaths: number;
  avg_time_to_death: number | string;
  avg_time_to_contact: number | string;
}

interface DeepDiveData {
  round_swing: number;
  impact: number;
  impact_percentage: number;
  round_swing_percent: number;
  opening_duels: OpeningDuelsData;
}

interface PlayerStatsData {
  player_complexion?: PlayerComplexion;
  trades?: TradesData;
  clutch_stats?: ClutchData;
  deep_dive?: DeepDiveData;
  players: Player[];
  current_user_steam_id: string;
}

interface Player {
  steam_id: string;
  name: string;
}

interface MatchPlayerStatsProps {
  matchId: number;
  selectedPlayerId: string | undefined;
}

export function MatchPlayerStats({
  matchId,
  selectedPlayerId,
}: MatchPlayerStatsProps) {
  const [data, setData] = useState<PlayerStatsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [initialLoad, setInitialLoad] = useState(true);
  const [filters, setFilters] = useState({
    playerSteamId: selectedPlayerId ?? '',
  });

  const fetchData = useCallback(
    async (isInitialLoad = false) => {
      try {
        setLoading(true);
        setError(null);

        const params = new URLSearchParams();
        if (filters.playerSteamId) {
          params.append('player_steam_id', filters.playerSteamId);
        }

        const response = await api.get<PlayerStatsData>(
          `/matches/${matchId}/player-stats?${params.toString()}`,
          { requireAuth: true }
        );

        setData(response.data);

        // Set default player selection on initial load
        if (
          isInitialLoad &&
          !filters.playerSteamId &&
          response.data.players.length > 0
        ) {
          const currentUserInMatch = response.data.players.find(
            player => player.steam_id === response.data.current_user_steam_id
          );
          const selectedPlayerSteamId = currentUserInMatch
            ? response.data.current_user_steam_id
            : response.data.players[0].steam_id;

          setFilters(prev => ({
            ...prev,
            playerSteamId: selectedPlayerSteamId,
          }));
          setInitialLoad(false);
        }
      } catch (err: unknown) {
        console.error('Error fetching player stats:', err);
        setError(
          err instanceof Error ? err.message : 'Failed to load player stats'
        );
      } finally {
        setLoading(false);
      }
    },
    [matchId, filters.playerSteamId]
  );

  useEffect(() => {
    if (initialLoad) {
      fetchData(true);
    } else if (filters.playerSteamId) {
      fetchData(false);
    }
  }, [matchId, filters.playerSteamId, fetchData, initialLoad]);

  const handleFiltersChange = useCallback((newFilters: typeof filters) => {
    setFilters(newFilters);
  }, []);

  const LoadingSkeleton = () => (
    <div className="space-y-6">
      <div className="animate-pulse grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="h-80 bg-gray-700 rounded"></div>
        <div className="h-80 bg-gray-700 rounded"></div>
        <div className="h-80 bg-gray-700 rounded"></div>
        <div className="h-80 bg-gray-700 rounded"></div>
      </div>
    </div>
  );

  // Don't render if no players available
  if (data && data.players.length === 0) {
    return (
      <div className="text-center py-8">
        <p className="text-gray-400">No players available for this match.</p>
      </div>
    );
  }

  if (loading && initialLoad) {
    return <LoadingSkeleton />;
  }

  if (error) {
    return (
      <div className="text-center py-8">
        <p className="text-red-400 mb-4">{error}</p>
        <button
          onClick={() => fetchData(false)}
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Retry
        </button>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="text-center py-8">
        <p className="text-gray-400">No player statistics available</p>
      </div>
    );
  }

  // If we have players but no stats data yet, show loading
  if (data.players.length > 0 && !data.player_complexion) {
    return <LoadingSkeleton />;
  }

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="space-y-4">
          <PlayerStatsFilters
            players={data.players}
            filters={filters}
            onFiltersChange={handleFiltersChange}
          />
          <DeepDiveStats
            deepDive={data.deep_dive}
            openingTiming={data.deep_dive?.opening_duels}
          />
        </div>

        {data.player_complexion && (
          <ComplexionStats complexion={data.player_complexion} />
        )}

        <Card>
          <CardContent className="relative">
            <div className="flex items-center justify-between mb-4">
              <CardTitle className="mb-0">Duels Analysis</CardTitle>
              <div className="group relative">
                <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
                <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                  <div className="text-sm">
                    <div className="font-semibold text-white mb-2">
                      Duels Analysis
                    </div>
                    <div className="text-gray-300 mb-3">
                      Analyses your trading performance and duel outcomes. Shows
                      how well you capitalise on teammate deaths and trade
                      potential.
                    </div>
                    <div className="text-xs text-gray-400 mb-3">
                      <div className="font-medium mb-1">Key Metrics:</div>
                      <div className="space-y-1">
                        <div>
                          <span className="text-green-400">Trades:</span>{' '}
                          Trading a teammates death
                        </div>
                        <div>
                          <span className="text-red-400">Traded Deaths:</span>{' '}
                          Deaths that were avenged
                        </div>
                      </div>
                    </div>
                    <div className="text-xs text-gray-400">
                      <div className="font-medium mb-1">Interpretation:</div>
                      <div>
                        Higher trade ratios = better at capitalising on teammate
                        deaths and setting up your team for a trade.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            {data.trades ? (
              <TradeStatsChart tradeData={data.trades} />
            ) : (
              <div className="flex items-center justify-center h-32">
                <div className="text-gray-400">Loading duels data...</div>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardContent className="relative">
            <div className="flex items-center justify-between mb-4">
              <CardTitle className="mb-0">Clutch Performance</CardTitle>
              <div className="group relative">
                <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
                <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                  <div className="text-sm">
                    <div className="font-semibold text-white mb-2">
                      Clutch Performance
                    </div>
                    <div className="text-gray-300 mb-3">
                      Shows your performance in clutch situations (1vX
                      Scenarios). Higher percentages indicate better clutch
                      ability.
                    </div>
                    <div className="text-xs text-gray-400">
                      <div className="font-medium mb-1">Interpretation:</div>
                      <div>Higher win percentages = better clutch player.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            {data.clutch_stats ? (
              <ClutchChart clutchData={data.clutch_stats} />
            ) : (
              <div className="flex items-center justify-center h-32">
                <div className="text-gray-400">Loading clutch data...</div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
