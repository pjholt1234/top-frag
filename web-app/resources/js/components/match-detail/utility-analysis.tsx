import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { UtilityFilters } from '@/components/your-matches/utility-filters';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { UtilityUsageChart } from './details/utility-usage-chart';
import { GrenadeRatingGauge } from './details/grenade-rating-gauge';
import { GrenadeEffectivenessChart } from './details/grenade-effectiveness-chart';
import { GrenadeTimingChart } from './details/grenade-timing-chart';
import { UtilityStats } from './details/utility-stats';

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
}

export function MatchUtilityAnalysis({ matchId }: MatchUtilityAnalysisProps) {
  const [data, setData] = useState<UtilityAnalysisData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState({
    playerSteamId: '',
    roundNumber: 'all',
  });

  const fetchData = async () => {
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

      // Auto-select current user if they exist in the player list, otherwise select first player
      if (!filters.playerSteamId && response.data.players.length > 0) {
        // Get current user's steam ID from the API response or auth context
        const currentUserSteamId = response.data.current_user_steam_id;

        // Check if current user exists in the player list
        const currentUserInList = response.data.players.find(
          player => player.steam_id === currentUserSteamId
        );

        // Select current user if found, otherwise select first player
        const selectedPlayerSteamId = currentUserInList
          ? currentUserSteamId
          : response.data.players[0].steam_id;

        setFilters(prev => ({
          ...prev,
          playerSteamId: selectedPlayerSteamId,
        }));
      }
    } catch (err: unknown) {
      console.error('Error fetching utility analysis:', err);
      setError(
        err instanceof Error ? err.message : 'Failed to load utility analysis'
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [matchId, filters]);

  const handleFiltersChange = (newFilters: typeof filters) => {
    setFilters(newFilters);
  };

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="animate-pulse grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div className="h-80 bg-gray-700 rounded"></div>
          <div className="h-80 bg-gray-700 rounded"></div>
          <div className="h-80 bg-gray-700 rounded"></div>
          <div className="h-80 bg-gray-700 rounded"></div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center py-8">
        <p className="text-red-400 mb-4">{error}</p>
        <button
          onClick={fetchData}
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
                <GrenadeRatingGauge
                  rating={data.overall_stats.overall_grenade_rating}
                />
              </CardContent>
            </Card>
            <Card>
              <CardContent>
                <UtilityUsageChart data={data.utility_usage} />
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
              <GrenadeEffectivenessChart data={data.grenade_effectiveness} />
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-2">
              <CardTitle className="mb-2">Grenade Timing Analysis</CardTitle>
              <GrenadeTimingChart data={data.grenade_timing} />
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
