import { useState, useEffect, useCallback } from 'react';
import { api } from '@/lib/api';
import { AccuracyGraph } from './aim-tracking/accuracy-graph';
import { AimTrackingFilters } from './aim-tracking/aim-tracking-filters';
import { AimRatingGauge } from './aim-tracking/aim-rating-gauge';
import { AimStatsCard } from './aim-tracking/aim-stats-card';

interface Player {
  steam_id: string;
  name: string;
}

interface HitData {
  head_hits_total: number;
  upper_chest_hits_total: number;
  chest_hits_total: number;
  legs_hits_total: number;
}

interface AimTrackingData extends HitData {
  match_id: number;
  player_steam_id: string;
  shots_fired: number;
  shots_hit: number;
  accuracy_all_shots: number;
  spraying_shots_fired: number;
  spraying_shots_hit: number;
  spraying_accuracy: number;
  average_crosshair_placement_x: number;
  average_crosshair_placement_y: number;
  headshot_accuracy: number;
  average_time_to_damage: number;
  aim_rating: number;
}

interface Weapon {
  value: string;
  label: string;
}

interface AimTrackingResponse {
  players?: Player[];
  weapons?: Weapon[];
  current_user_steam_id?: string;
}

interface AimTrackingProps {
  matchId: number;
}

export const AimTracking = ({ matchId }: AimTrackingProps) => {
  const [data, setData] = useState<
    (AimTrackingData & AimTrackingResponse) | null
  >(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [initialLoad, setInitialLoad] = useState(true);
  const [loadingWeapons, setLoadingWeapons] = useState(false);
  const [selectedPlayer, setSelectedPlayer] = useState('');

  const fetchData = useCallback(
    async (isInitialLoad = false) => {
      try {
        setLoading(true);
        setError(null);

        // First load - get player list
        if (isInitialLoad) {
          const playersResponse = await api.get<AimTrackingResponse>(
            `/matches/${matchId}/player-stats`,
            { requireAuth: true }
          );

          if (
            playersResponse.data.players &&
            playersResponse.data.players.length > 0
          ) {
            const currentUserInMatch = playersResponse.data.players.find(
              player =>
                player.steam_id === playersResponse.data.current_user_steam_id
            );
            const selectedPlayerSteamId = currentUserInMatch
              ? playersResponse.data.current_user_steam_id!
              : playersResponse.data.players[0].steam_id;

            setSelectedPlayer(selectedPlayerSteamId);
            setData(playersResponse.data as any);
            setInitialLoad(false);

            // Fetch weapons for the selected player
            await fetchWeapons(selectedPlayerSteamId);
            return;
          }
        }

        // Fetch aim tracking data (stats and rating)
        if (selectedPlayer) {
          const params = new URLSearchParams();
          params.append('player_steam_id', selectedPlayer);

          try {
            const response = await api.get<AimTrackingData>(
              `/matches/${matchId}/aim-tracking?${params.toString()}`,
              { requireAuth: true }
            );

            setData(prev => ({
              ...response.data,
              players: prev?.players,
              weapons: prev?.weapons,
              current_user_steam_id: prev?.current_user_steam_id,
            }));
          } catch (apiError: any) {
            // If 404, it means no aim tracking data exists for this player
            if (apiError.response?.status === 404) {
              setData(
                prev =>
                  ({
                    players: prev?.players,
                    weapons: prev?.weapons,
                    current_user_steam_id: prev?.current_user_steam_id,
                  }) as any
              );
              setError('No aim tracking data available for this player.');
            } else {
              throw apiError;
            }
          }
        }
      } catch (err: unknown) {
        console.error('Error fetching aim tracking data:', err);
        setError(
          err instanceof Error
            ? err.message
            : 'Failed to load aim tracking data'
        );
      } finally {
        setLoading(false);
      }
    },
    [matchId, selectedPlayer]
  );

  const fetchWeapons = useCallback(
    async (playerSteamId: string) => {
      if (!playerSteamId) return;

      try {
        setLoadingWeapons(true);
        const params = new URLSearchParams();
        params.append('player_steam_id', playerSteamId);

        const response = await api.get<AimTrackingResponse>(
          `/matches/${matchId}/aim-tracking/filter-options?${params.toString()}`,
          { requireAuth: true }
        );

        setData(prev => ({ ...prev, weapons: response.data.weapons }) as any);
      } catch (err: unknown) {
        console.error('Error fetching weapons:', err);
      } finally {
        setLoadingWeapons(false);
      }
    },
    [matchId]
  );

  useEffect(() => {
    if (initialLoad) {
      fetchData(true);
    } else if (selectedPlayer) {
      fetchData(false);
    }
  }, [matchId, selectedPlayer, fetchData, initialLoad]);

  const handlePlayerChange = useCallback(
    (playerSteamId: string) => {
      setSelectedPlayer(playerSteamId);
      fetchWeapons(playerSteamId);
    },
    [fetchWeapons]
  );

  const LoadingSkeleton = () => (
    <div className="p-6">
      <div className="animate-pulse space-y-4">
        <div className="h-80 bg-gray-700 rounded"></div>
        <div className="h-96 bg-gray-700 rounded"></div>
      </div>
    </div>
  );

  if (loading && initialLoad) {
    return <LoadingSkeleton />;
  }

  if (error && !data?.players) {
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

  if (!data || !data.players || data.players.length === 0) {
    return (
      <div className="text-center py-8">
        <p className="text-gray-400">No players available for this match.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Left Column - Filters, Stats, and Rating */}
        <div className="space-y-4">
          <AimTrackingFilters
            players={data.players}
            selectedPlayer={selectedPlayer}
            onPlayerChange={handlePlayerChange}
          />

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {data.headshot_accuracy !== undefined && (
              <AimStatsCard
                headshotAccuracy={data.headshot_accuracy}
                sprayingAccuracy={data.spraying_accuracy}
                crosshairPlacementX={data.average_crosshair_placement_x}
                crosshairPlacementY={data.average_crosshair_placement_y}
                averageTimeToDamage={data.average_time_to_damage}
              />
            )}

            {data.aim_rating !== undefined && (
              <AimRatingGauge aimRating={data.aim_rating} />
            )}
          </div>
        </div>

        {/* Right Column - Accuracy Graph */}
        <AccuracyGraph
          matchId={matchId}
          playerSteamId={selectedPlayer}
          weapons={data.weapons || []}
          loadingWeapons={loadingWeapons}
        />
      </div>
    </div>
  );
};
