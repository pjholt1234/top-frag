import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { COMPLEXION_COLORS } from '@/constants/colors';
import {
  getImpactColor,
  getRoundSwingColor,
  getKdColor,
  getAvgKillsColor,
  getWinRateColor,
} from '@/lib/utils';
import { OpenerIcon } from '@/components/icons/opener-icon';
import { CloserIcon } from '@/components/icons/closer-icon';
import { SupportIcon } from '@/components/icons/support-icon';
import { FraggerIcon } from '@/components/icons/fragger-icon';

interface PlayerComplexion {
  opener: number;
  closer: number;
  support: number;
  fragger: number;
}

interface PlayerCardData {
  username: string;
  avatar: string | null;
  average_impact: number;
  average_round_swing: number;
  average_kd: number;
  average_kills: number;
  average_deaths: number;
  total_matches: number;
  win_percentage: number;
  player_complexion: PlayerComplexion;
}

interface PlayerSummaryCardProps {
  playerCard: PlayerCardData;
}

const roleData = [
  {
    key: 'opener',
    label: 'Opener',
    color: COMPLEXION_COLORS.opener.text,
    gaugeColor: COMPLEXION_COLORS.opener.hex,
    IconComponent: OpenerIcon,
  },
  {
    key: 'closer',
    label: 'Closer',
    color: COMPLEXION_COLORS.closer.text,
    gaugeColor: COMPLEXION_COLORS.closer.hex,
    IconComponent: CloserIcon,
  },
  {
    key: 'support',
    label: 'Support',
    color: COMPLEXION_COLORS.support.text,
    gaugeColor: COMPLEXION_COLORS.support.hex,
    IconComponent: SupportIcon,
  },
  {
    key: 'fragger',
    label: 'Fragger',
    color: COMPLEXION_COLORS.fragger.text,
    gaugeColor: COMPLEXION_COLORS.fragger.hex,
    IconComponent: FraggerIcon,
  },
];

export function PlayerSummaryCard({ playerCard }: PlayerSummaryCardProps) {
  const statsData = [
    {
      label: 'Avg Impact',
      value: playerCard.average_impact.toFixed(2),
      color: getImpactColor(playerCard.average_impact),
    },
    {
      label: 'Round Swing',
      value: `${playerCard.average_round_swing.toFixed(1)}%`,
      color: getRoundSwingColor(playerCard.average_round_swing),
    },
    {
      label: 'K/D Ratio',
      value: playerCard.average_kd.toFixed(2),
      color: getKdColor(playerCard.average_kd),
    },
    {
      label: 'Avg Kills',
      value: playerCard.average_kills.toFixed(1),
      color: getAvgKillsColor(playerCard.average_kills),
    },
    {
      label: 'Matches',
      value: playerCard.total_matches,
      color: 'text-gray-200',
    },
    {
      label: 'Win Rate',
      value: `${playerCard.win_percentage.toFixed(1)}%`,
      color: getWinRateColor(playerCard.win_percentage),
    },
  ];

  return (
    <Card className="h-full">
      <CardHeader>
        <div className="flex items-center gap-4">
          {playerCard.avatar && (
            <img
              src={playerCard.avatar}
              alt={`${playerCard.username} profile`}
              className="w-16 h-16 rounded-full border-2 border-gray-600"
              onError={e => {
                e.currentTarget.style.display = 'none';
              }}
            />
          )}
          <div>
            <h3 className="text-xl font-bold">{playerCard.username}</h3>
            <p className="text-sm text-muted-foreground">Performance Summary</p>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-6">
        {/* Basic Stats */}
        <div>
          <div className="grid grid-cols-2 gap-3">
            {statsData.map(stat => (
              <div
                key={stat.label}
                className="flex justify-between items-center"
              >
                <span className="text-sm text-gray-400">{stat.label}</span>
                <span className={`font-medium ${stat.color}`}>
                  {stat.value}
                </span>
              </div>
            ))}
          </div>
        </div>

        {/* Player Complexion */}
        <div>
          <div className="space-y-3">
            {roleData.map(
              ({ key, label, color, gaugeColor, IconComponent }) => {
                const score =
                  playerCard.player_complexion[key as keyof PlayerComplexion];

                return (
                  <Card key={key} className="overflow-hidden p-0">
                    <div className="flex">
                      {/* Icon Section */}
                      <div className="flex items-center justify-center p-3 border-r border-gray-700 bg-gray-800/50">
                        <IconComponent size={20} color={gaugeColor} />
                      </div>

                      {/* Main Content */}
                      <CardContent
                        className="p-0 flex-1"
                        style={{
                          background: `linear-gradient(315deg, ${gaugeColor}30 0%, rgba(31, 41, 55, 0.7) 50%, rgba(31, 41, 55, 0.9) 100%)`,
                        }}
                      >
                        <div className="flex items-center w-full p-3">
                          <div className="flex items-center gap-2 flex-shrink-0">
                            <span className={`font-medium text-sm ${color}`}>
                              {label}
                            </span>
                          </div>

                          <div className="flex items-center gap-2 flex-1 ml-3">
                            {/* Score Bar */}
                            <div className="h-2 bg-gray-700 rounded-full overflow-hidden flex-1">
                              <div
                                className="h-full transition-all duration-300"
                                style={{
                                  width: `${score}%`,
                                  backgroundColor: gaugeColor,
                                }}
                              />
                            </div>
                            <span className="text-sm font-medium text-gray-300 w-8 flex-shrink-0">
                              {score}
                            </span>
                          </div>
                        </div>
                      </CardContent>
                    </div>
                  </Card>
                );
              }
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
