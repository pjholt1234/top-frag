import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { StatCard, StatWithTrend } from './stat-card';
import { Skeleton } from '@/components/ui/skeleton';
import {
  getFlashDurationColor,
  getPlayersBlindedColor,
  getHeMolotovDamageColor,
  getGrenadeEffectivenessColor,
  getGrenadeUsageColor,
} from '@/lib/utils';

interface UtilityData {
  avg_blind_duration_enemy: StatWithTrend;
  avg_blind_duration_friendly: StatWithTrend;
  avg_players_blinded_enemy: StatWithTrend;
  avg_players_blinded_friendly: StatWithTrend;
  he_molotov_damage: StatWithTrend;
  grenade_effectiveness: StatWithTrend;
  average_grenade_usage: StatWithTrend;
}

interface UtilityTabProps {
  filters: DashboardFilters;
}

export const UtilityTab = ({ filters }: UtilityTabProps) => {
  const [data, setData] = useState<UtilityData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      setError(null);

      try {
        const response = await api.get('/utility', {
          params: {
            date_from: filters.date_from || undefined,
            date_to: filters.date_to || undefined,
            game_type:
              filters.game_type === 'all' ? undefined : filters.game_type,
            map: filters.map === 'all' ? undefined : filters.map,
            past_match_count: filters.past_match_count,
          },
          requireAuth: true,
        });
        setData(response.data);
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to load utility stats');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [filters]);

  if (loading) {
    return <UtilityTabSkeleton />;
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <p className="text-red-500">{error}</p>
      </div>
    );
  }

  if (!data) {
    return null;
  }

  return (
    <div className="space-y-6">
      {/* Flash Stats Section */}
      <div>
        <h2 className="text-xl font-semibold mb-4">Flash Stats</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            title="Avg Enemy Blind Duration"
            stat={data.avg_blind_duration_enemy}
            suffix="s"
            valueClassName={getFlashDurationColor(
              typeof data.avg_blind_duration_enemy === 'object'
                ? (data.avg_blind_duration_enemy.value as number)
                : (data.avg_blind_duration_enemy as number),
              true
            )}
          />
          <StatCard
            title="Avg Friendly Blind Duration"
            stat={data.avg_blind_duration_friendly}
            suffix="s"
            lowerIsBetter={true}
            valueClassName={getFlashDurationColor(
              typeof data.avg_blind_duration_friendly === 'object'
                ? (data.avg_blind_duration_friendly.value as number)
                : (data.avg_blind_duration_friendly as number),
              false
            )}
          />
          <StatCard
            title="Avg Enemy Players Blinded"
            stat={data.avg_players_blinded_enemy}
            valueClassName={getPlayersBlindedColor(
              typeof data.avg_players_blinded_enemy === 'object'
                ? (data.avg_players_blinded_enemy.value as number)
                : (data.avg_players_blinded_enemy as number),
              true
            )}
          />
          <StatCard
            title="Avg Friendly Players Blinded"
            stat={data.avg_players_blinded_friendly}
            lowerIsBetter={true}
            valueClassName={getPlayersBlindedColor(
              typeof data.avg_players_blinded_friendly === 'object'
                ? (data.avg_players_blinded_friendly.value as number)
                : (data.avg_players_blinded_friendly as number),
              false
            )}
          />
        </div>
      </div>

      {/* Grenade Stats Section */}
      <div>
        <h2 className="text-xl font-semibold mb-4">Grenade Stats</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <StatCard
            title="HE + Molotov Damage"
            stat={data.he_molotov_damage}
            valueClassName={getHeMolotovDamageColor(
              typeof data.he_molotov_damage === 'object'
                ? (data.he_molotov_damage.value as number)
                : (data.he_molotov_damage as number)
            )}
          />
          <StatCard
            title="Grenade Effectiveness"
            stat={data.grenade_effectiveness}
            suffix="%"
            valueClassName={getGrenadeEffectivenessColor(
              typeof data.grenade_effectiveness === 'object'
                ? (data.grenade_effectiveness.value as number)
                : (data.grenade_effectiveness as number)
            )}
          />
          <StatCard
            title="Average Grenade Usage"
            stat={data.average_grenade_usage}
            valueClassName={getGrenadeUsageColor(
              typeof data.average_grenade_usage === 'object'
                ? (data.average_grenade_usage.value as number)
                : (data.average_grenade_usage as number)
            )}
          />
        </div>
      </div>
    </div>
  );
};

const UtilityTabSkeleton = () => {
  return (
    <div className="space-y-6">
      <div>
        <Skeleton className="h-6 w-24 mb-4" />
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
        </div>
      </div>
      <div>
        <Skeleton className="h-6 w-32 mb-4" />
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
        </div>
      </div>
    </div>
  );
};
