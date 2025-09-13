import { StackedBarChart } from '@/components/charts/stacked-bar-chart';

interface ClutchData {
  '1v1': {
    clutch_wins_1v1: number;
    clutch_attempts_1v1: number;
    clutch_win_percentage_1v1: number;
  };
  '1v2': {
    clutch_wins_1v2: number;
    clutch_attempts_1v2: number;
    clutch_win_percentage_1v2: number;
  };
  '1v3': {
    clutch_wins_1v3: number;
    clutch_attempts_1v3: number;
    clutch_win_percentage_1v3: number;
  };
  '1v4': {
    clutch_wins_1v4: number;
    clutch_attempts_1v4: number;
    clutch_win_percentage_1v4: number;
  };
  '1v5': {
    clutch_wins_1v5: number;
    clutch_attempts_1v5: number;
    clutch_win_percentage_1v5: number;
  };
}

interface ClutchChartProps {
  clutchData?: ClutchData;
}

export function ClutchChart({ clutchData }: ClutchChartProps) {
  if (!clutchData) {
    return (
      <div className="flex items-center justify-center h-32">
        <div className="text-gray-400">No clutch data available</div>
      </div>
    );
  }

  // Prepare data for the stacked bar chart
  const chartData = Object.entries(clutchData).map(([scenario, data]) => ({
    name: scenario,
    wins: data[`clutch_wins_${scenario}` as keyof typeof data] as number,
    attempts:
      (data[`clutch_attempts_${scenario}` as keyof typeof data] as number) -
      (data[`clutch_wins_${scenario}` as keyof typeof data] as number),
    total: data[`clutch_attempts_${scenario}` as keyof typeof data] as number,
    percentage: data[
      `clutch_win_percentage_${scenario}` as keyof typeof data
    ] as number,
  }));

  const CustomTooltip = ({ active, payload, label }: any) => {
    if (active && payload && payload.length) {
      const data = payload[0].payload;
      return (
        <div className="bg-gray-800 border border-gray-600 rounded-lg p-3">
          <p className="text-white font-medium">{label}</p>
          <p className="text-green-400">Wins: {data.wins}</p>
          <p className="text-red-400">Losses: {data.attempts}</p>
          <p className="text-gray-300">Total: {data.total}</p>
          <p className="text-blue-400">Win Rate: {data.percentage}%</p>
        </div>
      );
    }
    return null;
  };

  const bars = [
    {
      dataKey: 'wins',
      name: 'Wins',
      color: '#10B981', // green-500
    },
    {
      dataKey: 'attempts',
      name: 'Losses',
      color: '#EF4444', // red-500
    },
  ];

  return (
    <StackedBarChart
      data={chartData}
      bars={bars}
      height={256}
      showLegend={false}
      customTooltip={CustomTooltip}
      xAxisLabel="Clutches"
      yAxisLabel="Wins"
    />
  );
}
