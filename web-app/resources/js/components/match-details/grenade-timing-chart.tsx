import {
    ScatterChart,
    Scatter,
    XAxis,
    YAxis,
    CartesianGrid,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import { ChartContainer, ChartTooltip } from '@/components/ui/chart';

interface TimingData {
    round_time: number;
    round_number: number;
    effectiveness: number;
}

interface GrenadeTimingData {
    type: string;
    timing_data: TimingData[];
}

interface GrenadeTimingChartProps {
    data: GrenadeTimingData[];
}

const GRENADE_COLORS = {
    'HE Grenade': '#ef4444', // Red
    Fire: '#ff8c00', // Bright Orange (combined Incendiary + Molotov)
    'Smoke Grenade': '#6b7280', // Grey
    Flashbang: '#ffffff', // White
    'Decoy Grenade': '#3b82f6', // Blue
};

const chartConfig = {
    round_time: {
        label: 'Round Time',
    },
    effectiveness: {
        label: 'Effectiveness',
    },
};

export function GrenadeTimingChart({ data }: GrenadeTimingChartProps) {
    const formatGrenadeType = (type: string) => {
        if (type === 'Fire') {
            return 'Fire';
        }
        return type.charAt(0).toUpperCase() + type.slice(1).replace(' Grenade', '');
    };

    const scatterData = data.map(grenadeType => ({
        type: grenadeType.type,
        data: grenadeType.timing_data
            .filter(point => point.round_time >= 0) // Filter out negative round times
            .map(point => ({
                ...point,
                fill:
                    GRENADE_COLORS[grenadeType.type as keyof typeof GRENADE_COLORS] ||
                    '#6b7280',
            })),
    }));

    if (scatterData.length === 0) {
        return (
            <div className="flex items-center justify-center h-[300px] text-gray-400">
                No timing data available
            </div>
        );
    }

    return (
        <ChartContainer config={chartConfig} className="h-[300px] w-full">
            <ResponsiveContainer width="100%" height="100%">
                <ScatterChart margin={{ top: 20, right: 20, left: 20, bottom: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-gray-700" />
                    <XAxis
                        type="number"
                        dataKey="round_time"
                        name="Round Time"
                        className="text-gray-400"
                        tick={{ fontSize: 12 }}
                        domain={[0, 115]}
                        allowDataOverflow={false}
                        label={{
                            value: 'Round Time (seconds)',
                            position: 'insideBottom',
                            offset: -5,
                            style: { textAnchor: 'middle', fill: '#9ca3af' },
                        }}
                    />
                    <YAxis
                        type="number"
                        dataKey="effectiveness"
                        name="Effectiveness"
                        className="text-gray-400"
                        tick={{ fontSize: 12 }}
                        label={{
                            value: 'Effectiveness Rating',
                            angle: -90,
                            position: 'insideLeft',
                            style: { textAnchor: 'middle', fill: '#9ca3af' },
                        }}
                    />
                    <ChartTooltip
                        content={({ active, payload }) => {
                            if (active && payload && payload.length > 0) {
                                const data = payload[0].payload;
                                return (
                                    <div className="bg-gray-800 border border-gray-600 rounded-lg p-3 shadow-lg">
                                        <p className="text-white">{data.grenade_type}</p>
                                        <p className="text-blue-400">
                                            Round Time: {data.round_time}s
                                        </p>
                                        <p className="text-green-400">
                                            Effectiveness: {data.effectiveness}/100
                                        </p>
                                    </div>
                                );
                            }
                            return null;
                        }}
                    />
                    <Legend
                        formatter={value => formatGrenadeType(value)}
                        wrapperStyle={{ fontSize: '12px' }}
                        verticalAlign="top"
                        height={36}
                    />
                    {scatterData.map(series => (
                        <Scatter
                            key={series.type}
                            dataKey="effectiveness"
                            data={series.data}
                            fill={series.data[0]?.fill || '#6b7280'}
                            name={series.type}
                        />
                    ))}
                </ScatterChart>
            </ResponsiveContainer>
        </ChartContainer>
    );
}
