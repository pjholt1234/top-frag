import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { UtilityUsageChart } from './charts/utility-usage-chart';
import { GrenadeEffectivenessChart } from './charts/grenade-effectiveness-chart';
import { GrenadeTimingChart } from './charts/grenade-timing-chart';
import { UtilityStats } from './utility-stats';

interface UtilityUsageData {
  type: string;
  count: number;
  percentage: number;
}

interface GrenadeEffectivenessData {
  round: number;
  effectiveness: number;
  total_grenades: number;
}

interface GrenadeTimingData {
  type: string;
  timing_data: Array<{
    round_time: number;
    round_number: number;
    effectiveness: number;
  }>;
}

interface OverallStats {
  overall_grenade_rating: number;
  flash_stats: {
    enemy_avg_duration: number;
    friendly_avg_duration: number;
    enemy_avg_blinded: number;
    friendly_avg_blinded: number;
  };
  he_stats: {
    avg_damage: number;
  };
}

interface UtilityChartsProps {
  utilityUsage: UtilityUsageData[];
  grenadeEffectiveness: GrenadeEffectivenessData[];
  grenadeTiming: GrenadeTimingData[];
  overallStats: OverallStats;
}

export function UtilityCharts({
  utilityUsage,
  grenadeEffectiveness,
  grenadeTiming,
  overallStats,
}: UtilityChartsProps) {
  return (
    <div className="space-y-6">
      {/* First row: Utility Usage and Stats will be side by side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardContent className="pt-2">
            <CardTitle className="mb-2">Utility Usage</CardTitle>
            <UtilityUsageChart data={utilityUsage} />
          </CardContent>
        </Card>

        <UtilityStats overallStats={overallStats} />
      </div>

      {/* Second row: Grenade Effectiveness and Timing Analysis side by side */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardContent className="pt-2">
            <CardTitle className="mb-2">
              Grenade Effectiveness by Round
            </CardTitle>
            <GrenadeEffectivenessChart data={grenadeEffectiveness} />
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-2">
            <CardTitle className="mb-2">Grenade Timing Analysis</CardTitle>
            <GrenadeTimingChart data={grenadeTiming} />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
