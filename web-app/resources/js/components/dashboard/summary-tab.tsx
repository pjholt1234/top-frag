import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { StatCard, StatWithTrend } from './stat-card';
import { GaugeChart } from '@/components/charts/gauge-chart';
import { PlayerSummaryCard } from './player-summary-card';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { getImpactColor, getRoundSwingColor } from '@/lib/utils';

interface PlayerComplexion {
  opener: number;
  closer: number;
  support: number;
  fragger: number;
}

interface PlayerCardData {
  username: string;
  avatar: string | null;
  average_impact: number;
  average_round_swing: number;
  average_kd: number;
  average_adr: number;
  average_kills: number;
  average_deaths: number;
  total_kills: number;
  total_deaths: number;
  total_matches: number;
  win_percentage: number;
  player_complexion: PlayerComplexion;
}

interface StatImprovement extends StatWithTrend {
  name: string;
}

interface AchievementCounts {
  fragger: number;
  support: number;
  opener: number;
  closer: number;
  top_aimer: number;
  impact_player: number;
  difference_maker: number;
}

interface SummaryData {
  most_improved_stats: StatImprovement[] | null;
  least_improved_stats: StatImprovement[] | null;
  average_aim_rating: {
    value: number;
    max: number;
  };
  average_utility_effectiveness: {
    value: number;
    max: number;
  };
  achievements: AchievementCounts;
  player_card: PlayerCardData;
}

interface SummaryTabProps {
  filters: DashboardFilters;
}

export const SummaryTab = ({ filters }: SummaryTabProps) => {
  const navigate = useNavigate();
  const [data, setData] = useState<SummaryData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      setError(null);

      try {
        const params: Record<string, string | number> = {
          past_match_count: filters.past_match_count,
        };

        if (filters.date_from) params.date_from = filters.date_from;
        if (filters.date_to) params.date_to = filters.date_to;
        if (filters.game_type && filters.game_type !== 'all')
          params.game_type = filters.game_type;
        if (filters.map && filters.map !== 'all') params.map = filters.map;

        const response = await api.get('/dashboard/summary', {
          params,
          requireAuth: true,
        });
        setData(response.data as SummaryData);
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to load summary');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [filters]);

  if (loading) {
    return <SummaryTabSkeleton />;
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
      {/* Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-4">
        {/* Stats Row - Takes 3 columns on large screens */}
        <div className="lg:col-span-3 space-y-4">
          {/* Most/Least Improved Stats */}
          <div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* Most Improved Stats */}
              {data.most_improved_stats &&
              data.most_improved_stats.length > 0 ? (
                data.most_improved_stats.map((stat, index) => (
                  <StatCard
                    key={`improved-${index}`}
                    title={`🔥 ${stat.name}`}
                    stat={stat}
                    className="border-green-500/20"
                  />
                ))
              ) : (
                <Card className="border-gray-500/20 md:col-span-2">
                  <CardContent className="pt-6">
                    <div className="flex flex-col items-center justify-center py-4 text-center">
                      <span className="text-gray-400 text-sm">
                        No improved stats in this period
                      </span>
                      <span className="text-gray-500 text-xs mt-1">
                        Keep practicing to see improvements!
                      </span>
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Least Improved Stats */}
              {data.least_improved_stats &&
              data.least_improved_stats.length > 0 ? (
                data.least_improved_stats.map((stat, index) => (
                  <StatCard
                    key={`needs-work-${index}`}
                    title={`⚠️ ${stat.name}`}
                    stat={stat}
                    className="border-red-500/20"
                  />
                ))
              ) : (
                <Card className="border-gray-500/20 md:col-span-2">
                  <CardContent className="pt-6">
                    <div className="flex flex-col items-center justify-center py-4 text-center">
                      <span className="text-gray-400 text-sm">
                        No declining stats in this period
                      </span>
                      <span className="text-gray-500 text-xs mt-1">
                        Great work! Keep it up!
                      </span>
                    </div>
                  </CardContent>
                </Card>
              )}
            </div>
          </div>

          {/* Gauges */}
          <div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Card
                className="p-0 pb-8 cursor-pointer transition-all hover:scale-105 hover:shadow-lg hover:border-orange-500/50"
                onClick={() => navigate('/aim')}
              >
                <CardContent className="flex justify-center">
                  <GaugeChart
                    title="Avg Aim Rating"
                    currentValue={data.average_aim_rating.value}
                    maxValue={data.average_aim_rating.max}
                    size="lg"
                    color="#f97316"
                    showPercentage={false}
                  />
                </CardContent>
              </Card>

              <Card
                className="p-0 pb-8 cursor-pointer transition-all hover:scale-105 hover:shadow-lg hover:border-blue-500/50"
                onClick={() => navigate('/utility')}
              >
                <CardContent className="flex justify-center">
                  <GaugeChart
                    title="Avg Utility Effectiveness"
                    currentValue={data.average_utility_effectiveness.value}
                    maxValue={data.average_utility_effectiveness.max}
                    size="lg"
                    color="#3b82f6"
                    showPercentage={false}
                  />
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Impact & Round Swing Stats */}
          <div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Card>
                <CardContent>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-400">
                      Average Impact Rating
                    </span>
                    <span
                      className={`text-2xl font-bold ${getImpactColor(data.player_card.average_impact)}`}
                    >
                      {data.player_card.average_impact.toFixed(2)}
                    </span>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardContent>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-400">
                      Average Round Swing
                    </span>
                    <span
                      className={`text-2xl font-bold ${getRoundSwingColor(data.player_card.average_round_swing)}`}
                    >
                      {data.player_card.average_round_swing.toFixed(1)}%
                    </span>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>

        {/* Player Card - Takes 2 columns on large screens */}
        <div className="lg:col-span-2">
          <PlayerSummaryCard
            playerCard={data.player_card}
            achievements={data.achievements}
          />
        </div>
      </div>
    </div>
  );
};

const SummaryTabSkeleton = () => {
  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <div className="lg:col-span-3 space-y-4">
          <div>
            <Skeleton className="h-6 w-48 mb-4" />
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <Skeleton className="h-32" />
              <Skeleton className="h-32" />
              <Skeleton className="h-32" />
              <Skeleton className="h-32" />
            </div>
          </div>

          <div>
            <Skeleton className="h-6 w-48 mb-4" />
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Skeleton className="h-48" />
              <Skeleton className="h-48" />
            </div>
          </div>

          <div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Skeleton className="h-20" />
              <Skeleton className="h-20" />
            </div>
          </div>
        </div>

        <div className="lg:col-span-2">
          <Skeleton className="h-6 w-32 mb-4" />
          <Skeleton className="h-full min-h-96" />
        </div>
      </div>
    </div>
  );
};
