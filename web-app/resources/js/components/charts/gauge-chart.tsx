import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';
import React from 'react';

interface GaugeChartProps {
  currentValue: number;
  maxValue: number;
  title?: string;
  unit?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  color?: string;
  showValue?: boolean;
  showPercentage?: boolean;
  centerContent?: React.ReactNode;
}

export function GaugeChart({
  currentValue,
  maxValue,
  unit = '',
  size = 'md',
  color,
  showValue = true,
  showPercentage = true,
  centerContent,
  title,
}: GaugeChartProps) {
  // Calculate percentage
  const percentage = Math.min((currentValue / maxValue) * 100, 100);

  // Size configurations
  const sizeConfig = {
    sm: {
      fontSize: 'text-sm',
      textSize: 'text-xs',
      containerClass: 'w-30 h-20',
    },
    md: {
      fontSize: 'text-base',
      textSize: 'text-sm',
      containerClass: 'w-40 h-25',
    },
    lg: {
      fontSize: 'text-lg',
      textSize: 'text-base',
      containerClass: 'w-80 h-50',
    },
    xl: {
      fontSize: 'text-xl',
      textSize: 'text-base',
      containerClass: 'w-100 h-50',
    },
  };

  const config = sizeConfig[size];

  // Determine color based on percentage if no color provided
  const getColor = () => {
    if (color) return color;

    if (percentage >= 75) return '#10b981'; // green-500
    if (percentage >= 50) return '#f59e0b'; // amber-500
    if (percentage >= 25) return '#f97316'; // orange-500
    return '#ef4444'; // red-500
  };

  const gaugeColor = getColor();

  // Prepare data for PieChart (semi-circle gauge)
  const data = [
    {
      name: 'progress',
      value: percentage,
      fill: gaugeColor,
    },
    {
      name: 'remaining',
      value: 100 - percentage,
      fill: '#374151', // gray-700
    },
  ];

  return (
    <div className="flex flex-col items-center">
      <div className="flex flex-col items-center h-full">
        <div className={`${config.containerClass} relative`}>
          <ResponsiveContainer width="100%" height="100%">
            <PieChart margin={{ top: 0, right: 0, bottom: 0, left: 0 }}>
              <Pie
                data={data}
                cx="50%"
                cy="85%"
                startAngle={180}
                endAngle={0}
                innerRadius="75%"
                outerRadius="85%"
                dataKey="value"
                stroke="none"
              >
                {data.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.fill} />
                ))}
              </Pie>
            </PieChart>
          </ResponsiveContainer>

          {/* Text content positioned inside the gauge */}
          <div className="absolute inset-0 flex flex-col items-center justify-center mt-20">
            <div className="flex flex-col items-center space-y-2">
              {/* Percentage text */}
              {showPercentage && (
                <div className={`text-gray-400 ${config.textSize}`}>
                  {percentage.toFixed(0)}%
                </div>
              )}

              {/* Center content */}
              {centerContent && (
                <div className="text-center mt-2">{centerContent}</div>
              )}

              {/* Value text */}
              {showValue && (
                <div className={`text-white font-bold ${config.fontSize}`}>
                  {currentValue.toFixed(1)}
                  {unit && <span className="text-gray-400 ml-1">{unit}</span>}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
      {title && (
        <h2 className={`text-gray-300 font-medium ${config.fontSize} -mt-4`}>
          {title}
        </h2>
      )}
    </div>
  );
}
