import React, { useState } from 'react';
import { IconChevronDown, IconChevronRight } from '@tabler/icons-react';
import { Button } from '@/components/ui/button';
import { getAdrColor } from '@/lib/utils';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Pagination } from '@/components/ui/pagination';
import { Badge } from '@/components/ui/badge';

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

interface UnifiedMatch {
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

interface PaginationData {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number;
  to: number;
}

interface MatchesTableProps {
  matches: UnifiedMatch[];
  pagination?: PaginationData;
  onPageChange?: (page: number) => void;
}

export function MatchesTable({
  matches,
  pagination,
  onPageChange,
}: MatchesTableProps) {
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());

  console.log('MatchesTable received matches:', matches);

  const toggleRow = (matchId: number) => {
    const newExpanded = new Set(expandedRows);
    if (newExpanded.has(matchId)) {
      newExpanded.delete(matchId);
    } else {
      newExpanded.add(matchId);
    }
    setExpandedRows(newExpanded);
  };

  const formatDate = (dateString: string) => {
    if (!dateString) {
      return 'Unknown';
    }
    try {
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch (error) {
      console.warn('Error formatting date:', dateString, error);
      return 'Invalid Date';
    }
  };

  const getTeamPlayers = (playerStats: PlayerStats[], team: string) => {
    if (!Array.isArray(playerStats)) {
      console.warn('playerStats is not an array:', playerStats);
      return [];
    }
    return playerStats.filter(player => player && player.team === team);
  };

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case 'processing':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
      case 'queued':
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
      case 'error':
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
      default:
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
  };

  return (
    <div className="space-y-4">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-12"></TableHead>
            <TableHead>Map</TableHead>
            <TableHead>Score</TableHead>
            <TableHead>Result</TableHead>
            <TableHead>Match Type</TableHead>
            <TableHead>Date</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {matches.map(match => {
            // Handle in-progress jobs
            if (!match.is_completed) {
              return (
                <TableRow
                  key={`in-progress-${match.id}`}
                  className="bg-muted/30"
                >
                  <TableCell>
                    <div className="w-6 h-6 flex items-center justify-center">
                      <div className="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                    </div>
                  </TableCell>
                  <TableCell className="font-medium">
                    <div className="flex items-center space-x-2">
                      <span>{match.match_details?.map || 'Processing...'}</span>
                      {match.processing_status &&
                        match.progress_percentage !== null && (
                          <Badge
                            className={getStatusColor(match.processing_status)}
                          >
                            {match.progress_percentage}%
                          </Badge>
                        )}
                      {match.error_message && (
                        <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                          Error
                        </Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="text-muted-foreground">
                      {match.error_message ? 'Error' : 'Processing...'}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="text-muted-foreground">
                      {match.error_message ? 'Error' : 'Processing...'}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="text-muted-foreground">
                      {match.error_message ? 'Error' : 'Processing...'}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="text-muted-foreground">
                      {match.error_message ? 'Error' : 'Processing...'}
                    </span>
                  </TableCell>
                </TableRow>
              );
            }

            // Handle completed matches
            if (!match.match_details || !match.player_stats) {
              console.warn('Match missing required data:', match);
              return null;
            }

            const matchId = match.match_details.id || match.id;
            const isExpanded = expandedRows.has(matchId);
            const teamAPlayers = getTeamPlayers(match.player_stats || [], 'A');
            const teamBPlayers = getTeamPlayers(match.player_stats || [], 'B');

            return (
              <React.Fragment key={matchId}>
                <TableRow>
                  <TableCell>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => toggleRow(matchId)}
                      className="h-6 w-6 p-0"
                    >
                      {isExpanded ? (
                        <IconChevronDown className="h-4 w-4" />
                      ) : (
                        <IconChevronRight className="h-4 w-4" />
                      )}
                    </Button>
                  </TableCell>
                  <TableCell className="font-medium">
                    {match.match_details.map}
                  </TableCell>
                  <TableCell>
                    <span className="font-mono">
                      {match.match_details.winning_team_score} -{' '}
                      {match.match_details.losing_team_score}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="text-green-600 dark:text-green-400">
                      Win
                    </span>
                  </TableCell>
                  <TableCell>
                    {match.match_details.match_type || 'Unknown'}
                  </TableCell>
                  <TableCell>
                    {formatDate(match.match_details.created_at)}
                  </TableCell>
                </TableRow>
                {isExpanded && (
                  <TableRow>
                    <TableCell colSpan={6} className="p-0">
                      <div className="bg-muted/50">
                        <div className="flex flex-col lg:flex-row">
                          {/* Team A */}
                          <div className="lg:flex-1 lg:border-r lg:border-border lg:border-b lg:border-border">
                            <Table className="border-0 w-full table-fixed">
                              <TableHeader>
                                <TableRow className="border-b border-border">
                                  <TableHead className="text-sm py-2 pl-6 pr-3 border-0 w-1/3">
                                    Player
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    K/D
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    Kills
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    Deaths
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    FK +/-
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    ADR
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
                                      {player.player_name ||
                                        `Player ${index + 1}`}
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
                                        {player.player_kill_death_ratio.toFixed(
                                          2
                                        )}
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
                                          player.player_first_kill_differential >
                                            0
                                            ? 'text-green-600 dark:text-green-400'
                                            : player.player_first_kill_differential <
                                              0
                                              ? 'text-red-600 dark:text-red-400'
                                              : ''
                                        }
                                      >
                                        {player.player_first_kill_differential >
                                          0
                                          ? '+'
                                          : ''}
                                        {player.player_first_kill_differential}
                                      </span>
                                    </TableCell>
                                    <TableCell className="text-sm font-bold py-2 px-3 border-0">
                                      <span
                                        className={getAdrColor(
                                          player.player_adr
                                        )}
                                      >
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
                            <Table className="border-0 w-full table-fixed">
                              <TableHeader>
                                <TableRow className="border-b border-border">
                                  <TableHead className="text-sm py-2 pl-6 pr-3 border-0 w-1/3">
                                    Player
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    K/D
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    Kills
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    Deaths
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    FK +/-
                                  </TableHead>
                                  <TableHead className="text-sm py-2 px-3 border-0 w-1/6">
                                    ADR
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
                                      {player.player_name ||
                                        `Player ${index + 1}`}
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
                                        {player.player_kill_death_ratio.toFixed(
                                          2
                                        )}
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
                                          player.player_first_kill_differential >
                                            0
                                            ? 'text-green-600 dark:text-green-400'
                                            : player.player_first_kill_differential <
                                              0
                                              ? 'text-red-600 dark:text-red-400'
                                              : ''
                                        }
                                      >
                                        {player.player_first_kill_differential >
                                          0
                                          ? '+'
                                          : ''}
                                        {player.player_first_kill_differential}
                                      </span>
                                    </TableCell>
                                    <TableCell className="text-sm font-bold py-2 px-3 border-0">
                                      <span
                                        className={getAdrColor(
                                          player.player_adr
                                        )}
                                      >
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
                    </TableCell>
                  </TableRow>
                )}
              </React.Fragment>
            );
          })}
        </TableBody>
      </Table>

      {pagination && onPageChange && (
        <div className="mt-4">
          <Pagination
            currentPage={pagination.current_page}
            lastPage={pagination.last_page}
            total={pagination.total}
            perPage={pagination.per_page}
            onPageChange={onPageChange}
          />
        </div>
      )}
    </div>
  );
}
