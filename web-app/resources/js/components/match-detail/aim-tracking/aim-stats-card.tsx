import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { Info } from 'lucide-react';

interface AimStatsCardProps {
  headshotAccuracy: number | null | undefined;
  sprayingAccuracy: number | null | undefined;
  crosshairPlacementX: number | null | undefined;
  crosshairPlacementY: number | null | undefined;
  averageTimeToDamage?: number | null | undefined;
}

const QUALITY_COLORS = {
  poor: '#f87171', // red-400
  fair: '#fb923c', // orange-400
  good: '#22c55e', // green-500
  excellent: '#4ade80', // green-400 (lighter)
};

export function AimStatsCard({
  headshotAccuracy,
  sprayingAccuracy,
  crosshairPlacementX,
  crosshairPlacementY,
  averageTimeToDamage,
}: AimStatsCardProps) {
  const toNumber = (value: number | null | undefined): number => {
    if (typeof value === 'number') return value;
    const parsed = parseFloat(String(value || 0));
    return isNaN(parsed) ? 0 : parsed;
  };

  const getCrosshairXColor = (value: number): string => {
    if (value > 12) return QUALITY_COLORS.poor;
    if (value > 10) return QUALITY_COLORS.fair;
    if (value > 8) return QUALITY_COLORS.good;
    return QUALITY_COLORS.excellent;
  };

  const getCrosshairYColor = (value: number): string => {
    if (value > 4) return QUALITY_COLORS.poor;
    if (value > 3) return QUALITY_COLORS.fair;
    if (value > 2) return QUALITY_COLORS.good;
    return QUALITY_COLORS.excellent;
  };

  const getTimeToDamageColor = (value: number): string => {
    if (value > 600) return QUALITY_COLORS.poor;
    if (value > 500) return QUALITY_COLORS.fair;
    if (value > 400) return QUALITY_COLORS.good;
    return QUALITY_COLORS.excellent;
  };

  const getAccuracyColor = (value: number): string => {
    if (value < 30) return QUALITY_COLORS.poor;
    if (value < 40) return QUALITY_COLORS.fair;
    if (value < 50) return QUALITY_COLORS.good;
    return QUALITY_COLORS.excellent;
  };

  const formatPercentage = (value: number | null | undefined) => {
    const num = toNumber(value);
    return `${num.toFixed(1)}%`;
  };

  const formatCoordinate = (value: number | null | undefined) => {
    const num = toNumber(value);
    return num.toFixed(2);
  };

  const formatTime = (value: number | null | undefined) => {
    if (value === undefined || value === null) return 'N/A';
    const num = toNumber(value);
    return `${num.toFixed(2)}s`;
  };

  const headshotValue = toNumber(headshotAccuracy);
  const sprayValue = toNumber(sprayingAccuracy);
  const crosshairXValue = toNumber(crosshairPlacementX);
  const crosshairYValue = toNumber(crosshairPlacementY);
  const timeToDamageValue = toNumber(averageTimeToDamage);

  return (
    <Card>
      <CardContent>
        <div className="flex items-center justify-between mb-4">
          <CardTitle className="mb-0">Aim Statistics</CardTitle>
          <div className="group relative">
            <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
            <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
              <div className="text-sm">
                <div className="font-semibold text-white mb-2">
                  Aim Statistics
                </div>
                <div className="text-gray-300 mb-3">
                  Overall aim performance metrics for the selected player in
                  this match.
                </div>
                <div className="text-xs text-gray-400">
                  <div className="font-medium mb-1">Metrics Explained:</div>
                  <div className="space-y-1">
                    <div>
                      <span className="text-blue-400">Headshot %:</span>{' '}
                      Percentage of shots that hit the head
                    </div>
                    <div>
                      <span className="text-blue-400">Spray Accuracy:</span>{' '}
                      Accuracy when firing multiple consecutive shots
                    </div>
                    <div>
                      <span className="text-blue-400">
                        Crosshair Placement:
                      </span>{' '}
                      Average distance from head level (lower is better)
                    </div>
                    <div>
                      <span className="text-blue-400">
                        Avg. Time to Damage:
                      </span>{' '}
                      Average reaction time before landing first shot
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="space-y-4">
          {/* Headshot Percentage */}
          <div className="flex justify-between items-center">
            <span className="text-gray-400 text-sm">Headshot %</span>
            <span
              className="font-semibold"
              style={{ color: getAccuracyColor(headshotValue) }}
            >
              {formatPercentage(headshotAccuracy)}
            </span>
          </div>

          {/* Spray Accuracy */}
          <div className="flex justify-between items-center">
            <span className="text-gray-400 text-sm">Spray Accuracy</span>
            <span
              className="font-semibold"
              style={{ color: getAccuracyColor(sprayValue) }}
            >
              {formatPercentage(sprayingAccuracy)}
            </span>
          </div>

          {/* Crosshair Placement */}
          <div className="flex justify-between items-center">
            <span className="text-gray-400 text-sm">Crosshair Placement</span>
            <span className="font-semibold">
              <span style={{ color: getCrosshairXColor(crosshairXValue) }}>
                X: {formatCoordinate(crosshairPlacementX)}
              </span>
              <span className="text-gray-400">, </span>
              <span style={{ color: getCrosshairYColor(crosshairYValue) }}>
                Y: {formatCoordinate(crosshairPlacementY)}
              </span>
            </span>
          </div>

          {/* Average Time to Damage */}
          {averageTimeToDamage !== undefined && (
            <div className="flex justify-between items-center">
              <span className="text-gray-400 text-sm">Avg. Time to Damage</span>
              <span
                className="font-semibold"
                style={{ color: getTimeToDamageColor(timeToDamageValue) }}
              >
                {formatTime(averageTimeToDamage)}
              </span>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
