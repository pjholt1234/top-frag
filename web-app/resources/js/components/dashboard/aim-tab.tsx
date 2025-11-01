import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { StatCard, StatWithTrend } from './stat-card';
import { Skeleton } from '@/components/ui/skeleton';

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

interface AimData {
    aim_statistics: AimStatistics;
    weapon_breakdown: WeaponStat[];
}

interface AimTabProps {
    filters: DashboardFilters;
}

export const AimTab = ({ filters }: AimTabProps) => {
    const [data, setData] = useState<AimData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            setError(null);

            try {
                const response = await api.get('/dashboard/aim-stats', {
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
                setError(err.response?.data?.message || 'Failed to load aim stats');
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [filters]);

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

    return (
        <div className="space-y-6">
            {/* Aim Statistics Section */}
            <div>
                <h2 className="text-xl font-semibold mb-4">Aim Statistics</h2>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <StatCard
                        title="Average Aim Rating"
                        stat={data.aim_statistics.average_aim_rating}
                    />
                    <StatCard
                        title="Headshot Percentage"
                        stat={data.aim_statistics.average_headshot_percentage}
                        suffix="%"
                    />
                    <StatCard
                        title="Spray Accuracy"
                        stat={data.aim_statistics.average_spray_accuracy}
                        suffix="%"
                    />
                    <StatCard
                        title="Crosshair Placement"
                        stat={data.aim_statistics.average_crosshair_placement}
                        suffix="°"
                        lowerIsBetter={true}
                    />
                    <StatCard
                        title="Time to Damage"
                        stat={data.aim_statistics.average_time_to_damage}
                        suffix="s"
                        lowerIsBetter={true}
                    />
                </div>
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

            <div className="text-sm text-muted-foreground mt-6 p-4 bg-muted rounded-lg">
                <p className="mb-2">
                    <strong>Aim Rating:</strong> Overall aim performance score (0-100)
                </p>
                <p className="mb-2">
                    <strong>Spray Accuracy:</strong> Accuracy during continuous fire (3+ shots)
                </p>
                <p className="mb-2">
                    <strong>Crosshair Placement:</strong> How well positioned your crosshair is at head level
                </p>
                <p>
                    <strong>Time to Damage:</strong> Average time from seeing enemy to dealing damage
                </p>
            </div>
        </div>
    );
};

const AimTabSkeleton = () => {
    return (
        <div className="space-y-6">
            <div>
                <Skeleton className="h-6 w-32 mb-4" />
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Skeleton className="h-32" />
                    <Skeleton className="h-32" />
                    <Skeleton className="h-32" />
                    <Skeleton className="h-32" />
                    <Skeleton className="h-32" />
                </div>
            </div>
        </div>
    );
};

