import { Card, CardContent } from '@/components/ui/card';
import { GaugeChart } from '@/components/charts/gauge-chart';
import { OpenerIcon } from '@/components/icons/opener-icon';
import { CloserIcon } from '@/components/icons/closer-icon';
import { SupportIcon } from '@/components/icons/support-icon';
import { FraggerIcon } from '@/components/icons/fragger-icon';
import { COMPLEXION_COLORS } from '@/constants/colors';

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
    color: COMPLEXION_COLORS.opener.text,
    gaugeColor: COMPLEXION_COLORS.opener.hex,
    IconComponent: OpenerIcon,
  },
  {
    key: 'closer' as keyof PlayerComplexion,
    label: 'Closer',
    description: 'Late round & clutch situations',
    color: COMPLEXION_COLORS.closer.text,
    gaugeColor: COMPLEXION_COLORS.closer.hex,
    IconComponent: CloserIcon,
  },
  {
    key: 'support' as keyof PlayerComplexion,
    label: 'Support',
    description: 'Utility usage & team play',
    color: COMPLEXION_COLORS.support.text,
    gaugeColor: COMPLEXION_COLORS.support.hex,
    IconComponent: SupportIcon,
  },
  {
    key: 'fragger' as keyof PlayerComplexion,
    label: 'Fragger',
    description: 'Kill output & damage dealing',
    color: COMPLEXION_COLORS.fragger.text,
    gaugeColor: COMPLEXION_COLORS.fragger.hex,
    IconComponent: FraggerIcon,
  },
];

export function ComplexionStats({ complexion }: ComplexionStatsProps) {
  return (
    <Card>
      <CardContent>
        <div className="grid grid-cols-2 gap-0 relative">
          <div className="absolute left-1/2 top-0 bottom-0 w-px bg-gray-600 transform -translate-x-1/2"></div>
          <div className="absolute top-1/2 left-0 right-0 h-px bg-gray-600 transform -translate-y-1/2"></div>

          {complexionData.map(
            ({ key, label, description, color, gaugeColor, IconComponent }) => (
              <div key={key} className="text-center p-4 relative">
                <div className="absolute top-2 left-2 w-5 h-5">
                  <IconComponent
                    size={20}
                    color={gaugeColor}
                    className="opacity-70"
                  />
                </div>
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
