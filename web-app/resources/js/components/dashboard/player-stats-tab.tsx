import { useState, useEffect } from 'react';
import * as React from 'react';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { StatCard, StatWithTrend } from './stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { StackedBarChart } from '@/components/charts/stacked-bar-chart';
import { cn } from '@/lib/utils';
import {
    getAvgKillsColor,
    getAvgDeathsColor,
    getKdColor,
    getAdrColor,
    getOpeningKillsColor,
    getOpeningDeathsColor,
    getWinRateColor,
} from '@/lib/utils';

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
    basic_stats: {
        total_kills: StatWithTrend;
        total_deaths: StatWithTrend;
        average_kills: StatWithTrend;
        average_deaths: StatWithTrend;
        average_kd: StatWithTrend;
        average_adr: StatWithTrend;
    };
    high_level_stats: {
        average_impact: StatWithTrend;
        average_round_swing: StatWithTrend;
    };
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
                const response = await api.get('/dashboard/player-stats', {
                    params: {
                        date_from: filters.date_from || undefined,
                        date_to: filters.date_to || undefined,
                        game_type: filters.game_type === 'all' ? undefined : filters.game_type,
                        map: filters.map === 'all' ? undefined : filters.map,
                        past_match_count: filters.past_match_count,
                    },
                    requireAuth: true,
                });
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

    const renderBasicStat = (label: string, stat: StatWithTrend, colorClass?: string) => {
        const getTrendIcon = () => {
            if (stat.trend === 'up') return <TrendingUp className="h-4 w-4 text-green-500" />;
            if (stat.trend === 'down') return <TrendingDown className="h-4 w-4 text-red-500" />;
            return <Minus className="h-4 w-4 text-gray-400" />;
        };

        const getTrendColor = () => {
            if (stat.trend === 'up') return 'text-green-500';
            if (stat.trend === 'down') return 'text-red-500';
            return 'text-gray-400';
        };

        return (
            <div className="space-y-1">
                <p className="text-sm text-muted-foreground">{label}</p>
                <div className="flex items-baseline gap-2">
                    <p className={cn("text-2xl font-bold", colorClass)}>{stat.value}</p>
                    {stat.change > 0 && (
                        <div className="flex items-center gap-1">
                            {getTrendIcon()}
                            <span className={`text-sm ${getTrendColor()}`}>
                                {stat.change}%
                            </span>
                        </div>
                    )}
                </div>
            </div>
        );
    };

    const getColorFromClass = (colorClass?: string): string => {
        if (colorClass?.includes('red-600')) return '#dc2626';
        if (colorClass?.includes('orange')) return '#f97316';
        if (colorClass?.includes('green-600')) return '#16a34a';
        if (colorClass?.includes('green-500')) return '#22c55e';
        return '#6b7280';
    };

    const prepareOpeningChartData = () => {
        if (!data) return [];

        return [
            {
                name: 'Total',
                openingKills: data.opening_stats.total_opening_kills.value,
                openingDeaths: data.opening_stats.total_opening_deaths.value,
            },
            {
                name: 'Average',
                openingKills: data.opening_stats.average_opening_kills.value,
                openingDeaths: data.opening_stats.average_opening_deaths.value,
            },
        ];
    };

    const prepareTradingChartData = () => {
        if (!data) return [];

        const totalSuccessfulTrades = data.trading_stats.total_trades.value;
        const totalUnsuccessfulTrades = data.trading_stats.total_possible_trades.value - totalSuccessfulTrades;

        const totalTradedDeaths = data.trading_stats.total_traded_deaths.value;
        const totalUntradedDeaths = data.trading_stats.total_possible_traded_deaths.value - totalTradedDeaths;

        const avgSuccessfulTrades = data.trading_stats.average_trades.value;
        const avgUnsuccessfulTrades = data.trading_stats.average_possible_trades.value - avgSuccessfulTrades;

        const avgTradedDeaths = data.trading_stats.average_traded_deaths.value;
        const avgUntradedDeaths = data.trading_stats.average_possible_traded_deaths.value - avgTradedDeaths;

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
            {/* Basic Stats Section */}
            <Card>
                <CardHeader>
                    <CardTitle>Basic Stats</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">
                        {renderBasicStat('Total Kills', data.basic_stats.total_kills, getAvgKillsColor(data.basic_stats.average_kills.value))}
                        {renderBasicStat('Total Deaths', data.basic_stats.total_deaths, getAvgDeathsColor(data.basic_stats.average_deaths.value))}
                        {renderBasicStat('Average Kills', data.basic_stats.average_kills, getAvgKillsColor(data.basic_stats.average_kills.value))}
                        {renderBasicStat('Average Deaths', data.basic_stats.average_deaths, getAvgDeathsColor(data.basic_stats.average_deaths.value))}
                        {renderBasicStat('Average K/D', data.basic_stats.average_kd, getKdColor(data.basic_stats.average_kd.value))}
                        {renderBasicStat('Average ADR', data.basic_stats.average_adr, getAdrColor(data.basic_stats.average_adr.value))}
                    </div>
                </CardContent>
            </Card>

            {/* High Level Stats Section */}
            <div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <StatCard
                        title="Average Impact Rating"
                        stat={data.high_level_stats.average_impact}
                    />
                    <StatCard
                        title="Average Round Swing %"
                        stat={data.high_level_stats.average_round_swing}
                        suffix="%"
                    />
                </div>
            </div>

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
                                }
                            ]}
                            height={250}
                            showLegend={true}
                            showGrid={true}
                            xAxisLabel="Category"
                            yAxisLabel="Count"
                        />

                        {/* Win Percentages */}
                        <div className="grid grid-cols-2 gap-4 pt-4 border-t">
                            <div className="text-center">
                                <div className="text-xs text-muted-foreground mb-1">Opening Duel Win Rate</div>
                                <div className={cn("text-2xl font-bold", getWinRateColor(data.opening_stats.opening_duel_winrate.value))}>
                                    {data.opening_stats.opening_duel_winrate.value}%
                                </div>
                            </div>
                            <div className="text-center">
                                <div className="text-xs text-muted-foreground mb-1">Avg Duel Win Rate</div>
                                <div className={cn("text-2xl font-bold", getWinRateColor(data.opening_stats.average_duel_winrate.value))}>
                                    {data.opening_stats.average_duel_winrate.value}%
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
                                }
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
                                <div className={cn("text-2xl font-bold", getWinRateColor(data.trading_stats.average_trade_success_rate.value))}>
                                    {data.trading_stats.total_trades.value} / {data.trading_stats.total_possible_trades.value}
                                </div>
                                <div className="text-xs text-muted-foreground mt-1">
                                    {data.trading_stats.average_trade_success_rate.value}%
                                </div>
                            </div>
                            <div className="text-center">
                                <div className="text-xs text-muted-foreground mb-1">Traded Deaths</div>
                                <div className={cn("text-2xl font-bold", getWinRateColor(data.trading_stats.average_traded_death_success_rate.value))}>
                                    {data.trading_stats.total_traded_deaths.value} / {data.trading_stats.total_possible_traded_deaths.value}
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
            <Skeleton className="h-64" />
            <div>
                <Skeleton className="h-6 w-24 mb-4" />
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Skeleton className="h-32" />
                    <Skeleton className="h-32" />
                </div>
            </div>
            <Skeleton className="h-80" />
            <Skeleton className="h-80" />
            <Skeleton className="h-96" />
        </div>
    );
};
