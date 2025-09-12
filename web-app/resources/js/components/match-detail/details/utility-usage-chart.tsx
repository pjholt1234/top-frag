import { useMemo } from 'react';
import { PieChart, Pie, Cell, ResponsiveContainer, Legend } from 'recharts';
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart';

interface UtilityUsageData {
  type: string;
  count: number;
  percentage: number;
}

interface UtilityUsageChartProps {
  data: UtilityUsageData[];
}

const GRENADE_COLORS = {
  'HE Grenade': '#ef4444', // Red
  Fire: '#ff8c00', // Bright Orange (combined Incendiary + Molotov)
  'Smoke Grenade': '#6b7280', // Grey
  Flashbang: '#ffffff', // White
  'Decoy Grenade': '#3b82f6', // Blue
};

const chartConfig = {
  count: {
    label: 'Count',
  },
  percentage: {
    label: 'Percentage',
  },
};

export function UtilityUsageChart({ data }: UtilityUsageChartProps) {
  const chartData = useMemo(
    () =>
      data.map(item => ({
        ...item,
        fill:
          GRENADE_COLORS[item.type as keyof typeof GRENADE_COLORS] || '#6b7280',
      })),
    [data]
  );

  const formatGrenadeType = useMemo(
    () => (type: string) => {
      if (type === 'Fire') {
        return 'Fire';
      }
      return (
        type.charAt(0).toUpperCase() + type.slice(1).replace(' Grenade', '')
      );
    },
    []
  );

  return (
    <div className="flex justify-center">
      <ChartContainer config={chartConfig} className="h-[300px] w-fit">
        <ResponsiveContainer width={250} height={250}>
          <PieChart margin={{ top: 0, right: 0, bottom: 0, left: 0 }}>
            <Pie
              data={chartData}
              cx="50%"
              cy="50%"
              outerRadius={80}
              dataKey="count"
              nameKey="type"
              label={({ percentage }) => `${percentage}%`}
              labelLine={false}
            >
              {chartData.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={entry.fill} />
              ))}
            </Pie>
            <ChartTooltip
              content={
                <ChartTooltipContent
                  formatter={(value, name) => [
                    `${value} throws`,
                    formatGrenadeType(name as string),
                  ]}
                />
              }
            />
            <Legend
              formatter={value => formatGrenadeType(value)}
              wrapperStyle={{ fontSize: '12px' }}
              layout="horizontal"
              verticalAlign="bottom"
              align="center"
              margin={{ top: 10, left: 0, right: 0, bottom: 0 }}
            />
          </PieChart>
        </ResponsiveContainer>
      </ChartContainer>
    </div>
  );
}
