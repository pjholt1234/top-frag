import {
  useState,
  useEffect,
  useCallback,
  useMemo,
  Suspense,
  lazy,
} from 'react';
import { api } from '@/lib/api';
import { UtilityFilters } from '@/components/your-matches/utility-filters';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { UtilityStats } from './utility-analysis/utility-stats';

// Lazy load chart components for better performance
const UtilityUsageChart = lazy(() =>
  import('./utility-analysis/utility-usage-chart').then(m => ({
    default: m.UtilityUsageChart,
  }))
);
const GrenadeRatingGauge = lazy(() =>
  import('./utility-analysis/grenade-rating-gauge').then(m => ({
    default: m.GrenadeRatingGauge,
  }))
);
const GrenadeEffectivenessChart = lazy(() =>
  import('./utility-analysis/grenade-effectiveness-chart').then(m => ({
    default: m.GrenadeEffectivenessChart,
  }))
);
const GrenadeTimingChart = lazy(() =>
  import('./utility-analysis/grenade-timing-chart').then(m => ({
    default: m.GrenadeTimingChart,
  }))
);

interface UtilityAnalysisData {
  utility_usage: Array<{
    type: string;
    count: number;
    percentage: number;
  }>;
  grenade_effectiveness: Array<{
    round: number;
    effectiveness: number;
    total_grenades: number;
  }>;
  grenade_timing: Array<{
    type: string;
    timing_data: Array<{
      round_time: number;
      round_number: number;
      effectiveness: number;
    }>;
  }>;
  overall_stats: {
    overall_grenade_rating: number;
    flash_stats: {
      enemy_avg_duration: number;
      friendly_avg_duration: number;
      enemy_avg_blinded: number;
      friendly_avg_blinded: number;
    };
    he_stats: {
      avg_damage: number;
    };
  };
  players: Array<{
    steam_id: string;
    name: string;
  }>;
  rounds: number[];
  current_user_steam_id: string;
}

interface MatchUtilityAnalysisProps {
  matchId: number;
  selectedPlayerId: string | undefined;
}

export function MatchUtilityAnalysis({
  matchId,
  selectedPlayerId,
}: MatchUtilityAnalysisProps) {
  const [data, setData] = useState<UtilityAnalysisData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [initialLoad, setInitialLoad] = useState(true);
  const [filters, setFilters] = useState({
    playerSteamId: selectedPlayerId ?? '',
    roundNumber: 'all',
  });

  // Memoize filter values to prevent unnecessary re-renders
  const filterValues = useMemo(
    () => ({
      playerSteamId: filters.playerSteamId,
      roundNumber: filters.roundNumber,
    }),
    [filters.playerSteamId, filters.roundNumber]
  );

  const fetchData = useCallback(
    async (isInitialLoad = false) => {
      try {
        setLoading(true);
        setError(null);

        const params = new URLSearchParams();
        if (filters.playerSteamId) {
          params.append('player_steam_id', filters.playerSteamId);
        }
        if (filters.roundNumber !== 'all') {
          params.append('round_number', filters.roundNumber);
        }

        const response = await api.get<UtilityAnalysisData>(
          `/matches/${matchId}/utility-analysis?${params.toString()}`,
          { requireAuth: true }
        );

        setData(response.data);

        // Auto-select current user only on initial load to prevent double API calls
        if (
          isInitialLoad &&
          !filters.playerSteamId &&
          response.data.players.length > 0
        ) {
          const currentUserSteamId = response.data.current_user_steam_id;
          const currentUserInList = response.data.players.find(
            player => player.steam_id === currentUserSteamId
          );
          const selectedPlayerSteamId = currentUserInList
            ? currentUserSteamId
            : response.data.players[0].steam_id;

          setFilters(prev => ({
            ...prev,
            playerSteamId: selectedPlayerSteamId,
          }));
          setInitialLoad(false);
        }
      } catch (err: unknown) {
        console.error('Error fetching utility analysis:', err);
        setError(
          err instanceof Error ? err.message : 'Failed to load utility analysis'
        );
      } finally {
        setLoading(false);
      }
    },
    [matchId, filters.playerSteamId, filters.roundNumber]
  );

  // Separate useEffect for initial load
  useEffect(() => {
    if (initialLoad) {
      fetchData(true);
    }
  }, [matchId, initialLoad, fetchData]);

  // Separate useEffect for filter changes (only after initial load)
  useEffect(() => {
    if (!initialLoad) {
      fetchData(false);
    }
  }, [filterValues, initialLoad, fetchData]);

  const handleFiltersChange = useCallback((newFilters: typeof filters) => {
    setFilters(newFilters);
  }, []);

  // Loading skeleton component
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

  // Chart loading fallback
  const ChartFallback = () => (
    <div className="h-[300px] flex items-center justify-center">
      <div className="animate-pulse bg-gray-700 rounded w-full h-full"></div>
    </div>
  );

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
        <p className="text-gray-400">No utility analysis data available</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="space-y-6">
        {/* First row: Utility Usage and Stats will be side by side */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div>
            <UtilityFilters
              players={data.players}
              rounds={data.rounds}
              filters={filters}
              onFiltersChange={handleFiltersChange}
            />

            <UtilityStats overallStats={data.overall_stats} />
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Card className="h-full">
              <CardContent className="flex items-center justify-center">
                <Suspense fallback={<ChartFallback />}>
                  <GrenadeRatingGauge
                    rating={data.overall_stats.overall_grenade_rating}
                  />
                </Suspense>
              </CardContent>
            </Card>
            <Card>
              <CardContent>
                <Suspense fallback={<ChartFallback />}>
                  <UtilityUsageChart data={data.utility_usage} />
                </Suspense>
              </CardContent>
            </Card>
          </div>
        </div>

        {/* Second row: Grenade Effectiveness and Timing Analysis side by side */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <Card>
            <CardContent className="pt-2">
              <CardTitle className="mb-2">
                Grenade Effectiveness by Round
              </CardTitle>
              <Suspense fallback={<ChartFallback />}>
                <GrenadeEffectivenessChart data={data.grenade_effectiveness} />
              </Suspense>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-2">
              <CardTitle className="mb-2">Grenade Timing Analysis</CardTitle>
              <Suspense fallback={<ChartFallback />}>
                <GrenadeTimingChart data={data.grenade_timing} />
              </Suspense>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
