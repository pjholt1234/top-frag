import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { StatWithTrend } from './stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { StackedBarChart } from '@/components/charts/stacked-bar-chart';
import { cn } from '@/lib/utils';
import { getWinRateColor } from '@/lib/utils';

interface ClutchStat {
  total: number;
  attempts: number;
  winrate: number;
}

interface ClutchStats {
  '1v1': ClutchStat;
  '1v2': ClutchStat;
  '1v3': ClutchStat;
  '1v4': ClutchStat;
  '1v5': ClutchStat;
  overall: ClutchStat;
}

interface PlayerStatsData {
  opening_stats: {
    total_opening_kills: StatWithTrend;
    total_opening_deaths: StatWithTrend;
    opening_duel_winrate: StatWithTrend;
    average_opening_kills: StatWithTrend;
    average_opening_deaths: StatWithTrend;
    average_duel_winrate: StatWithTrend;
  };
  trading_stats: {
    total_trades: StatWithTrend;
    total_possible_trades: StatWithTrend;
    total_traded_deaths: StatWithTrend;
    total_possible_traded_deaths: StatWithTrend;
    average_trades: StatWithTrend;
    average_possible_trades: StatWithTrend;
    average_traded_deaths: StatWithTrend;
    average_possible_traded_deaths: StatWithTrend;
    average_trade_success_rate: StatWithTrend;
    average_traded_death_success_rate: StatWithTrend;
  };
  clutch_stats: ClutchStats;
}

interface PlayerStatsTabProps {
  filters: DashboardFilters;
}

export const PlayerStatsTab = ({ filters }: PlayerStatsTabProps) => {
  const [data, setData] = useState<PlayerStatsData | null>(null);
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

        const response = await api.get<PlayerStatsData>(
          '/dashboard/player-stats',
          {
            params,
            requireAuth: true,
          }
        );
        setData(response.data);
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to load player stats');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [filters]);

  if (loading) {
    return <PlayerStatsTabSkeleton />;
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

  const prepareOpeningChartData = () => {
    if (!data) return [];

    return [
      {
        name: 'Total',
        openingKills: data.opening_stats.total_opening_kills.value,
        openingDeaths: data.opening_stats.total_opening_deaths.value,
      },
      {
        name: 'Average Per Match',
        openingKills: data.opening_stats.average_opening_kills.value,
        openingDeaths: data.opening_stats.average_opening_deaths.value,
      },
    ];
  };

  const prepareTradingChartData = () => {
    if (!data) return [];

    const totalSuccessfulTrades = Number(data.trading_stats.total_trades.value);
    const totalUnsuccessfulTrades =
      Number(data.trading_stats.total_possible_trades.value) -
      totalSuccessfulTrades;

    const totalTradedDeaths = Number(
      data.trading_stats.total_traded_deaths.value
    );
    const totalUntradedDeaths =
      Number(data.trading_stats.total_possible_traded_deaths.value) -
      totalTradedDeaths;

    const avgSuccessfulTrades = Number(data.trading_stats.average_trades.value);
    const avgUnsuccessfulTrades =
      Number(data.trading_stats.average_possible_trades.value) -
      avgSuccessfulTrades;

    const avgTradedDeaths = Number(
      data.trading_stats.average_traded_deaths.value
    );
    const avgUntradedDeaths =
      Number(data.trading_stats.average_possible_traded_deaths.value) -
      avgTradedDeaths;

    return [
      {
        name: 'Total Trades',
        successful: totalSuccessfulTrades,
        unsuccessful: totalUnsuccessfulTrades,
      },
      {
        name: 'Avg Trades',
        successful: avgSuccessfulTrades,
        unsuccessful: avgUnsuccessfulTrades,
      },
      {
        name: 'Total Traded Deaths',
        successful: totalTradedDeaths,
        unsuccessful: totalUntradedDeaths,
      },
      {
        name: 'Avg Traded Deaths',
        successful: avgTradedDeaths,
        unsuccessful: avgUntradedDeaths,
      },
    ];
  };

  return (
    <div className="space-y-6">
      {/* Opening & Trading Stats Section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Opening Stats */}
        <Card>
          <CardHeader>
            <CardTitle>Opening</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            <StackedBarChart
              data={prepareOpeningChartData()}
              bars={[
                {
                  dataKey: 'openingKills',
                  name: 'Opening Kills',
                  color: '#10B981', // green-500
                },
                {
                  dataKey: 'openingDeaths',
                  name: 'Opening Deaths',
                  color: '#EF4444', // red-500
                },
              ]}
              height={250}
              showLegend={true}
              showGrid={true}
              xAxisLabel="Category"
              yAxisLabel="Count"
            />

            {/* Win Percentages */}
            <div className="pt-4 border-t">
              <div className="text-center">
                <div className="text-xs text-muted-foreground mb-1">
                  Opening Duel Win Rate
                </div>
                <div
                  className={cn(
                    'text-2xl font-bold',
                    getWinRateColor(
                      Number(data.opening_stats.opening_duel_winrate.value)
                    )
                  )}
                >
                  {data.opening_stats.opening_duel_winrate.value}%
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Trading Stats */}
        <Card>
          <CardHeader>
            <CardTitle>Trading</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            <StackedBarChart
              data={prepareTradingChartData()}
              bars={[
                {
                  dataKey: 'successful',
                  name: 'Successful',
                  color: '#10B981', // green-500
                },
                {
                  dataKey: 'unsuccessful',
                  name: 'Unsuccessful',
                  color: '#EF4444', // red-500
                },
              ]}
              height={250}
              showLegend={true}
              showGrid={true}
              xAxisLabel="Category"
              yAxisLabel="Count"
            />

            {/* Success Rates */}
            <div className="grid grid-cols-2 gap-4 pt-4 border-t">
              <div className="text-center">
                <div className="text-xs text-muted-foreground mb-1">Trades</div>
                <div
                  className={cn(
                    'text-2xl font-bold',
                    getWinRateColor(
                      Number(
                        data.trading_stats.average_trade_success_rate.value
                      )
                    )
                  )}
                >
                  {data.trading_stats.total_trades.value} /{' '}
                  {data.trading_stats.total_possible_trades.value}
                </div>
                <div className="text-xs text-muted-foreground mt-1">
                  {data.trading_stats.average_trade_success_rate.value}%
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-muted-foreground mb-1">
                  Traded Deaths
                </div>
                <div
                  className={cn(
                    'text-2xl font-bold',
                    getWinRateColor(
                      Number(
                        data.trading_stats.average_traded_death_success_rate
                          .value
                      )
                    )
                  )}
                >
                  {data.trading_stats.total_traded_deaths.value} /{' '}
                  {data.trading_stats.total_possible_traded_deaths.value}
                </div>
                <div className="text-xs text-muted-foreground mt-1">
                  {data.trading_stats.average_traded_death_success_rate.value}%
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Clutches Section */}
      <div>
        <Card>
          <CardHeader>
            <CardTitle>Clutch Statistics</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2 px-4">Situation</th>
                    <th className="text-center py-2 px-4">Total Wins</th>
                    <th className="text-center py-2 px-4">Attempts</th>
                    <th className="text-center py-2 px-4">Win Rate</th>
                  </tr>
                </thead>
                <tbody>
                  <tr className="border-b">
                    <td className="py-2 px-4 font-medium">1v1</td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v1'].total}
                    </td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v1'].attempts}
                    </td>
                    <td className="text-center py-2 px-4 font-semibold">
                      {data.clutch_stats['1v1'].winrate}%
                    </td>
                  </tr>
                  <tr className="border-b">
                    <td className="py-2 px-4 font-medium">1v2</td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v2'].total}
                    </td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v2'].attempts}
                    </td>
                    <td className="text-center py-2 px-4 font-semibold">
                      {data.clutch_stats['1v2'].winrate}%
                    </td>
                  </tr>
                  <tr className="border-b">
                    <td className="py-2 px-4 font-medium">1v3</td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v3'].total}
                    </td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v3'].attempts}
                    </td>
                    <td className="text-center py-2 px-4 font-semibold">
                      {data.clutch_stats['1v3'].winrate}%
                    </td>
                  </tr>
                  <tr className="border-b">
                    <td className="py-2 px-4 font-medium">1v4</td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v4'].total}
                    </td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v4'].attempts}
                    </td>
                    <td className="text-center py-2 px-4 font-semibold">
                      {data.clutch_stats['1v4'].winrate}%
                    </td>
                  </tr>
                  <tr className="border-b">
                    <td className="py-2 px-4 font-medium">1v5</td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v5'].total}
                    </td>
                    <td className="text-center py-2 px-4">
                      {data.clutch_stats['1v5'].attempts}
                    </td>
                    <td className="text-center py-2 px-4 font-semibold">
                      {data.clutch_stats['1v5'].winrate}%
                    </td>
                  </tr>
                  <tr className="bg-muted/50">
                    <td className="py-2 px-4 font-bold">Overall</td>
                    <td className="text-center py-2 px-4 font-bold">
                      {data.clutch_stats.overall.total}
                    </td>
                    <td className="text-center py-2 px-4 font-bold">
                      {data.clutch_stats.overall.attempts}
                    </td>
                    <td className="text-center py-2 px-4 font-bold">
                      {data.clutch_stats.overall.winrate}%
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

const PlayerStatsTabSkeleton = () => {
  return (
    <div className="space-y-6">
      <Skeleton className="h-80" />
      <Skeleton className="h-80" />
      <Skeleton className="h-96" />
    </div>
  );
};
