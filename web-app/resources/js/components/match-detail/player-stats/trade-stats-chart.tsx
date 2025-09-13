import { StackedBarChart } from '@/components/charts/stacked-bar-chart';

interface TradeStatsData {
  total_successful_trades: number;
  total_possible_trades: number;
  total_traded_deaths: number;
  total_possible_traded_deaths: number;
}

interface TradeStatsChartProps {
  tradeData?: TradeStatsData;
}

export function TradeStatsChart({ tradeData }: TradeStatsChartProps) {
  if (!tradeData) {
    return (
      <div className="flex items-center justify-center h-32">
        <div className="text-gray-400">No trade data available</div>
      </div>
    );
  }

  const successfulTrades = tradeData.total_successful_trades;
  const missedTrades =
    tradeData.total_possible_trades - tradeData.total_successful_trades;
  const tradedDeaths = tradeData.total_traded_deaths;
  const missedTradeDeaths =
    tradeData.total_possible_traded_deaths - tradeData.total_traded_deaths;

  const chartData = [
    {
      name: 'Trades',
      successful: successfulTrades,
      missed: missedTrades,
    },
    {
      name: 'Trade Deaths',
      successful: tradedDeaths,
      missed: missedTradeDeaths,
    },
  ];

  const bars = [
    {
      dataKey: 'successful',
      name: 'Successful',
      color: '#10B981', // green-500
    },
    {
      dataKey: 'missed',
      name: 'Missed',
      color: '#EF4444', // red-500
    },
  ];

  return (
    <div className="h-64">
      <StackedBarChart
        data={chartData}
        bars={bars}
        height={256}
        showLegend={false}
        showGrid={true}
        xAxisLabel="Trade Categories"
        yAxisLabel="Count"
      />
    </div>
  );
}
