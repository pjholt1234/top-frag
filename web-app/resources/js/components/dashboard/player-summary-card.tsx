import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { COMPLEXION_COLORS } from '@/constants/colors';
import {
  getKdColor,
  getAdrColor,
  getAvgKillsColor,
  getAvgDeathsColor,
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
  average_adr: number;
  average_kills: number;
  average_deaths: number;
  total_kills: number;
  total_deaths: number;
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
        {/* Stats Grid */}
        <div>
          <div className="grid grid-cols-2 gap-3">
            {/* Avg Kills */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Avg Kills</span>
              <span
                className={`font-medium ${getAvgKillsColor(playerCard.average_kills)}`}
              >
                {playerCard.average_kills.toFixed(1)}
              </span>
            </div>

            {/* Avg Deaths */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Avg Deaths</span>
              <span
                className={`font-medium ${getAvgDeathsColor(playerCard.average_deaths)}`}
              >
                {playerCard.average_deaths.toFixed(1)}
              </span>
            </div>

            {/* Total Kills */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Total Kills</span>
              <span className="font-medium text-gray-200">
                {playerCard.total_kills}
              </span>
            </div>

            {/* Total Deaths */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Total Deaths</span>
              <span className="font-medium text-gray-200">
                {playerCard.total_deaths}
              </span>
            </div>

            {/* Average K/D */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Average K/D</span>
              <span
                className={`font-medium ${getKdColor(playerCard.average_kd)}`}
              >
                {playerCard.average_kd.toFixed(2)}
              </span>
            </div>

            {/* Average ADR */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Average ADR</span>
              <span
                className={`font-medium ${getAdrColor(playerCard.average_adr)}`}
              >
                {playerCard.average_adr.toFixed(1)}
              </span>
            </div>

            {/* Total Matches */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Matches</span>
              <span className="font-medium text-gray-200">
                {playerCard.total_matches}
              </span>
            </div>

            {/* Win Percentage */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Win Rate</span>
              <span
                className={`font-medium ${getWinRateColor(playerCard.win_percentage)}`}
              >
                {playerCard.win_percentage.toFixed(1)}%
              </span>
            </div>
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
