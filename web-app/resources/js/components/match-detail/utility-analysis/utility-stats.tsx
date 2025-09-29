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

interface SmokeStats {
  total_smoke_blocking_duration: number;
  avg_smoke_blocking_duration: number;
  total_round_smoke_blocking_duration: number;
  avg_round_smoke_blocking_duration: number;
  smoke_count: number;
  rounds_with_smoke: number;
}

interface OverallStats {
  overall_grenade_rating: number;
  flash_stats: FlashStats;
  he_stats: HeStats;
  smoke_stats: SmokeStats;
}

interface UtilityStatsProps {
  overallStats: OverallStats;
}

export function UtilityStats({ overallStats }: UtilityStatsProps) {
  // Add defensive check for smoke_stats
  const smokeStats = overallStats.smoke_stats || {
    total_smoke_blocking_duration: 0,
    avg_smoke_blocking_duration: 0,
    total_round_smoke_blocking_duration: 0,
    avg_round_smoke_blocking_duration: 0,
    smoke_count: 0,
    rounds_with_smoke: 0,
  };

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

  const getSmokeBlockingColor = (duration: number) => {
    // For smoke blocking duration, higher is better (more effective)
    return getCustomRatingColor(duration, [50, 100, 200, 300], 'text');
  };

  const getAVGPerSmokeBlockingColor = (duration: number) => {
    // For smoke blocking duration, higher is better (more effective)
    return getCustomRatingColor(duration, [2, 7, 12, 17], 'text');
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

  // Smoke Stats Table Data
  const smokeColumns = [
    { header: 'Smoke Grenades', width: 'w-1/3' },
    { header: 'Blocking Duration', width: 'w-1/3' },
    { header: 'Avg per Smoke', width: 'w-1/3' },
  ];

  const smokeRows = [
    {
      label: 'Total',
      values: [
        {
          value: `${smokeStats.total_smoke_blocking_duration}s`,
          className: getSmokeBlockingColor(
            smokeStats.total_smoke_blocking_duration
          ),
        },
        {
          value: `${smokeStats.avg_smoke_blocking_duration}s`,
          className: getAVGPerSmokeBlockingColor(
            smokeStats.avg_smoke_blocking_duration
          ),
        },
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
        title="Smoke Grenades"
        columns={smokeColumns}
        rows={smokeRows}
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
