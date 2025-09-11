import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    ResponsiveContainer,
} from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';

interface GrenadeEffectivenessData {
    round: number;
    effectiveness: number;
    total_grenades: number;
}

interface GrenadeEffectivenessChartProps {
    data: GrenadeEffectivenessData[];
}

const chartConfig = {
    effectiveness: {
        label: 'Effectiveness',
        color: '#3b82f6',
    },
    total_grenades: {
        label: 'Total Grenades',
        color: '#10b981',
    },
};

export function GrenadeEffectivenessChart({
    data,
}: GrenadeEffectivenessChartProps) {
    if (data.length === 0) {
        return (
            <div className="flex items-center justify-center h-[300px] text-gray-400">
                No effectiveness data available
            </div>
        );
    }

    return (
        <ChartContainer config={chartConfig} className="h-[300px] w-full">
            <ResponsiveContainer width="100%" height="100%">
                <LineChart
                    data={data}
                    margin={{ top: 20, right: 20, left: 20, bottom: 29 }}
                >
                    <CartesianGrid strokeDasharray="3 3" className="stroke-gray-700" />
                    <XAxis
                        dataKey="round"
                        className="text-gray-400"
                        tick={{ fontSize: 12 }}
                        label={{
                            value: 'Round Number',
                            position: 'insideBottom',
                            offset: -5,
                            style: { textAnchor: 'middle', fill: '#9ca3af' },
                        }}
                    />
                    <YAxis
                        className="text-gray-400"
                        tick={{ fontSize: 12 }}
                        domain={[0, 10]}
                        label={{
                            value: 'Effectiveness Rating',
                            angle: -90,
                            position: 'insideLeft',
                            style: { textAnchor: 'middle', fill: '#9ca3af' },
                        }}
                    />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                formatter={(value, name) => [
                                    name === 'effectiveness'
                                        ? `${value}/100`
                                        : `${value} grenades`,
                                    name === 'effectiveness' ? 'Effectiveness' : 'Total Grenades',
                                ]}
                                labelFormatter={label => `Round ${label}`}
                            />
                        }
                    />
                    <Line
                        type="linear"
                        dataKey="effectiveness"
                        stroke="var(--custom-orange)"
                        strokeWidth={2}
                        dot={{ fill: 'var(--custom-orange)', strokeWidth: 2, r: 4 }}
                        activeDot={{ r: 6, stroke: 'var(--custom-orange)', strokeWidth: 2 }}
                    />
                </LineChart>
            </ResponsiveContainer>
        </ChartContainer>
    );
}
