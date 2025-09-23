import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { QUALITY_COLORS } from '@/constants/colors';
import { Info } from 'lucide-react';

interface DeepDiveData {
  round_swing: number;
  impact: number;
  impact_percentage: number;
  round_swing_percent: number;
}

interface OpeningTimingData {
  first_kills: number;
  first_deaths: number;
  avg_time_to_death: number | string;
  avg_time_to_contact: number | string;
}

interface DeepDiveStatsProps {
  deepDive?: DeepDiveData;
  openingTiming?: OpeningTimingData;
}

const firstKillsColours = (firstKills: number) => {
  let color = '';
  switch (firstKills) {
    case 4:
      color = QUALITY_COLORS.excellent.text;
      break;
    case 3:
      color = QUALITY_COLORS.good.text;
      break;
    case 2:
      color = QUALITY_COLORS.fair.text;
      break;
    default:
      color = QUALITY_COLORS.poor.text;
      break;
  }

  return color;
};

const firstDeathColours = (firstKills: number) => {
  let color = '';
  switch (firstKills) {
    case 0:
      color = QUALITY_COLORS.excellent.text;
      break;
    case 1:
      color = QUALITY_COLORS.good.text;
      break;
    case 2:
      color = QUALITY_COLORS.fair.text;
      break;
    default:
      color = QUALITY_COLORS.poor.text;
      break;
  }

  return color;
};

// Impact percentage color function (handles negative values)
const getImpactPercentageColor = (impactPercentage: number) => {
  if (impactPercentage < 0) {
    return QUALITY_COLORS.poor.text;
  }

  if (impactPercentage >= 60) {
    return QUALITY_COLORS.excellent.text;
  }
  if (impactPercentage >= 40) {
    return QUALITY_COLORS.good.text;
  }
  if (impactPercentage >= 15) {
    return QUALITY_COLORS.fair.text;
  }

  return QUALITY_COLORS.poor.text;
};

// Round swing percentage color function (handles negative values)
const getRoundSwingColor = (roundSwingPercent: number) => {
  if (roundSwingPercent < 0) {
    return QUALITY_COLORS.poor.text;
  }

  if (roundSwingPercent >= 10) {
    return QUALITY_COLORS.excellent.text;
  }
  if (roundSwingPercent >= 5) {
    return QUALITY_COLORS.good.text;
  }
  if (roundSwingPercent >= 2) {
    return QUALITY_COLORS.fair.text;
  }
  return QUALITY_COLORS.poor.text;
};

export function DeepDiveStats({ deepDive, openingTiming }: DeepDiveStatsProps) {
  return (
    <>
      <Card>
        <CardContent>
          <div className="flex items-center justify-between mb-2">
            <CardTitle>Advanced Metrics</CardTitle>
            <div className="flex items-center gap-2">
              <div className="group relative">
                <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
                <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                  <div className="text-sm">
                    <div className="font-semibold text-white mb-2">
                      Round Swing %
                    </div>
                    <div className="text-gray-300 mb-3">
                      Measures your influence on round outcomes. Higher values
                      indicate you&apos;re consistently making impactful plays
                      that help your team win rounds.
                    </div>
                    <div className="text-xs text-gray-400 mb-3">
                      <div className="font-medium mb-1">
                        Good: 5%+ | Fair: 2-5% | Poor: 0-2%
                      </div>
                      <div>
                        Negative values indicate you&apos;re hurting your
                        team&apos;s chances
                      </div>
                    </div>

                    <div className="font-semibold text-white mb-2">
                      Impact %
                    </div>
                    <div className="text-gray-300 mb-3">
                      Shows how effective your individual actions are. Based on
                      context, opponent strength, and situation multipliers.
                    </div>
                    <div className="text-xs text-gray-400">
                      <div className="font-medium mb-1">
                        Excellent: 75%+ | Good: 50-75% | Fair: 25-50% | Poor:
                        0-25%
                      </div>
                      <div>
                        Higher = more impactful kills, assists, and plays
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          {deepDive ? (
            <div>
              <div className="grid grid-cols-2 gap-4 relative">
                <div className="absolute left-1/2 top-0 bottom-0 w-px bg-gray-600 transform -translate-x-1/2"></div>
                <div className="text-center">
                  <div
                    className={`text-2xl font-bold ${getRoundSwingColor(Number(deepDive.round_swing_percent) || 0)}`} // Force blue to test if CSS is working
                  >
                    {Number(deepDive.round_swing_percent || 0).toFixed(1)}%
                  </div>
                  <div className="text-sm text-gray-400">Round Swing %</div>
                </div>
                <div className="text-center">
                  <div
                    className={`text-2xl font-bold ${getImpactPercentageColor(Number(deepDive.impact_percentage) || 0)}`}
                  >
                    {Number(deepDive.impact_percentage || 0).toFixed(1)}%
                  </div>
                  <div className="text-sm text-gray-400">Impact %</div>
                </div>
              </div>
            </div>
          ) : (
            <div className="flex items-center justify-center h-32">
              <div className="text-center">
                <div className="text-gray-400 text-lg mb-2">🔍</div>
                <p className="text-gray-400">Advanced metrics coming soon</p>
                <p className="text-gray-500 text-sm mt-2">
                  Round swing and impact analysis will be displayed here
                </p>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
      <Card>
        <CardContent>
          <CardTitle className="mb-2">Opening & Timing</CardTitle>
          {openingTiming && (
            <div>
              <div className="grid grid-cols-2 gap-4 relative">
                <div className="absolute left-1/2 top-0 bottom-0 w-px bg-gray-600 transform -translate-x-1/2"></div>
                <div className="text-center">
                  <div
                    className={`text-2xl font-bold ${firstKillsColours(openingTiming.first_kills)}`}
                  >
                    {openingTiming.first_kills}
                  </div>
                  <div className="text-sm text-gray-400">First Kills</div>
                </div>
                <div className="text-center">
                  <div
                    className={`text-2xl font-bold ${firstDeathColours(openingTiming.first_deaths)}`}
                  >
                    {openingTiming.first_deaths}
                  </div>
                  <div className="text-sm text-gray-400">First Deaths</div>
                </div>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}
