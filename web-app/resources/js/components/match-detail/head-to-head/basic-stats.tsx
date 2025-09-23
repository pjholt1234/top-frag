interface BasicStatsData {
  kills: number;
  deaths: number;
  adr: number;
  assists: number;
  headshots: number;
  total_impact: number;
  impact_percentage: number;
  match_swing_percent: number;
}

interface BasicStatsProps {
  stats: BasicStatsData;
  comparisonStats?: BasicStatsData;
}

export function BasicStats({ stats, comparisonStats }: BasicStatsProps) {
  const getComparisonColor = (
    value: number,
    comparisonValue: number | undefined,
    higherIsBetter: boolean = true
  ) => {
    if (!comparisonStats || comparisonValue === undefined)
      return 'text-gray-200';

    // Debug logging for ADR comparison issues
    if (typeof value !== 'number' || typeof comparisonValue !== 'number') {
      console.warn('ADR comparison: Invalid data types', {
        value,
        comparisonValue,
        valueType: typeof value,
        comparisonType: typeof comparisonValue,
      });
      return 'text-gray-200';
    }

    if (isNaN(value) || isNaN(comparisonValue)) {
      console.warn('ADR comparison: NaN values', { value, comparisonValue });
      return 'text-gray-200';
    }

    if (higherIsBetter) {
      if (value > comparisonValue) return 'text-green-400';
      if (value < comparisonValue) return 'text-red-400';
    } else {
      if (value < comparisonValue) return 'text-green-400';
      if (value > comparisonValue) return 'text-red-400';
    }

    return 'text-gray-200';
  };

  const statsData = [
    {
      label: 'Kills',
      value: stats.kills,
      comparisonValue: comparisonStats?.kills,
      higherIsBetter: true,
    },
    {
      label: 'Deaths',
      value: stats.deaths,
      comparisonValue: comparisonStats?.deaths,
      higherIsBetter: false,
    },
    {
      label: 'ADR',
      value: Math.round(Number(stats.adr) || 0),
      comparisonValue: comparisonStats
        ? Math.round(Number(comparisonStats.adr) || 0)
        : undefined,
      higherIsBetter: true,
    },
    {
      label: 'Assists',
      value: stats.assists,
      comparisonValue: comparisonStats?.assists,
      higherIsBetter: true,
    },
    {
      label: 'Headshots',
      value: stats.headshots,
      comparisonValue: comparisonStats?.headshots,
      higherIsBetter: true,
    },
    {
      label: 'Total Impact',
      value: Number(stats.total_impact || 0).toFixed(1),
      comparisonValue: comparisonStats
        ? Number(comparisonStats.total_impact || 0).toFixed(1)
        : undefined,
      higherIsBetter: true,
    },
    {
      label: 'Impact %',
      value: Number(stats.impact_percentage || 0).toFixed(1) + '%',
      comparisonValue: comparisonStats
        ? Number(comparisonStats.impact_percentage || 0).toFixed(1) + '%'
        : undefined,
      higherIsBetter: true,
    },
    {
      label: 'Round Swing %',
      value: Number(stats.match_swing_percent || 0).toFixed(1) + '%',
      comparisonValue: comparisonStats
        ? Number(comparisonStats.match_swing_percent || 0).toFixed(1) + '%'
        : undefined,
      higherIsBetter: true,
    },
  ];

  return (
    <div className="mx-2">
      <h3 className="text-base font-medium mb-2">Basic Stats</h3>
      <div className="grid grid-cols-1 gap-1">
        {statsData.map(stat => (
          <div key={stat.label} className="flex justify-between items-center">
            <span className="text-sm text-gray-400">{stat.label}</span>
            <span
              className={`font-medium ${getComparisonColor(
                typeof stat.value === 'string'
                  ? parseFloat(stat.value)
                  : stat.value,
                typeof stat.comparisonValue === 'string'
                  ? parseFloat(stat.comparisonValue)
                  : stat.comparisonValue,
                stat.higherIsBetter
              )}`}
            >
              {stat.value}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}
