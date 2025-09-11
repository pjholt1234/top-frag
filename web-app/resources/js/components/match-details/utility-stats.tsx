import { getCustomRatingColor } from '@/lib/utils';

interface FlashStats {
    enemy_avg_duration: number;
    friendly_avg_duration: number;
    enemy_avg_blinded: number;
    friendly_avg_blinded: number;
}

interface HeStats {
    avg_damage: number;
}

interface OverallStats {
    overall_grenade_rating: number;
    flash_stats: FlashStats;
    he_stats: HeStats;
}

interface UtilityStatsProps {
    overallStats: OverallStats;
}

export function UtilityStats({ overallStats }: UtilityStatsProps) {
    const getFlashDurationColor = (duration: number, isEnemy: boolean) => {
        if (isEnemy) {
            // For enemy flash duration, higher is better (more effective)
            return getCustomRatingColor(duration, [1, 2, 3, 4], 'text');
        } else {
            // For friendly flash duration, lower is better (less friendly fire)
            return getCustomRatingColor(4 - duration, [1, 2, 3, 4], 'text');
        }
    };

    const getBlindedColor = (count: number, isEnemy: boolean) => {
        if (isEnemy) {
            // For enemy blinded count, higher is better (more effective)
            return getCustomRatingColor(count, [1, 2, 3, 4], 'text');
        } else {
            // For friendly blinded count, lower is better (less friendly fire)
            return getCustomRatingColor(4 - count, [1, 2, 3, 4], 'text');
        }
    };

    const getHeDamageColor = (damage: number) => {
        // For HE damage, higher is better (more effective)
        return getCustomRatingColor(damage, [5, 10, 20, 30], 'text');
    };

    return (
        <>
            <div className="overflow-hidden rounded-lg border border-gray-700 mb-4">
                <table className="w-full table-fixed">
                    <thead className="bg-gray-800/50">
                        <tr>
                            <th className="w-1/3 px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Flashbangs
                            </th>
                            <th className="w-1/3 px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Avg Flash Duration
                            </th>
                            <th className="w-1/3 px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Players Blinded
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-700">
                        <tr>
                            <td className="px-4 py-2 text-sm font-medium text-gray-300">
                                Enemy
                            </td>
                            <td className="px-4 py-2 text-center">
                                <span
                                    className={`text-sm font-medium ${getFlashDurationColor(overallStats.flash_stats.enemy_avg_duration, true)}`}
                                >
                                    {overallStats.flash_stats.enemy_avg_duration.toFixed(2)}s
                                </span>
                            </td>
                            <td className="px-4 py-2 text-center">
                                <span
                                    className={`text-sm font-medium ${getBlindedColor(overallStats.flash_stats.enemy_avg_blinded, true)}`}
                                >
                                    {overallStats.flash_stats.enemy_avg_blinded.toFixed(1)}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td className="px-4 py-2 text-sm font-medium text-gray-300">
                                Friendly
                            </td>
                            <td className="px-4 py-2 text-center">
                                <span
                                    className={`text-sm font-medium ${getFlashDurationColor(overallStats.flash_stats.friendly_avg_duration, false)}`}
                                >
                                    {overallStats.flash_stats.friendly_avg_duration.toFixed(
                                        2
                                    )}
                                    s
                                </span>
                            </td>
                            <td className="px-4 py-2 text-center">
                                <span
                                    className={`text-sm font-medium ${getBlindedColor(overallStats.flash_stats.friendly_avg_blinded, false)}`}
                                >
                                    {overallStats.flash_stats.friendly_avg_blinded.toFixed(1)}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div className="overflow-hidden rounded-lg border border-gray-700 mb-4">
                <table className="w-full table-fixed">
                    <thead className="bg-gray-800/50">
                        <tr>
                            <th className="w-1/3 px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                HE + Molotov
                            </th>
                            <th className="w-1/3 px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Damage
                            </th>
                            <th className="w-1/3 px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">
                                &nbsp;
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-700">
                        <tr>
                            <td className="px-4 py-2 text-sm font-medium text-gray-300">
                                Total
                            </td>
                            <td className="px-4 py-2 text-center">
                                <span className={`text-sm font-medium ${getHeDamageColor(overallStats.he_stats.avg_damage)}`}>
                                    {overallStats.he_stats.avg_damage}
                                </span>
                            </td>
                            <td className="px-4 py-2 text-center">
                                &nbsp;
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </>
    );
}
