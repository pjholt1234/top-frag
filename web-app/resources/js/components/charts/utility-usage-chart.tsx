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
  const chartData = data.map(item => ({
    ...item,
    fill: GRENADE_COLORS[item.type as keyof typeof GRENADE_COLORS] || '#6b7280',
  }));

  const formatGrenadeType = (type: string) => {
    if (type === 'Fire') {
      return 'Fire';
    }
    return type.charAt(0).toUpperCase() + type.slice(1).replace(' Grenade', '');
  };

  return (
    <ChartContainer config={chartConfig} className="h-[300px] w-full">
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={chartData}
            cx="50%"
            cy="50%"
            outerRadius={80}
            dataKey="count"
            nameKey="type"
            label={({ type, percentage }) =>
              `${formatGrenadeType(type)}: ${percentage}%`
            }
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
          />
        </PieChart>
      </ResponsiveContainer>
    </ChartContainer>
  );
}
