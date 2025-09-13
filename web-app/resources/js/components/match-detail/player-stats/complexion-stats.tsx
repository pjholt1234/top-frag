import { Card, CardContent } from '@/components/ui/card';
import { GaugeChart } from '@/components/charts/gauge-chart';

interface PlayerComplexion {
  opener: number;
  closer: number;
  support: number;
  fragger: number;
}

interface ComplexionStatsProps {
  complexion: PlayerComplexion;
}

const complexionData = [
  {
    key: 'opener' as keyof PlayerComplexion,
    label: 'Opener',
    description: 'First contact & entry fragging',
    color: 'text-yellow-500',
    gaugeColor: '#eab308', // yellow-500
  },
  {
    key: 'closer' as keyof PlayerComplexion,
    label: 'Closer',
    description: 'Late round & clutch situations',
    color: 'text-purple-500',
    gaugeColor: '#8b5cf6', // purple-500
  },
  {
    key: 'support' as keyof PlayerComplexion,
    label: 'Support',
    description: 'Utility usage & team play',
    color: 'text-blue-500',
    gaugeColor: '#3b82f6', // green-500
  },
  {
    key: 'fragger' as keyof PlayerComplexion,
    label: 'Fragger',
    description: 'Kill output & damage dealing',
    color: 'text-red-500',
    gaugeColor: '#ef4444', // red-500
  },
];

export function ComplexionStats({ complexion }: ComplexionStatsProps) {
  return (
    <Card>
      <CardContent>
        <div className="grid grid-cols-2 gap-0 relative">
          {/* Vertical divider */}
          <div className="absolute left-1/2 top-0 bottom-0 w-px bg-gray-600 transform -translate-x-1/2"></div>
          {/* Horizontal divider */}
          <div className="absolute top-1/2 left-0 right-0 h-px bg-gray-600 transform -translate-y-1/2"></div>

          {complexionData.map(
            ({ key, label, description, color, gaugeColor }) => (
              <div key={key} className="text-center p-4">
                <div className="h-18 flex items-center justify-center">
                  <GaugeChart
                    currentValue={complexion[key]}
                    maxValue={100}
                    size="lg"
                    color={gaugeColor}
                    showValue={true}
                    showPercentage={false}
                    centerContent={
                      <div className={`text-sm font-medium ${color}`}>
                        {label}
                      </div>
                    }
                  />
                </div>
                <div className="text-xs text-gray-400 mt-12">{description}</div>
              </div>
            )
          )}
        </div>
      </CardContent>
    </Card>
  );
}
