import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { COMPLEXION_COLORS } from '@/constants/colors';
import {
  getKdColor,
  getAdrColor,
  getAvgKillsColor,
  getAvgDeathsColor,
  getWinRateColor,
  getImpactColor,
  getRoundSwingColor,
} from '@/lib/utils';
import { OpenerIcon } from '@/components/icons/opener-icon';
import { CloserIcon } from '@/components/icons/closer-icon';
import { SupportIcon } from '@/components/icons/support-icon';
import { FraggerIcon } from '@/components/icons/fragger-icon';
import { TopAimerIcon } from '@/components/icons/top-aimer-icon';
import { ImpactPlayerIcon } from '@/components/icons/impact-player-icon';
import { DifferenceMakerIcon } from '@/components/icons/difference-maker-icon';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';

interface PlayerComplexion {
  opener: number;
  closer: number;
  support: number;
  fragger: number;
}

interface AchievementCounts {
  fragger: number;
  support: number;
  opener: number;
  closer: number;
  top_aimer: number;
  impact_player: number;
  difference_maker: number;
}

interface PlayerCardData {
  username: string;
  avatar: string | null;
  steam_id: string | null;
  faceit_nickname: string | null;
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
  achievements?: AchievementCounts;
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

const achievementData = [
  {
    key: 'fragger',
    label: 'Fragger',
    description: 'Awarded for having the highest fragger score in a match',
    color: COMPLEXION_COLORS.fragger.hex,
    IconComponent: FraggerIcon,
  },
  {
    key: 'support',
    label: 'Support',
    description: 'Awarded for having the highest support score in a match',
    color: COMPLEXION_COLORS.support.hex,
    IconComponent: SupportIcon,
  },
  {
    key: 'opener',
    label: 'Opener',
    description: 'Awarded for having the highest opener score in a match',
    color: COMPLEXION_COLORS.opener.hex,
    IconComponent: OpenerIcon,
  },
  {
    key: 'closer',
    label: 'Closer',
    description: 'Awarded for having the highest closer score in a match',
    color: COMPLEXION_COLORS.closer.hex,
    IconComponent: CloserIcon,
  },
  {
    key: 'top_aimer',
    label: 'Top Aimer',
    description: 'Awarded for having the highest aim rating in a match',
    color: '#f97316',
    IconComponent: TopAimerIcon,
  },
  {
    key: 'impact_player',
    label: 'Impact Player',
    description: 'Awarded for having the highest total impact in a match',
    color: '#22c55e',
    IconComponent: ImpactPlayerIcon,
  },
  {
    key: 'difference_maker',
    label: 'Difference Maker',
    description:
      'Awarded for having the highest round swing percentage in a match',
    color: '#a855f7',
    IconComponent: DifferenceMakerIcon,
  },
];

export function PlayerSummaryCard({
  playerCard,
  achievements,
}: PlayerSummaryCardProps) {
  return (
    <Card className="h-full">
      <CardHeader>
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-4 flex-1">
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
            <div className="flex-1">
              <div className="flex items-center justify-between gap-2">
                <h3 className="text-xl font-bold">{playerCard.username}</h3>
                {/* External Links */}
                <div className="flex items-center gap-2">
                  {playerCard.steam_id && (
                    <a
                      href={`https://steamcommunity.com/profiles/${playerCard.steam_id}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="hover:opacity-80 transition-opacity"
                    >
                      <img
                        src="/images/icons/steam.ico"
                        alt="Steam Profile"
                        className="w-5 h-5"
                      />
                    </a>
                  )}
                  {playerCard.faceit_nickname && (
                    <a
                      href={`https://www.faceit.com/en/players/${playerCard.faceit_nickname}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="hover:opacity-80 transition-opacity"
                    >
                      <img
                        src="/images/icons/faceit.ico"
                        alt="FaceIT Profile"
                        className="w-5 h-5"
                      />
                    </a>
                  )}
                </div>
              </div>
              {/* Achievement Icons */}
              {achievements && (
                <div className="flex items-center gap-2 mt-0 flex-wrap">
                  {achievementData.map(
                    ({ key, label, description, color, IconComponent }) => {
                      const count =
                        achievements[key as keyof AchievementCounts];
                      if (count === 0) return null;

                      return (
                        <Tooltip key={key}>
                          <TooltipTrigger asChild>
                            <div className="relative cursor-pointer group">
                              <IconComponent size={20} color={color} />
                              {/* Badge with count */}
                              <div
                                className="absolute -bottom-1 -right-1 flex items-center justify-center min-w-[14px] h-[14px] px-0.5 rounded-full text-[9px] font-black text-white"
                                style={{ backgroundColor: color }}
                              >
                                {count}
                              </div>
                            </div>
                          </TooltipTrigger>
                          <TooltipContent
                            className="bg-gray-800 text-gray-100 border max-w-xs"
                            style={{ borderColor: color }}
                            sideOffset={5}
                          >
                            <div
                              className="font-semibold mb-1"
                              style={{ color }}
                            >
                              {label}
                            </div>
                            <div className="text-xs text-gray-300">
                              {description}
                            </div>
                            <div className="text-xs text-gray-400 mt-1">
                              Earned <span className="font-black">{count}</span>{' '}
                              time{count !== 1 ? 's' : ''} in this period
                            </div>
                          </TooltipContent>
                        </Tooltip>
                      );
                    }
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-6">
        {/* Stats Grid */}
        <div>
          <div className="grid grid-cols-2 gap-3">
            {/* Average Impact Rating */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Avg Impact Rating</span>
              <span
                className={`font-medium ${getImpactColor(playerCard.average_impact)}`}
              >
                {playerCard.average_impact.toFixed(2)}
              </span>
            </div>

            {/* Average Round Swing */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-400">Avg Round Swing</span>
              <span
                className={`font-medium ${getRoundSwingColor(playerCard.average_round_swing)}`}
              >
                {playerCard.average_round_swing.toFixed(1)}%
              </span>
            </div>

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
