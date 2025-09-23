import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { PlayerCard } from './head-to-head/player-card';

interface SteamProfile {
  persona_name: string;
  profile_url: string;
  avatar: string;
  avatar_medium: string;
  avatar_full: string;
  persona_state: number;
  community_visibility_state: number;
}

interface Player {
  steam_id: string;
  name: string;
  steam_profile?: SteamProfile;
}

interface PlayerComplexion {
  opener: number;
  closer: number;
  support: number;
  fragger: number;
}

interface BasicStats {
  kills: number;
  deaths: number;
  adr: number;
  assists: number;
  headshots: number;
  first_kills: number;
  first_deaths: number;
  total_impact: number;
  impact_percentage: number;
  match_swing_percent: number;
}

interface RoleStatValue {
  value: number;
  higherIsBetter: boolean;
}

interface RoleStats {
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
  basic_stats: BasicStats;
  player_complexion: PlayerComplexion;
  role_stats: RoleStats;
  utility_analysis: any;
  rank_data: RankData;
}

interface MatchData {
  game_mode: string | null;
  match_type: string;
}

interface HeadToHeadData {
  players: Player[];
  current_user_steam_id: string;
  match_data: MatchData;
  player1?: PlayerStats;
  player2?: PlayerStats;
}

interface HeadToHeadProps {
  matchId: number;
}

export function HeadToHead({ matchId }: HeadToHeadProps) {
  const [data, setData] = useState<HeadToHeadData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedPlayer1, setSelectedPlayer1] = useState<string | null>(null);
  const [selectedPlayer2, setSelectedPlayer2] = useState<string | null>(null);

  const fetchPlayerStats = async (
    playerSteamId: string,
    playerKey: 'player1' | 'player2'
  ) => {
    try {
      const response = await api.get(
        `/matches/${matchId}/head-to-head/player`,
        {
          params: { player_steam_id: playerSteamId },
          requireAuth: true,
        }
      );

      setData(prev =>
        prev
          ? {
              ...prev,
              [playerKey]: response.data,
            }
          : null
      );
    } catch (err) {
      console.error(`Error fetching ${playerKey} stats:`, err);
    }
  };

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        const response = await api.get(`/matches/${matchId}/head-to-head`, {
          requireAuth: true,
        });
        const result = response.data;

        const headToHeadData = result as HeadToHeadData;
        setData(headToHeadData);

        // Set default player selections and fetch their stats
        if (headToHeadData.players && headToHeadData.players.length > 0) {
          // If current user participated, select them as player 1
          const currentUserPlayer = headToHeadData.players.find(
            (player: Player) =>
              player.steam_id === headToHeadData.current_user_steam_id
          );

          let player1Id: string;
          if (currentUserPlayer) {
            player1Id = currentUserPlayer.steam_id;
          } else {
            player1Id = headToHeadData.players[0].steam_id;
          }

          setSelectedPlayer1(player1Id);

          // Fetch stats for player 1
          await fetchPlayerStats(player1Id, 'player1');

          // Select second player if available
          if (headToHeadData.players.length > 1) {
            const player2Id = headToHeadData.players[1].steam_id;
            setSelectedPlayer2(player2Id);
            // Fetch stats for player 2
            await fetchPlayerStats(player2Id, 'player2');
          }
        }
      } catch (err) {
        console.error('Error fetching head-to-head data:', err);
        setError('Failed to load head-to-head data');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [matchId]);

  const handlePlayer1Change = async (playerSteamId: string) => {
    setSelectedPlayer1(playerSteamId);
    await fetchPlayerStats(playerSteamId, 'player1');
  };

  const handlePlayer2Change = async (playerSteamId: string) => {
    setSelectedPlayer2(playerSteamId);
    await fetchPlayerStats(playerSteamId, 'player2');
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-gray-400">Loading head-to-head data...</div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-red-400">{error || 'Failed to load data'}</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <PlayerCard
          players={data.players}
          selectedPlayer={selectedPlayer1}
          onPlayerChange={handlePlayer1Change}
          playerStats={data.player1}
          comparisonStats={data.player2}
          matchData={data.match_data}
        />

        <PlayerCard
          players={data.players}
          selectedPlayer={selectedPlayer2}
          onPlayerChange={handlePlayer2Change}
          playerStats={data.player2}
          comparisonStats={data.player1}
          matchData={data.match_data}
        />
      </div>
    </div>
  );
}
