import { getCustomRatingColor } from '@/lib/utils';
import { StatsTable } from '@/components/ui/stats-table';

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

  // Flashbangs Table Data
  const flashColumns = [
    { header: 'Flashbangs', width: 'w-1/3' },
    { header: 'Avg Flash Duration', width: 'w-1/3' },
    { header: 'Players Blinded', width: 'w-1/3' },
  ];

  const flashRows = [
    {
      label: 'Enemy',
      values: [
        {
          value: `${overallStats.flash_stats.enemy_avg_duration.toFixed(2)}s`,
          className: getFlashDurationColor(
            overallStats.flash_stats.enemy_avg_duration,
            true
          ),
        },
        {
          value: overallStats.flash_stats.enemy_avg_blinded.toFixed(1),
          className: getBlindedColor(
            overallStats.flash_stats.enemy_avg_blinded,
            true
          ),
        },
      ],
    },
    {
      label: 'Friendly',
      values: [
        {
          value: `${overallStats.flash_stats.friendly_avg_duration.toFixed(2)}s`,
          className: getFlashDurationColor(
            overallStats.flash_stats.friendly_avg_duration,
            false
          ),
        },
        {
          value: overallStats.flash_stats.friendly_avg_blinded.toFixed(1),
          className: getBlindedColor(
            overallStats.flash_stats.friendly_avg_blinded,
            false
          ),
        },
      ],
    },
  ];

  // HE + Molotov Table Data
  const heColumns = [
    { header: 'HE + Molotov', width: 'w-1/3' },
    { header: 'Damage', width: 'w-1/3' },
    { header: '', width: 'w-1/3' },
  ];

  const heRows = [
    {
      label: 'Total',
      values: [
        {
          value: overallStats.he_stats.avg_damage,
          className: getHeDamageColor(overallStats.he_stats.avg_damage),
        },
        { value: '', empty: true },
      ],
    },
  ];

  return (
    <>
      <StatsTable
        title="Flashbangs"
        columns={flashColumns}
        rows={flashRows}
        className="mb-4"
      />
      <StatsTable
        title="HE + Molotov"
        columns={heColumns}
        rows={heRows}
        className="mb-4"
      />
    </>
  );
}
