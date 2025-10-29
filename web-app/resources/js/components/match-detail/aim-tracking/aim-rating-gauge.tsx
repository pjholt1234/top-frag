import { Card, CardContent } from '@/components/ui/card';
import { GaugeChart } from '@/components/charts/gauge-chart';
import { getRatingColor, getQualityLevel } from '@/lib/utils';
import { Info } from 'lucide-react';

interface AimRatingGaugeProps {
  aimRating: number | null | undefined;
}

export function AimRatingGauge({ aimRating }: AimRatingGaugeProps) {
  // Convert to number and validate
  const rating =
    typeof aimRating === 'number'
      ? aimRating
      : parseFloat(String(aimRating || 0));

  // Don't render if rating is invalid
  if (isNaN(rating)) {
    return null;
  }

  // Get rating color and description using standardized utility
  const ratingColor = getRatingColor(rating, 'hex');
  const qualityLevel = getQualityLevel(rating);
  const ratingDescription =
    qualityLevel.charAt(0).toUpperCase() + qualityLevel.slice(1);

  return (
    <Card className="h-full pt-0 mt-0">
      <CardContent className="flex items-center justify-center relative">
        <div className="absolute top-4 right-4 z-10">
          <div className="group relative">
            <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
            <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
              <div className="text-sm">
                <div className="font-semibold text-white mb-2">Aim Rating</div>
                <div className="text-gray-300 mb-3">
                  Overall aim performance score calculated from accuracy,
                  crosshair placement, headshot rate, and reaction time.
                </div>
                <div className="text-xs text-gray-400 mb-3">
                  <div className="font-medium mb-1">Rating Breakdown:</div>
                  <div className="space-y-1">
                    <div>
                      <span className="text-green-400">
                        Excellent (80-100):
                      </span>{' '}
                      Outstanding aim
                    </div>
                    <div>
                      <span className="text-blue-400">Good (60-79):</span> Solid
                      accuracy
                    </div>
                    <div>
                      <span className="text-yellow-400">Fair (40-59):</span>{' '}
                      Average performance
                    </div>
                    <div>
                      <span className="text-red-400">Poor (0-39):</span> Needs
                      improvement
                    </div>
                  </div>
                </div>
                <div className="text-xs text-gray-400">
                  <div className="font-medium mb-1">Factors:</div>
                  <div>
                    Accuracy, crosshair placement, headshot rate, spraying
                    control
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <GaugeChart
          currentValue={rating}
          maxValue={100}
          title="Aim Rating"
          unit="/100"
          size="lg"
          color={ratingColor}
          showValue={true}
          showPercentage={false}
          centerContent={
            <div className="text-sm font-medium" style={{ color: ratingColor }}>
              {ratingDescription}
            </div>
          }
        />
      </CardContent>
    </Card>
  );
}
