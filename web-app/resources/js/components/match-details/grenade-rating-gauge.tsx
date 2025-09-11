import { GaugeChart } from '../charts/gauge-chart';
import { getRatingColor, getQualityLevel } from '@/lib/utils';

interface GrenadeRatingGaugeProps {
  rating: number;
  size?: 'sm' | 'md' | 'lg';
  showTitle?: boolean;
}

export function GrenadeRatingGauge({
  rating,
  size = 'lg',
  showTitle = true,
}: GrenadeRatingGaugeProps) {
  // Get rating color and description using standardized utility
  const ratingColor = getRatingColor(rating, 'hex');
  const qualityLevel = getQualityLevel(rating);
  const ratingDescription =
    qualityLevel.charAt(0).toUpperCase() + qualityLevel.slice(1);

  return (
    <GaugeChart
      currentValue={rating}
      maxValue={100}
      title={showTitle ? 'Avg Grenade Effectiveness' : undefined}
      unit="/100"
      size={size}
      color={ratingColor}
      showValue={true}
      showPercentage={false}
      centerContent={
        <div className="text-sm font-medium" style={{ color: ratingColor }}>
          {ratingDescription}
        </div>
      }
    />
  );
}
