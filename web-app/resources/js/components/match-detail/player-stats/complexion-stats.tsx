import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { GaugeChart } from '@/components/charts/gauge-chart';
import { OpenerIcon } from '@/components/icons/opener-icon';
import { CloserIcon } from '@/components/icons/closer-icon';
import { SupportIcon } from '@/components/icons/support-icon';
import { FraggerIcon } from '@/components/icons/fragger-icon';
import { COMPLEXION_COLORS } from '@/constants/colors';
import { Info } from 'lucide-react';

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
        <div className="flex items-center justify-between mb-4">
          <CardTitle>Player Complexion</CardTitle>
          <div className="flex items-center gap-2">
            <div className="group relative z-20">
              <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
              <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-20">
                <div className="text-sm">
                  <div className="font-semibold text-white mb-2">
                    Player Complexion
                  </div>
                  <div className="text-gray-300 mb-3">
                    Shows your playstyle distribution across four key roles.
                    Each gauge represents your proficiency in each role.
                  </div>
                  <div className="text-xs text-gray-400 mb-3">
                    <div className="font-medium mb-1">Role Breakdown:</div>
                    <div className="space-y-2">
                      <div>
                        <div>
                          <span className="text-yellow-500">Opener:</span> First
                          contact & entry fragging
                        </div>
                        <div className="text-gray-500 ml-2">
                          Stats: First kills, entry duels, opening impact
                        </div>
                      </div>
                      <div>
                        <div>
                          <span className="text-purple-500">Closer:</span> Late
                          round & clutch situations
                        </div>
                        <div className="text-gray-500 ml-2">
                          Stats: Clutch wins, late round kills, 1vX situations
                        </div>
                      </div>
                      <div>
                        <div>
                          <span className="text-blue-500">Support:</span>{' '}
                          Utility usage & team play
                        </div>
                        <div className="text-gray-500 ml-2">
                          Stats: Assists, flash assists, utility usage, trade
                          kills
                        </div>
                      </div>
                      <div>
                        <div>
                          <span className="text-red-500">Fragger:</span> Kill
                          output & damage dealing
                        </div>
                        <div className="text-gray-500 ml-2">
                          Stats: Kills, ADR, headshot percentage, multi-kills
                        </div>
                      </div>
                    </div>
                  </div>
                  <div className="text-xs text-gray-400">
                    <div className="font-medium mb-1">Interpretation:</div>
                    <div>
                      Higher values = better performance in that role. 100 is
                      the maximum score.
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
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
