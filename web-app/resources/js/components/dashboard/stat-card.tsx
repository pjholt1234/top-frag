import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface StatWithTrend {
  value: number | string;
  trend: 'up' | 'down' | 'neutral';
  change: number;
}

interface StatCardProps {
  title: string;
  stat: StatWithTrend | number | string;
  suffix?: string;
  className?: string;
  valueClassName?: string;
  lowerIsBetter?: boolean;
}

export const StatCard = ({
  title,
  stat,
  suffix = '',
  className,
  valueClassName,
  lowerIsBetter = false,
}: StatCardProps) => {
  const isStatWithTrend = (s: any): s is StatWithTrend => {
    return typeof s === 'object' && 'value' in s && 'trend' in s;
  };

  const statWithTrend = isStatWithTrend(stat) ? stat : null;
  const value = statWithTrend ? statWithTrend.value : stat;

  const getTrendIcon = (trend: 'up' | 'down' | 'neutral') => {
    if (lowerIsBetter) {
      // Inverted: down is good, up is bad
      switch (trend) {
        case 'up':
          return <TrendingUp className="h-4 w-4 text-red-500" />;
        case 'down':
          return <TrendingDown className="h-4 w-4 text-green-500" />;
        case 'neutral':
          return <Minus className="h-4 w-4 text-gray-500" />;
      }
    } else {
      // Normal: up is good, down is bad
      switch (trend) {
        case 'up':
          return <TrendingUp className="h-4 w-4 text-green-500" />;
        case 'down':
          return <TrendingDown className="h-4 w-4 text-red-500" />;
        case 'neutral':
          return <Minus className="h-4 w-4 text-gray-500" />;
      }
    }
  };

  const getTrendColor = (trend: 'up' | 'down' | 'neutral') => {
    if (lowerIsBetter) {
      // Inverted: down is good, up is bad
      switch (trend) {
        case 'up':
          return 'text-red-500';
        case 'down':
          return 'text-green-500';
        case 'neutral':
          return 'text-gray-500';
      }
    } else {
      // Normal: up is good, down is bad
      switch (trend) {
        case 'up':
          return 'text-green-500';
        case 'down':
          return 'text-red-500';
        case 'neutral':
          return 'text-gray-500';
      }
    }
  };

  return (
    <Card className={className}>
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex items-center justify-between">
          <div className={cn('text-2xl font-bold', valueClassName)}>
            {value}
            {suffix}
          </div>
          {statWithTrend && (
            <div className="flex items-center gap-1">
              {getTrendIcon(statWithTrend.trend)}
              <span
                className={cn(
                  'text-sm font-medium',
                  getTrendColor(statWithTrend.trend)
                )}
              >
                {statWithTrend.change}%
              </span>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
};
