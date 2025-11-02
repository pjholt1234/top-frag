import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { StatWithTrend } from './stat-card';
import { Skeleton } from '@/components/ui/skeleton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { HitDistributionChart } from '@/components/charts/hit-distribution-chart';
import { Info, TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
  getAimRatingColor,
  getHeadshotPercentageColor,
  getSprayAccuracyColor,
  getCrosshairPlacementColor,
  getTimeToDamageColor,
} from '@/lib/utils';

interface AimStatistics {
  average_aim_rating: StatWithTrend;
  average_headshot_percentage: StatWithTrend;
  average_spray_accuracy: StatWithTrend;
  average_crosshair_placement: StatWithTrend;
  average_time_to_damage: StatWithTrend;
}

interface WeaponStat {
  weapon_name: string;
  shots_fired: number;
  shots_hit: number;
  accuracy: number;
  headshot_percentage: number;
}

interface Weapon {
  value: string;
  label: string;
}

interface HitData {
  head_hits_total: number;
  upper_chest_hits_total: number;
  chest_hits_total: number;
  legs_hits_total: number;
}

interface WeaponStats {
  shots_fired: number;
  shots_hit: number;
  accuracy_all_shots: number;
  headshot_accuracy: number;
  spraying_accuracy: number;
}

interface HitDistributionData extends HitData, WeaponStats {}

interface AimData {
  aim_statistics: AimStatistics;
  weapon_breakdown: WeaponStat[];
}

interface AimTabProps {
  filters: DashboardFilters;
}

export const AimTab = ({ filters }: AimTabProps) => {
  const [data, setData] = useState<AimData | null>(null);
  const [weapons, setWeapons] = useState<Weapon[]>([]);
  const [selectedWeapon, setSelectedWeapon] = useState('all');
  const [hitDistribution, setHitDistribution] =
    useState<HitDistributionData | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadingWeapons, setLoadingWeapons] = useState(false);
  const [loadingHitDist, setLoadingHitDist] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Fetch aim statistics
  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      setError(null);

      try {
        const response = await api.get('/aim', {
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
        setError(err.response?.data?.message || 'Failed to load aim stats');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [filters]);

  // Fetch available weapons
  useEffect(() => {
    const fetchWeapons = async () => {
      setLoadingWeapons(true);

      try {
        const response = await api.get('/aim/weapons', {
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
        setWeapons(response.data);
      } catch (err: any) {
        console.error('Failed to load weapons:', err);
      } finally {
        setLoadingWeapons(false);
      }
    };

    fetchWeapons();
  }, [filters]);

  // Fetch hit distribution data
  useEffect(() => {
    const fetchHitDistribution = async () => {
      setLoadingHitDist(true);

      try {
        const params: Record<string, string | number> = {
          past_match_count: filters.past_match_count,
        };

        if (filters.date_from) params.date_from = filters.date_from;
        if (filters.date_to) params.date_to = filters.date_to;
        if (filters.game_type && filters.game_type !== 'all')
          params.game_type = filters.game_type;
        if (filters.map && filters.map !== 'all') params.map = filters.map;
        if (selectedWeapon && selectedWeapon !== 'all')
          params.weapon_name = selectedWeapon;

        const response = await api.get('/aim/hit-distribution', {
          params,
          requireAuth: true,
        });

        if (response.data && Object.keys(response.data).length > 0) {
          setHitDistribution(response.data);
        } else {
          setHitDistribution(null);
        }
      } catch (err: any) {
        console.error('Failed to load hit distribution:', err);
        setHitDistribution(null);
      } finally {
        setLoadingHitDist(false);
      }
    };

    fetchHitDistribution();
  }, [filters, selectedWeapon]);

  if (loading) {
    return <AimTabSkeleton />;
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

  // Helper functions for trend display
  const getTrendIcon = (
    trend: 'up' | 'down' | 'neutral',
    lowerIsBetter: boolean = false
  ) => {
    if (lowerIsBetter) {
      // Inverted: down is good, up is bad
      switch (trend) {
        case 'up':
          return <TrendingUp className="h-4 w-4 text-red-500" />;
        case 'down':
          return <TrendingDown className="h-4 w-4 text-green-500" />;
        case 'neutral':
          return <Minus className="h-4 w-4 text-gray-500" />;
      }
    } else {
      // Normal: up is good, down is bad
      switch (trend) {
        case 'up':
          return <TrendingUp className="h-4 w-4 text-green-500" />;
        case 'down':
          return <TrendingDown className="h-4 w-4 text-red-500" />;
        case 'neutral':
          return <Minus className="h-4 w-4 text-gray-500" />;
      }
    }
  };

  const getTrendColor = (
    trend: 'up' | 'down' | 'neutral',
    lowerIsBetter: boolean = false
  ) => {
    if (lowerIsBetter) {
      // Inverted: down is good, up is bad
      switch (trend) {
        case 'up':
          return 'text-red-500';
        case 'down':
          return 'text-green-500';
        case 'neutral':
          return 'text-gray-500';
      }
    } else {
      // Normal: up is good, down is bad
      switch (trend) {
        case 'up':
          return 'text-green-500';
        case 'down':
          return 'text-red-500';
        case 'neutral':
          return 'text-gray-500';
      }
    }
  };

  return (
    <div className="space-y-6">
      {/* Aim Statistics and Hit Distribution - Side by Side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Aim Statistics Card - Left */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Overview</CardTitle>
              <div className="group relative">
                <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
                <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                  <div className="text-sm">
                    <div className="text-gray-300 space-y-2">
                      <div>
                        <span className="font-medium text-orange-400">
                          Aim Rating:
                        </span>{' '}
                        Overall aim performance score (0-100)
                      </div>
                      <div>
                        <span className="font-medium text-orange-400">
                          Headshot Percentage:
                        </span>{' '}
                        Percentage of hits that landed on the head
                      </div>
                      <div>
                        <span className="font-medium text-orange-400">
                          Spray Accuracy:
                        </span>{' '}
                        Accuracy during continuous fire (3+ shots)
                      </div>
                      <div>
                        <span className="font-medium text-orange-400">
                          Crosshair Placement:
                        </span>{' '}
                        How well positioned your crosshair is at head level
                        (lower is better)
                      </div>
                      <div>
                        <span className="font-medium text-orange-400">
                          Time to Damage:
                        </span>{' '}
                        Average time from seeing enemy to dealing damage (lower
                        is better)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {/* Average Aim Rating */}
              <div className="flex items-center justify-between py-2 border-b">
                <span className="text-sm font-medium text-gray-300">
                  Average Aim Rating
                </span>
                <div className="flex items-center gap-3">
                  <span
                    className={`text-lg font-semibold ${getAimRatingColor(
                      typeof data.aim_statistics.average_aim_rating === 'object'
                        ? (data.aim_statistics.average_aim_rating
                            .value as number)
                        : (data.aim_statistics.average_aim_rating as number)
                    )}`}
                  >
                    {typeof data.aim_statistics.average_aim_rating === 'object'
                      ? data.aim_statistics.average_aim_rating.value
                      : data.aim_statistics.average_aim_rating}
                  </span>
                  {typeof data.aim_statistics.average_aim_rating ===
                    'object' && (
                    <div className="flex items-center gap-1">
                      {getTrendIcon(
                        data.aim_statistics.average_aim_rating.trend
                      )}
                      <span
                        className={cn(
                          'text-sm font-medium',
                          getTrendColor(
                            data.aim_statistics.average_aim_rating.trend
                          )
                        )}
                      >
                        {data.aim_statistics.average_aim_rating.change}%
                      </span>
                    </div>
                  )}
                </div>
              </div>

              {/* Headshot Percentage */}
              <div className="flex items-center justify-between py-2 border-b">
                <span className="text-sm font-medium text-gray-300">
                  Headshot Percentage
                </span>
                <div className="flex items-center gap-3">
                  <span
                    className={`text-lg font-semibold ${getHeadshotPercentageColor(
                      typeof data.aim_statistics.average_headshot_percentage ===
                        'object'
                        ? (data.aim_statistics.average_headshot_percentage
                            .value as number)
                        : (data.aim_statistics
                            .average_headshot_percentage as number)
                    )}`}
                  >
                    {typeof data.aim_statistics.average_headshot_percentage ===
                    'object'
                      ? data.aim_statistics.average_headshot_percentage.value
                      : data.aim_statistics.average_headshot_percentage}
                    %
                  </span>
                  {typeof data.aim_statistics.average_headshot_percentage ===
                    'object' && (
                    <div className="flex items-center gap-1">
                      {getTrendIcon(
                        data.aim_statistics.average_headshot_percentage.trend
                      )}
                      <span
                        className={cn(
                          'text-sm font-medium',
                          getTrendColor(
                            data.aim_statistics.average_headshot_percentage
                              .trend
                          )
                        )}
                      >
                        {data.aim_statistics.average_headshot_percentage.change}
                        %
                      </span>
                    </div>
                  )}
                </div>
              </div>

              {/* Spray Accuracy */}
              <div className="flex items-center justify-between py-2 border-b">
                <span className="text-sm font-medium text-gray-300">
                  Spray Accuracy
                </span>
                <div className="flex items-center gap-3">
                  <span
                    className={`text-lg font-semibold ${getSprayAccuracyColor(
                      typeof data.aim_statistics.average_spray_accuracy ===
                        'object'
                        ? (data.aim_statistics.average_spray_accuracy
                            .value as number)
                        : (data.aim_statistics.average_spray_accuracy as number)
                    )}`}
                  >
                    {typeof data.aim_statistics.average_spray_accuracy ===
                    'object'
                      ? data.aim_statistics.average_spray_accuracy.value
                      : data.aim_statistics.average_spray_accuracy}
                    %
                  </span>
                  {typeof data.aim_statistics.average_spray_accuracy ===
                    'object' && (
                    <div className="flex items-center gap-1">
                      {getTrendIcon(
                        data.aim_statistics.average_spray_accuracy.trend
                      )}
                      <span
                        className={cn(
                          'text-sm font-medium',
                          getTrendColor(
                            data.aim_statistics.average_spray_accuracy.trend
                          )
                        )}
                      >
                        {data.aim_statistics.average_spray_accuracy.change}%
                      </span>
                    </div>
                  )}
                </div>
              </div>

              {/* Crosshair Placement */}
              <div className="flex items-center justify-between py-2 border-b">
                <span className="text-sm font-medium text-gray-300">
                  Crosshair Placement
                </span>
                <div className="flex items-center gap-3">
                  <span
                    className={`text-lg font-semibold ${getCrosshairPlacementColor(
                      typeof data.aim_statistics.average_crosshair_placement ===
                        'object'
                        ? (data.aim_statistics.average_crosshair_placement
                            .value as number)
                        : (data.aim_statistics
                            .average_crosshair_placement as number)
                    )}`}
                  >
                    {typeof data.aim_statistics.average_crosshair_placement ===
                    'object'
                      ? data.aim_statistics.average_crosshair_placement.value
                      : data.aim_statistics.average_crosshair_placement}
                    °
                  </span>
                  {typeof data.aim_statistics.average_crosshair_placement ===
                    'object' && (
                    <div className="flex items-center gap-1">
                      {getTrendIcon(
                        data.aim_statistics.average_crosshair_placement.trend,
                        true // lowerIsBetter
                      )}
                      <span
                        className={cn(
                          'text-sm font-medium',
                          getTrendColor(
                            data.aim_statistics.average_crosshair_placement
                              .trend,
                            true // lowerIsBetter
                          )
                        )}
                      >
                        {data.aim_statistics.average_crosshair_placement.change}
                        %
                      </span>
                    </div>
                  )}
                </div>
              </div>

              {/* Time to Damage */}
              <div className="flex items-center justify-between py-2">
                <span className="text-sm font-medium text-gray-300">
                  Time to Damage
                </span>
                <div className="flex items-center gap-3">
                  <span
                    className={`text-lg font-semibold ${getTimeToDamageColor(
                      typeof data.aim_statistics.average_time_to_damage ===
                        'object'
                        ? (data.aim_statistics.average_time_to_damage
                            .value as number)
                        : (data.aim_statistics.average_time_to_damage as number)
                    )}`}
                  >
                    {typeof data.aim_statistics.average_time_to_damage ===
                    'object'
                      ? data.aim_statistics.average_time_to_damage.value
                      : data.aim_statistics.average_time_to_damage}
                    s
                  </span>
                  {typeof data.aim_statistics.average_time_to_damage ===
                    'object' && (
                    <div className="flex items-center gap-1">
                      {getTrendIcon(
                        data.aim_statistics.average_time_to_damage.trend,
                        true // lowerIsBetter
                      )}
                      <span
                        className={cn(
                          'text-sm font-medium',
                          getTrendColor(
                            data.aim_statistics.average_time_to_damage.trend,
                            true // lowerIsBetter
                          )
                        )}
                      >
                        {data.aim_statistics.average_time_to_damage.change}%
                      </span>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Hit Distribution Card - Right */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Hit Distribution</CardTitle>
              <div className="group relative">
                <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
                <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                  <div className="text-sm">
                    <div className="font-semibold text-white mb-2">
                      Hit Distribution
                    </div>
                    <div className="text-gray-300 mb-3">
                      Aggregated view of where your shots are landing across all
                      filtered matches. Filter by weapon to see specific weapon
                      performance.
                    </div>
                    <div className="text-xs text-gray-400">
                      <div className="font-medium mb-1">Body Regions:</div>
                      <div className="space-y-1">
                        <div>
                          <span className="text-orange-400">Head:</span> Highest
                          damage multiplier
                        </div>
                        <div>
                          <span className="text-orange-400">Upper Chest:</span>{' '}
                          High damage region
                        </div>
                        <div>
                          <span className="text-orange-400">Chest:</span>{' '}
                          Standard damage
                        </div>
                        <div>
                          <span className="text-orange-400">Legs:</span> Lowest
                          damage
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {/* Hit Distribution Chart */}
            {loadingHitDist ? (
              <div className="flex items-center justify-center h-80">
                <Skeleton className="h-full w-full" />
              </div>
            ) : hitDistribution ? (
              <HitDistributionChart
                hitData={{
                  head_hits_total: hitDistribution.head_hits_total,
                  upper_chest_hits_total:
                    hitDistribution.upper_chest_hits_total,
                  chest_hits_total: hitDistribution.chest_hits_total,
                  legs_hits_total: hitDistribution.legs_hits_total,
                }}
                weaponStats={{
                  shots_fired: hitDistribution.shots_fired,
                  shots_hit: hitDistribution.shots_hit,
                  accuracy_all_shots: hitDistribution.accuracy_all_shots,
                  headshot_accuracy: hitDistribution.headshot_accuracy,
                  spraying_accuracy: hitDistribution.spraying_accuracy,
                }}
                showWeaponStats={true}
                weapons={weapons}
                selectedWeapon={selectedWeapon}
                onWeaponChange={setSelectedWeapon}
                loadingWeapons={loadingWeapons}
              />
            ) : (
              <div
                className="flex items-center justify-center h-80 rounded-lg text-gray-400"
                style={{ backgroundColor: 'oklch(0.13 0.028 261.692)' }}
              >
                No hit distribution data available for the selected filters
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Weapon Breakdown Section */}
      {data.weapon_breakdown && data.weapon_breakdown.length > 0 && (
        <div>
          <h2 className="text-xl font-semibold mb-4">Weapon Breakdown</h2>
          <div className="text-sm text-muted-foreground mb-2">
            Coming soon: Detailed weapon statistics
          </div>
        </div>
      )}
    </div>
  );
};

const AimTabSkeleton = () => {
  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <Skeleton className="h-6 w-32" />
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex items-center justify-between py-2 border-b">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-6 w-16" />
              </div>
              <div className="flex items-center justify-between py-2 border-b">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-6 w-16" />
              </div>
              <div className="flex items-center justify-between py-2 border-b">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-6 w-16" />
              </div>
              <div className="flex items-center justify-between py-2 border-b">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-6 w-16" />
              </div>
              <div className="flex items-center justify-between py-2">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-6 w-16" />
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <Skeleton className="h-6 w-32" />
          </CardHeader>
          <CardContent>
            <Skeleton className="h-96" />
          </CardContent>
        </Card>
      </div>
    </div>
  );
};
