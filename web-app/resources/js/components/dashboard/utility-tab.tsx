import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { StatCard, StatWithTrend } from './stat-card';
import { Skeleton } from '@/components/ui/skeleton';

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
                const response = await api.get('/dashboard/utility-stats', {
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
                    />
                    <StatCard
                        title="Avg Friendly Blind Duration"
                        stat={data.avg_blind_duration_friendly}
                        suffix="s"
                        lowerIsBetter={true}
                    />
                    <StatCard
                        title="Avg Enemy Players Blinded"
                        stat={data.avg_players_blinded_enemy}
                    />
                    <StatCard
                        title="Avg Friendly Players Blinded"
                        stat={data.avg_players_blinded_friendly}
                        lowerIsBetter={true}
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
                    />
                    <StatCard
                        title="Grenade Effectiveness"
                        stat={data.grenade_effectiveness}
                        suffix="%"
                    />
                    <StatCard
                        title="Average Grenade Usage"
                        stat={data.average_grenade_usage}
                    />
                </div>
            </div>

            <div className="text-sm text-muted-foreground mt-6 p-4 bg-muted rounded-lg">
                <p className="mb-2">
                    <strong>Blind Duration:</strong> Total time enemies/teammates were blinded by your flashes
                </p>
                <p className="mb-2">
                    <strong>Players Blinded:</strong> Average number of players affected per flashbang
                </p>
                <p className="mb-2">
                    <strong>HE + Molotov Damage:</strong> Total damage dealt with explosive and incendiary grenades
                </p>
                <p>
                    <strong>Grenade Effectiveness:</strong> Overall utility impact score based on timing and placement
                </p>
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

