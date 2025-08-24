import React, { useState } from 'react';
import {
  IconChevronDown,
  IconChevronRight,
  IconChevronUp,
} from '@tabler/icons-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { getAdrColor } from '@/lib/utils';

interface PlayerStats {
  player_name: string;
  player_kills: number;
  player_deaths: number;
  player_first_kill_differential: number;
  player_kill_death_ratio: number;
  player_adr: number;
  team: string;
}

interface MatchDetails {
  id: number | null;
  map: string;
  winning_team_score: number;
  losing_team_score: number;
  winning_team: string;
  match_type: string;
  created_at: string;
}

interface Match {
  id: number;
  created_at: string;
  is_completed: boolean;
  match_details: MatchDetails | null;
  player_stats: PlayerStats[] | null;
  processing_status: string | null;
  progress_percentage: number | null;
  current_step: string | null;
  error_message: string | null;
}

type SortColumn =
  | 'player_name'
  | 'player_kill_death_ratio'
  | 'player_kills'
  | 'player_deaths'
  | 'player_first_kill_differential'
  | 'player_adr';
type SortDirection = 'asc' | 'desc';

interface PlayerStatsTableProps {
  players: PlayerStats[];
  variant?: 'expanded' | 'full';
  className?: string;
  // For expanded variant, allow external sorting state
  sortColumn?: SortColumn;
  sortDirection?: SortDirection;
  onSort?: (column: SortColumn) => void;
  // Add match data for team names and win/loss indicators
  match?: Match | null;
}

export function PlayerStatsTable({
  players,
  variant = 'full',
  className = '',
  sortColumn: externalSortColumn,
  sortDirection: externalSortDirection,
  onSort,
  match
}: PlayerStatsTableProps) {
  const [internalSortColumn, setInternalSortColumn] = useState<SortColumn>('player_kill_death_ratio');
  const [internalSortDirection, setInternalSortDirection] = useState<SortDirection>('desc');

  // Use external state for expanded variant, internal state for full variant
  const sortColumn = variant === 'expanded' ? externalSortColumn || internalSortColumn : internalSortColumn;
  const sortDirection = variant === 'expanded' ? externalSortDirection || internalSortDirection : internalSortDirection;

  const handleSort = (column: SortColumn) => {
    if (variant === 'expanded' && onSort) {
      // Use external sort handler for expanded variant
      onSort(column);
    } else {
      // Use internal sort state for full variant
      if (internalSortColumn === column) {
        setInternalSortDirection(internalSortDirection === 'asc' ? 'desc' : 'asc');
      } else {
        setInternalSortColumn(column);
        setInternalSortDirection('desc');
      }
    }
  };

  const sortedPlayers = [...players].sort((a, b) => {
    const aValue = a[sortColumn];
    const bValue = b[sortColumn];

    if (typeof aValue === 'string' && typeof bValue === 'string') {
      return sortDirection === 'asc'
        ? aValue.localeCompare(bValue)
        : bValue.localeCompare(aValue);
    }

    if (typeof aValue === 'number' && typeof bValue === 'number') {
      return sortDirection === 'asc' ? aValue - bValue : bValue - aValue;
    }

    return 0;
  });

  const getSortIcon = (column: SortColumn) => {
    if (sortColumn !== column) {
      return <IconChevronRight className="h-4 w-4 ml-1 opacity-50" />;
    }
    return sortDirection === 'asc' ? (
      <IconChevronUp className="h-4 w-4 ml-1" />
    ) : (
      <IconChevronDown className="h-4 w-4 ml-1" />
    );
  };

  const getTeamColor = (team: string) => {
    return team === 'A' ? 'bg-blue-500' : 'bg-orange-500';
  };

  const sortPlayers = (players: PlayerStats[], matchId: number) => {
    return players.sort((a, b) => {
      const aValue = a[sortColumn];
      const bValue = b[sortColumn];

      if (typeof aValue === 'string' && typeof bValue === 'string') {
        return sortDirection === 'asc'
          ? aValue.localeCompare(bValue)
          : bValue.localeCompare(aValue);
      }

      if (typeof aValue === 'number' && typeof bValue === 'number') {
        return sortDirection === 'asc' ? aValue - bValue : bValue - aValue;
      }

      return 0;
    });
  };

  // For expanded variant (used in matches table), separate by teams
  if (variant === 'expanded') {
    const sortedPlayers = sortPlayers(players, 0);
    const teamAPlayers = sortedPlayers.filter(player => player.team === 'A');
    const teamBPlayers = sortedPlayers.filter(player => player.team === 'B');

    // Get team scores and determine winner
    const teamAScore = match?.match_details?.winning_team === 'A'
      ? match.match_details.winning_team_score
      : match?.match_details?.losing_team_score || 0;
    const teamBScore = match?.match_details?.winning_team === 'B'
      ? match.match_details.winning_team_score
      : match?.match_details?.losing_team_score || 0;

    const teamAWon = match?.match_details?.winning_team === 'A';
    const teamBWon = match?.match_details?.winning_team === 'B';

    return (
      <div className={`bg-muted/50 ${className}`}>
        <div className="flex flex-col lg:flex-row">
          {/* Team A */}
          <div className="lg:flex-1 lg:border-r lg:border-border lg:border-b lg:border-border">
            {/* Team A Header */}
            <div className={`border-b border-border px-6 py-3 ${teamAWon ? 'bg-green-500/10' : 'bg-red-500/10'}`}>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className={`w-3 h-3 rounded-full ${teamAWon ? 'bg-green-500' : 'bg-red-500'}`}></div>
                  <span className={`font-semibold ${teamAWon ? 'text-green-500' : 'text-red-500'}`}>Team A</span>
                  {match?.match_details && (
                    <span className={`text-sm font-medium ${teamAWon ? 'text-green-500' : 'text-red-500'}`}>
                      {teamAWon ? "WIN" : "LOSS"}
                    </span>
                  )}
                </div>
              </div>
            </div>

            <Table className="border-0 w-full table-fixed">
              <TableHeader>
                <TableRow className="border-b border-border">
                  <TableHead
                    className="text-sm py-2 pl-6 pr-3 border-0 w-1/3 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_name')}
                  >
                    <div className="flex items-center">
                      Player
                      {getSortIcon('player_name')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_kill_death_ratio')}
                  >
                    <div className="flex items-center">
                      K/D
                      {getSortIcon('player_kill_death_ratio')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_kills')}
                  >
                    <div className="flex items-center">
                      Kills
                      {getSortIcon('player_kills')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_deaths')}
                  >
                    <div className="flex items-center">
                      Deaths
                      {getSortIcon('player_deaths')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_first_kill_differential')}
                  >
                    <div className="flex items-center">
                      FK +/-
                      {getSortIcon('player_first_kill_differential')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_adr')}
                  >
                    <div className="flex items-center">
                      ADR
                      {getSortIcon('player_adr')}
                    </div>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody className="border-b border-border">
                {teamAPlayers.map((player, index) => (
                  <TableRow
                    key={index}
                    className="border-b border-border"
                  >
                    <TableCell className="text-sm font-medium py-2 pl-6 pr-3 border-0">
                      {player.player_name || `Player ${index + 1}`}
                    </TableCell>
                    <TableCell className="text-sm py-2 px-3 border-0">
                      <span
                        className={
                          player.player_kill_death_ratio > 1
                            ? 'text-green-600 dark:text-green-400'
                            : player.player_kill_death_ratio < 1
                              ? 'text-red-600 dark:text-red-400'
                              : ''
                        }
                      >
                        {player.player_kill_death_ratio.toFixed(2)}
                      </span>
                    </TableCell>
                    <TableCell className="text-sm py-2 px-3 border-0">
                      {player.player_kills}
                    </TableCell>
                    <TableCell className="text-sm py-2 px-3 border-0">
                      {player.player_deaths}
                    </TableCell>
                    <TableCell className="text-sm py-2 px-3 border-0">
                      <span
                        className={
                          player.player_first_kill_differential > 0
                            ? 'text-green-600 dark:text-green-400'
                            : player.player_first_kill_differential < 0
                              ? 'text-red-600 dark:text-red-400'
                              : ''
                        }
                      >
                        {player.player_first_kill_differential > 0 ? '+' : ''}
                        {player.player_first_kill_differential}
                      </span>
                    </TableCell>
                    <TableCell className="text-sm font-bold py-2 px-3 border-0">
                      <span className={getAdrColor(player.player_adr)}>
                        {player.player_adr.toFixed(0)}
                      </span>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {/* Team B */}
          <div className="lg:flex-1 lg:border-b lg:border-border">
            {/* Team B Header */}
            <div className={`border-b border-border px-6 py-3 ${teamBWon ? 'bg-green-500/10' : 'bg-red-500/10'}`}>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className={`w-3 h-3 rounded-full ${teamBWon ? 'bg-green-500' : 'bg-red-500'}`}></div>
                  <span className={`font-semibold ${teamBWon ? 'text-green-500' : 'text-red-500'}`}>Team B</span>
                  {match?.match_details && (
                    <span className={`text-sm font-medium ${teamBWon ? 'text-green-500' : 'text-red-500'}`}>
                      {teamBWon ? "WIN" : "LOSS"}
                    </span>
                  )}
                </div>
              </div>
            </div>

            <Table className="border-0 w-full table-fixed">
              <TableHeader>
                <TableRow className="border-b border-border">
                  <TableHead
                    className="text-sm py-2 pl-6 pr-3 border-0 w-1/3 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_name')}
                  >
                    <div className="flex items-center">
                      Player
                      {getSortIcon('player_name')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_kill_death_ratio')}
                  >
                    <div className="flex items-center">
                      K/D
                      {getSortIcon('player_kill_death_ratio')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_kills')}
                  >
                    <div className="flex items-center">
                      Kills
                      {getSortIcon('player_kills')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_deaths')}
                  >
                    <div className="flex items-center">
                      Deaths
                      {getSortIcon('player_deaths')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_first_kill_differential')}
                  >
                    <div className="flex items-center">
                      FK +/-
                      {getSortIcon('player_first_kill_differential')}
                    </div>
                  </TableHead>
                  <TableHead
                    className="text-sm py-2 px-3 border-0 w-1/6 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => handleSort('player_adr')}
                  >
                    <div className="flex items-center">
                      ADR
                      {getSortIcon('player_adr')}
                    </div>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody className="border-b border-border">
                {teamBPlayers.map((player, index) => (
                  <TableRow
                    key={index}
                    className="border-b border-border"
                  >
                    <TableCell className="text-sm font-medium py-2 pl-6 pr-3 border-0">
                      {player.player_name || `Player ${index + 1}`}
                    </TableCell>
                    <TableCell className="text-sm py-2 px-3 border-0">
                      <span
                        className={
                          player.player_kill_death_ratio > 1
                            ? 'text-green-600 dark:text-green-400'
                            : player.player_kill_death_ratio < 1
                              ? 'text-red-600 dark:text-red-400'
                              : ''
                        }
                      >
                        {player.player_kill_death_ratio.toFixed(2)}
                      </span>
                    </TableCell>
                    <TableCell className="text-sm py-2 px-3 border-0">
                      {player.player_kills}
                    </TableCell>
                    <TableCell className="text-sm py-2 px-3 border-0">
                      {player.player_deaths}
                    </TableCell>
                    <TableCell className="text-sm py-2 px-3 border-0">
                      <span
                        className={
                          player.player_first_kill_differential > 0
                            ? 'text-green-600 dark:text-green-400'
                            : player.player_first_kill_differential < 0
                              ? 'text-red-600 dark:text-red-400'
                              : ''
                        }
                      >
                        {player.player_first_kill_differential > 0 ? '+' : ''}
                        {player.player_first_kill_differential}
                      </span>
                    </TableCell>
                    <TableCell className="text-sm font-bold py-2 px-3 border-0">
                      <span className={getAdrColor(player.player_adr)}>
                        {player.player_adr.toFixed(0)}
                      </span>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </div>
      </div>
    );
  }

  // For full variant (used in match details page), single table with team column
  return (
    <Table className={className}>
      <TableHeader>
        <TableRow>
          <TableHead
            className="cursor-pointer hover:bg-gray-700"
            onClick={() => handleSort('player_name')}
          >
            <div className="flex items-center">
              Player
              {getSortIcon('player_name')}
            </div>
          </TableHead>
          <TableHead
            className="cursor-pointer hover:bg-gray-700"
            onClick={() => handleSort('team')}
          >
            <div className="flex items-center">
              Team
              {getSortIcon('player_name')} {/* Using player_name for team sorting */}
            </div>
          </TableHead>
          <TableHead
            className="cursor-pointer hover:bg-gray-700"
            onClick={() => handleSort('player_kills')}
          >
            <div className="flex items-center">
              Kills
              {getSortIcon('player_kills')}
            </div>
          </TableHead>
          <TableHead
            className="cursor-pointer hover:bg-gray-700"
            onClick={() => handleSort('player_deaths')}
          >
            <div className="flex items-center">
              Deaths
              {getSortIcon('player_deaths')}
            </div>
          </TableHead>
          <TableHead
            className="cursor-pointer hover:bg-gray-700"
            onClick={() => handleSort('player_kill_death_ratio')}
          >
            <div className="flex items-center">
              K/D Ratio
              {getSortIcon('player_kill_death_ratio')}
            </div>
          </TableHead>
          <TableHead
            className="cursor-pointer hover:bg-gray-700"
            onClick={() => handleSort('player_first_kill_differential')}
          >
            <div className="flex items-center">
              First Kill Diff
              {getSortIcon('player_first_kill_differential')}
            </div>
          </TableHead>
          <TableHead
            className="cursor-pointer hover:bg-gray-700"
            onClick={() => handleSort('player_adr')}
          >
            <div className="flex items-center">
              ADR
              {getSortIcon('player_adr')}
            </div>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {sortedPlayers.map((player, index) => (
          <TableRow key={index}>
            <TableCell className="font-medium">{player.player_name}</TableCell>
            <TableCell>
              <Badge className={getTeamColor(player.team)}>
                Team {player.team}
              </Badge>
            </TableCell>
            <TableCell>{player.player_kills}</TableCell>
            <TableCell>{player.player_deaths}</TableCell>
            <TableCell>{player.player_kill_death_ratio}</TableCell>
            <TableCell>
              <span className={player.player_first_kill_differential >= 0 ? 'text-green-400' : 'text-red-400'}>
                {player.player_first_kill_differential >= 0 ? '+' : ''}{player.player_first_kill_differential}
              </span>
            </TableCell>
            <TableCell>
              <span className={getAdrColor(player.player_adr)}>
                {player.player_adr}
              </span>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}
