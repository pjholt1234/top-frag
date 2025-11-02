import { Card, CardContent } from '@/components/ui/card';
import { COMPLEXION_COLORS } from '@/constants/colors';
import { OpenerIcon } from '@/components/icons/opener-icon';
import { CloserIcon } from '@/components/icons/closer-icon';
import { SupportIcon } from '@/components/icons/support-icon';
import { FraggerIcon } from '@/components/icons/fragger-icon';
import { TopAimerIcon } from '@/components/icons/top-aimer-icon';
import { ImpactPlayerIcon } from '@/components/icons/impact-player-icon';
import { DifferenceMakerIcon } from '@/components/icons/difference-maker-icon';

interface Achievement {
  award_name: string;
}

interface MatchAchievementsNotificationProps {
  achievements: Achievement[];
}

const achievementData = [
  {
    key: 'fragger',
    label: 'Fragger',
    color: COMPLEXION_COLORS.fragger.hex,
    IconComponent: FraggerIcon,
  },
  {
    key: 'support',
    label: 'Support',
    color: COMPLEXION_COLORS.support.hex,
    IconComponent: SupportIcon,
  },
  {
    key: 'opener',
    label: 'Opener',
    color: COMPLEXION_COLORS.opener.hex,
    IconComponent: OpenerIcon,
  },
  {
    key: 'closer',
    label: 'Closer',
    color: COMPLEXION_COLORS.closer.hex,
    IconComponent: CloserIcon,
  },
  {
    key: 'top_aimer',
    label: 'Top Aimer',
    color: '#f97316',
    IconComponent: TopAimerIcon,
  },
  {
    key: 'impact_player',
    label: 'Impact Player',
    color: '#22c55e',
    IconComponent: ImpactPlayerIcon,
  },
  {
    key: 'difference_maker',
    label: 'Difference Maker',
    color: '#a855f7',
    IconComponent: DifferenceMakerIcon,
  },
];

export function MatchAchievementsNotification({
  achievements,
}: MatchAchievementsNotificationProps) {
  if (!achievements || achievements.length === 0) {
    return null;
  }

  const orangeColor = '#f97316';

  return (
    <Card
      className="mb-6 overflow-hidden p-0"
      style={{
        borderColor: orangeColor,
        background: `linear-gradient(315deg, ${orangeColor}20 0%, rgba(31, 41, 55, 0.7) 50%, rgba(31, 41, 55, 0.9) 100%)`,
      }}
    >
      <CardContent className="p-4">
        <div className="flex items-center gap-4 flex-wrap">
          <div className="italic text-gray-400">
            Achievements earned this match:
          </div>
          <div className="flex items-center gap-3 flex-wrap">
            {achievements.map(achievement => {
              const achievementInfo = achievementData.find(
                a => a.key === achievement.award_name
              );

              if (!achievementInfo) return null;

              const { label, color, IconComponent } = achievementInfo;

              return (
                <div
                  key={achievement.award_name}
                  className="flex items-center gap-2"
                >
                  <IconComponent size={20} color={color} />
                  <span className="text-sm font-medium" style={{ color }}>
                    {label}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
