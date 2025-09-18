import { useState, useEffect } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { OpenerIcon } from '@/components/icons/opener-icon';
import { CloserIcon } from '@/components/icons/closer-icon';
import { SupportIcon } from '@/components/icons/support-icon';
import { FraggerIcon } from '@/components/icons/fragger-icon';
import { COMPLEXION_COLORS } from '@/constants/colors';
import { api } from '@/lib/api';

interface TopRolePlayer {
  name: string | null;
  steam_id: string | null;
  score: number;
  stats: Record<string, string | number>;
}

interface TopRolePlayers {
  opener: TopRolePlayer;
  closer: TopRolePlayer;
  support: TopRolePlayer;
  fragger: TopRolePlayer;
}

interface TopPlayersProps {
  matchId: number;
}

const roleData = [
  {
    key: 'opener' as const,
    label: 'Opener',
    color: COMPLEXION_COLORS.opener.text,
    hexColor: COMPLEXION_COLORS.opener.hex,
    IconComponent: OpenerIcon,
  },
  {
    key: 'closer' as const,
    label: 'Closer',
    color: COMPLEXION_COLORS.closer.text,
    hexColor: COMPLEXION_COLORS.closer.hex,
    IconComponent: CloserIcon,
  },
  {
    key: 'support' as const,
    label: 'Support',
    color: COMPLEXION_COLORS.support.text,
    hexColor: COMPLEXION_COLORS.support.hex,
    IconComponent: SupportIcon,
  },
  {
    key: 'fragger' as const,
    label: 'Fragger',
    color: COMPLEXION_COLORS.fragger.text,
    hexColor: COMPLEXION_COLORS.fragger.hex,
    IconComponent: FraggerIcon,
  },
];

export function TopPlayers({ matchId }: TopPlayersProps) {
  const [topPlayers, setTopPlayers] = useState<TopRolePlayers | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchTopPlayers = async () => {
      try {
        setLoading(true);
        setError(null);
        const response = await api.get<TopRolePlayers>(
          `/matches/${matchId}/top-role-players`,
          { requireAuth: true }
        );
        setTopPlayers(response.data);
      } catch (err: unknown) {
        console.error('Error fetching top role players:', err);
        setError(
          err instanceof Error ? err.message : 'Failed to load top players'
        );
      } finally {
        setLoading(false);
      }
    };

    fetchTopPlayers();
  }, [matchId]);

  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <Card key={index} className="bg-gray-800 border-gray-700">
            <CardContent className="p-6">
              <div className="animate-pulse">
                <div className="flex items-center justify-center mb-4">
                  <div className="w-8 h-8 bg-gray-700 rounded"></div>
                </div>
                <div className="text-center">
                  <div className="h-4 bg-gray-700 rounded mb-2"></div>
                  <div className="h-4 bg-gray-700 rounded w-3/4 mx-auto"></div>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <Card className="bg-gray-800 border-gray-700 mb-4">
        <CardContent className="p-6 text-center">
          <p className="text-red-400">Error loading top players: {error}</p>
        </CardContent>
      </Card>
    );
  }

  if (!topPlayers) {
    return (
      <Card className="bg-gray-800 border-gray-700 mb-4">
        <CardContent className="p-6 text-center">
          <p className="text-gray-400">No top players data available</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      {roleData.map(({ key, label, color, hexColor, IconComponent }) => {
        const player = topPlayers[key];
        return (
          <Card
            key={key}
            className="bg-gray-800 border-gray-700 hover:border-gray-600 transition-all duration-1000 relative overflow-hidden cursor-pointer group h-40"
            style={{
              background: `linear-gradient(315deg, ${hexColor}30 0%, rgba(31, 41, 55, 0.7) 50%, rgba(31, 41, 55, 0.9) 100%)`,
            }}
            onMouseEnter={e => {
              e.currentTarget.style.background = `linear-gradient(135deg, ${hexColor}30 0%, rgba(31, 41, 55, 0.7) 50%, rgba(31, 41, 55, 0.9) 100%)`;
            }}
            onMouseLeave={e => {
              e.currentTarget.style.background = `linear-gradient(315deg, ${hexColor}30 0%, rgba(31, 41, 55, 0.7) 50%, rgba(31, 41, 55, 0.9) 100%)`;
            }}
          >
            <CardContent className="p-4 h-full relative">
              {/* Icon in top left - slides in on hover */}
              <div className="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <IconComponent
                  size={20}
                  color={hexColor}
                  className="opacity-90"
                />
              </div>

              {/* Main content - slides out on hover */}
              <div className="flex flex-col items-center justify-center h-full transition-transform duration-300 group-hover:-translate-y-16">
                <div className="flex items-center justify-center gap-2 mb-2">
                  <IconComponent
                    size={32}
                    color={hexColor}
                    className="opacity-90"
                  />
                  <div className={`text-lg font-semibold ${color}`}>
                    Top {label}
                  </div>
                </div>
                <div className="text-white flex flex-col items-center justify-center">
                  <div
                    className="font-medium text-center leading-tight my-3"
                    style={{
                      fontSize:
                        player.name && player.name.length > 12
                          ? `${Math.max(1.2, 1.875 - (player.name.length - 12) * 0.08)}rem`
                          : '1.875rem', // 3xl = 1.875rem
                    }}
                  >
                    {player.name}
                  </div>
                  <div className="text-gray-400 text-xs mt-1 group-hover:opacity-0 transition-opacity duration-300">
                    Score: {player.score} / 100
                  </div>
                </div>
              </div>

              {/* Stats content - slides in on hover */}
              <div className="absolute inset-0 p-4 transform translate-y-full group-hover:translate-y-0 transition-transform duration-300 opacity-0 group-hover:opacity-100">
                <div className="h-full flex flex-col">
                  <div
                    className="font-semibold text-sm mb-1 text-center"
                    style={{ color: hexColor }}
                  >
                    {label} Stats
                  </div>
                  <div className="space-y-0.5 flex-1">
                    {Object.entries(player.stats || {}).map(
                      ([statName, statValue]) => (
                        <div
                          key={statName}
                          className="flex justify-between text-xs leading-tight"
                        >
                          <span className="text-gray-300 truncate">
                            {statName}:
                          </span>
                          <span className="text-white font-medium ml-2">
                            {statValue}
                          </span>
                        </div>
                      )
                    )}
                    {/* Debug info - remove this later */}
                    <div className="text-xs text-gray-500 mt-2">
                      Debug: {Object.keys(player.stats || {}).length} stats
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        );
      })}
    </div>
  );
}
