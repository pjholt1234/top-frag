import { Card, CardContent } from '@/components/ui/card';
import { IconChevronDown, IconChevronUp } from '@tabler/icons-react';
import { COMPLEXION_COLORS } from '@/constants/colors';
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

interface RoleStatValue {
  value: number;
  higherIsBetter: boolean;
}

interface RoleStatsData {
  opener: Record<string, RoleStatValue>;
  closer: Record<string, RoleStatValue>;
  support: Record<string, RoleStatValue>;
  fragger: Record<string, RoleStatValue>;
}

interface RoleStatsProps {
  complexion: PlayerComplexion;
  roleStats: RoleStatsData;
  comparisonRoleStats?: RoleStatsData;
  expandedRoles: Set<string>;
  onToggleRole: (role: string) => void;
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

export function RoleStats({
  complexion,
  roleStats,
  comparisonRoleStats,
  expandedRoles,
  onToggleRole,
}: RoleStatsProps) {
  const getComparisonColor = (
    value: number,
    comparisonValue: number | undefined,
    higherIsBetter: boolean
  ) => {
    if (comparisonValue === undefined) return 'text-gray-200';

    // Debug logging for role stats comparison issues
    if (typeof value !== 'number' || typeof comparisonValue !== 'number') {
      console.warn('Role stats comparison: Invalid data types', {
        value,
        comparisonValue,
        valueType: typeof value,
        comparisonType: typeof comparisonValue,
      });
      return 'text-gray-200';
    }

    if (isNaN(value) || isNaN(comparisonValue)) {
      console.warn('Role stats comparison: NaN values', {
        value,
        comparisonValue,
      });
      return 'text-gray-200';
    }

    if (higherIsBetter) {
      if (value > comparisonValue) return 'text-green-400';
      if (value < comparisonValue) return 'text-red-400';
    } else {
      if (value < comparisonValue) return 'text-green-400';
      if (value > comparisonValue) return 'text-red-400';
    }

    return 'text-gray-200';
  };

  return (
    <div className="space-y-4">
      {roleData.map(({ key, label, color, gaugeColor, IconComponent }) => {
        const score = complexion[key as keyof PlayerComplexion];
        const isExpanded = expandedRoles.has(key);
        const stats = roleStats[key as keyof RoleStatsData];
        const comparisonStats =
          comparisonRoleStats?.[key as keyof RoleStatsData];

        return (
          <Card
            key={key}
            className="overflow-hidden p-0 cursor-pointer hover:opacity-90 transition-opacity"
            onClick={() => onToggleRole(key)}
          >
            <div className="flex">
              {/* Icon Section */}
              <div className="flex items-center justify-center p-4 border-r border-gray-700 bg-gray-800/50">
                <IconComponent size={24} color={gaugeColor} />
              </div>

              {/* Main Content */}
              <CardContent
                className="p-0 flex-1"
                style={{
                  background: `linear-gradient(315deg, ${gaugeColor}30 0%, rgba(31, 41, 55, 0.7) 50%, rgba(31, 41, 55, 0.9) 100%)`,
                }}
              >
                {/* Role Header */}
                <div className="flex items-center w-full p-4">
                  <div className="flex items-center gap-3 flex-shrink-0">
                    <span className={`font-medium ${color}`}>{label}</span>
                  </div>

                  <div className="flex items-center gap-3 flex-1 ml-4">
                    {/* Score Bar */}
                    <div className="flex items-center gap-2 flex-1">
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

                    {/* Expand/Collapse Indicator */}
                    <div className="flex-shrink-0">
                      {isExpanded ? (
                        <IconChevronUp size={16} className="text-gray-400" />
                      ) : (
                        <IconChevronDown size={16} className="text-gray-400" />
                      )}
                    </div>
                  </div>
                </div>

                {/* Expanded Stats */}
                {isExpanded && (
                  <div className="p-4 border-t border-gray-700">
                    <div className="grid grid-cols-1 gap-3 ">
                      {Object.entries(stats).map(([statLabel, statData]) => {
                        const comparisonData = comparisonStats?.[statLabel];
                        const comparisonValue = comparisonData?.value;
                        const higherIsBetter = statData.higherIsBetter;

                        // Ensure values are numbers
                        const safeValue = Number(statData.value) || 0;
                        const safeComparisonValue =
                          comparisonValue !== undefined
                            ? Number(comparisonValue) || 0
                            : undefined;

                        return (
                          <div
                            key={statLabel}
                            className="flex justify-between items-center"
                          >
                            <span className="text-sm text-gray-400">
                              {statLabel}
                            </span>
                            <span
                              className={`text-sm font-medium ${getComparisonColor(safeValue, safeComparisonValue, higherIsBetter)}`}
                            >
                              {safeValue}
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
              </CardContent>
            </div>
          </Card>
        );
      })}
    </div>
  );
}
