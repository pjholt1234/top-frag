import { useState } from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { getRankDisplay } from '@/lib/rank-utils';
import { BasicStats } from './basic-stats';
import { RoleStats } from './role-stats';

interface Player {
  steam_id: string;
  name: string;
}

interface PlayerComplexion {
  opener: number;
  closer: number;
  support: number;
  fragger: number;
}

interface BasicStatsData {
  kills: number;
  deaths: number;
  adr: number;
  assists: number;
  headshots: number;
  first_kills: number;
  first_deaths: number;
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

interface RankData {
  rank_value: number;
  rank_type: string;
}

interface PlayerStats {
  basic_stats: BasicStatsData;
  player_complexion: PlayerComplexion;
  role_stats: RoleStatsData;
  utility_analysis: any;
  rank_data: RankData;
}

interface MatchData {
  game_mode: string | null;
  match_type: string;
}

interface PlayerCardProps {
  players: Player[];
  selectedPlayer: string | null;
  onPlayerChange: (playerSteamId: string) => void;
  playerStats?: PlayerStats;
  comparisonStats?: PlayerStats;
  matchData?: MatchData;
}

export function PlayerCard({
  players,
  selectedPlayer,
  onPlayerChange,
  playerStats,
  comparisonStats,
  matchData,
}: PlayerCardProps) {
  const [expandedRoles, setExpandedRoles] = useState<Set<string>>(new Set());

  const toggleRoleExpansion = (role: string) => {
    setExpandedRoles(prev => {
      const newSet = new Set(prev);
      if (newSet.has(role)) {
        newSet.delete(role);
      } else {
        newSet.add(role);
      }
      return newSet;
    });
  };

  const selectedPlayerData = players.find(p => p.steam_id === selectedPlayer);

  return (
    <Card className="h-fit">
      <CardHeader>
        <div className="flex items-center gap-4">
          <Select value={selectedPlayer || ''} onValueChange={onPlayerChange}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Select a player" />
            </SelectTrigger>
            <SelectContent>
              {players.map(player => (
                <SelectItem key={player.steam_id} value={player.steam_id}>
                  {player.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {selectedPlayerData && playerStats && (
            <div className="flex items-center gap-2">
              {getRankDisplay({
                gameMode: matchData?.game_mode,
                matchType: matchData?.match_type,
                rankValue: playerStats.rank_data?.rank_value || 0,
                variant: 'md',
              }) || <span className="text-sm text-gray-500">No Rank</span>}
            </div>
          )}
        </div>
      </CardHeader>

      <CardContent className="space-y-4">
        {playerStats ? (
          <>
            <div className="grid grid-cols-3 gap-6">
              {/* Player Profile Photo - 1/3 width */}
              <div className="flex justify-center items-center">
                {selectedPlayerData?.steam_profile?.avatar_full && (
                  <img
                    src={selectedPlayerData.steam_profile.avatar_full}
                    alt={`${selectedPlayerData.name} profile`}
                    className="w-32 h-32 rounded-full border-2 border-gray-600"
                    onError={e => {
                      // Fallback to medium avatar if full fails
                      if (selectedPlayerData?.steam_profile?.avatar_medium) {
                        e.currentTarget.src =
                          selectedPlayerData.steam_profile.avatar_medium;
                      } else {
                        e.currentTarget.style.display = 'none';
                      }
                    }}
                  />
                )}
              </div>

              {/* Basic Stats - 2/3 width */}
              <div className="col-span-2">
                <BasicStats
                  stats={playerStats.basic_stats}
                  comparisonStats={comparisonStats?.basic_stats}
                />
              </div>
            </div>

            <RoleStats
              complexion={playerStats.player_complexion}
              roleStats={playerStats.role_stats}
              comparisonRoleStats={comparisonStats?.role_stats}
              expandedRoles={expandedRoles}
              onToggleRole={toggleRoleExpansion}
            />
          </>
        ) : (
          <div className="flex items-center justify-center h-32">
            <div className="text-gray-400">Select a player to view stats</div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
