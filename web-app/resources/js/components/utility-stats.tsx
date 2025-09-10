import { Card, CardContent, CardTitle } from '@/components/ui/card';

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
      if (duration >= 3) return 'text-green-400';
      if (duration >= 2) return 'text-green-300';
      if (duration >= 1) return 'text-orange-400';
      return 'text-red-400';
    } else {
      if (duration >= 3) return 'text-red-400';
      if (duration >= 2) return 'text-orange-400';
      if (duration >= 1) return 'text-green-300';
      return 'text-green-400';
    }
  };

  const getBlindedColor = (count: number, isEnemy: boolean) => {
    if (isEnemy) {
      if (count >= 3) return 'text-green-400';
      if (count >= 2) return 'text-green-300';
      if (count >= 1) return 'text-orange-400';
      return 'text-red-400';
    } else {
      if (count >= 3) return 'text-red-400';
      if (count >= 2) return 'text-orange-400';
      if (count >= 1) return 'text-green-300';
      return 'text-green-400';
    }
  };

  const getHeDamageColor = (damage: number) => {
    if (damage >= 20) return 'bg-green-600';
    if (damage >= 10) return 'bg-green-400';
    if (damage >= 5) return 'bg-orange-400';
    return 'bg-red-500';
  };

  const getRatingColor = (rating: number) => {
    if (rating >= 75) return 'bg-green-600';
    if (rating >= 50) return 'bg-green-400';
    if (rating >= 25) return 'bg-orange-400';
    return 'bg-red-500';
  };

  return (
    <Card>
      <CardContent className="pt-2">
        <div className="flex items-center justify-between mb-6">
          <CardTitle>Utility Statistics</CardTitle>
          <div className="flex items-center gap-3">
            <div className="text-sm text-gray-400">Overall Rating</div>
            <span
              className={`inline-flex items-center px-1 rounded-full text-md font-medium ${getRatingColor(overallStats.overall_grenade_rating)} text-white`}
            >
              {overallStats.overall_grenade_rating.toFixed(1)}/100
            </span>
          </div>
        </div>

        {/* Flash Statistics Table */}
        <div className="mb-6">
          <h4 className="text-sm font-semibold text-gray-300 mb-4">
            Flash Statistics
          </h4>
          <div className="overflow-hidden rounded-lg border border-gray-700">
            <table className="w-full">
              <thead className="bg-gray-800/50">
                <tr>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                    Type
                  </th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">
                    Duration
                  </th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">
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
        </div>

        {/* HE Statistics */}
        <div>
          <h4 className="text-sm font-semibold text-gray-300 mb-3">
            HE Grenade Statistics
          </h4>
          <div className="flex items-center gap-3 px-4 py-2 rounded-lg bg-gray-800/30 border border-gray-700">
            <div className="text-xs text-gray-400">Average Damage</div>
            <span
              className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getHeDamageColor(overallStats.he_stats.avg_damage)} text-white`}
            >
              {overallStats.he_stats.avg_damage.toFixed(1)}
            </span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
