import React, { useState } from 'react';
import { IconChevronDown, IconChevronRight } from '@tabler/icons-react';
import { Button } from '@/components/ui/button';
import { Scoreboard } from '@/components/shared/scoreboard';
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

interface Scoreboard {
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
  player_won_match?: boolean;
  player_was_participant?: boolean;
  player_team?: string;
}

interface UnifiedMatch {
  id: number;
  created_at: string;
  is_completed: boolean;
  match_details: MatchDetails | null;
  player_stats: Scoreboard[] | null;
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

type SortColumn =
  | 'player_name'
  | 'player_kill_death_ratio'
  | 'player_kills'
  | 'player_deaths'
  | 'player_first_kill_differential'
  | 'player_adr';
type SortDirection = 'asc' | 'desc';

export function MatchesTable({
  matches,
  pagination,
  onPageChange,
}: MatchesTableProps) {
  const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());
  const [matchSortStates, setMatchSortStates] = useState<
    Record<number, { column: SortColumn; direction: SortDirection }>
  >({});

  const toggleRow = (matchId: number) => {
    const newExpanded = new Set(expandedRows);
    if (newExpanded.has(matchId)) {
      newExpanded.delete(matchId);
    } else {
      newExpanded.add(matchId);
    }
    setExpandedRows(newExpanded);
  };

  const handleSort = (matchId: number, column: SortColumn) => {
    const currentSort = matchSortStates[matchId];

    if (currentSort && currentSort.column === column) {
      // Toggle direction for same column
      setMatchSortStates(prev => ({
        ...prev,
        [matchId]: {
          column,
          direction: currentSort.direction === 'asc' ? 'desc' : 'asc',
        },
      }));
    } else {
      // Set new column with default direction
      setMatchSortStates(prev => ({
        ...prev,
        [matchId]: {
          column,
          direction: 'desc',
        },
      }));
    }
  };

  const formatDate = (dateString: string) => {
    if (!dateString) {
      return 'Unknown';
    }
    try {
      const date = new Date(dateString);
      console.log('Parsed date:', date, 'isValid:', !isNaN(date.getTime()));
      if (isNaN(date.getTime())) {
        console.warn('Invalid date:', dateString);
        return 'Invalid Date';
      }
      return date.toLocaleDateString('en-US', {
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

  const capitalizeFirst = (str: string) => {
    const [first, ...rest] = str;
    return first.toUpperCase() + rest.join('');
  };

  const getMatchResult = (match: UnifiedMatch) => {
    if (!match.is_completed || !match.match_details) {
      return { text: 'Processing...', color: 'text-muted-foreground' };
    }

    const { player_was_participant, player_won_match } = match.match_details;

    // For backward compatibility, if the new fields don't exist, assume participation
    if (player_was_participant === undefined) {
      // Fallback to old behavior - assume player participated and won
      return { text: 'Win', color: 'text-green-600 dark:text-green-400' };
    }

    // If player didn't participate, show "Unknown"
    if (!player_was_participant) {
      return { text: 'Unknown', color: 'text-muted-foreground' };
    }

    // If player participated, show Win/Loss
    if (player_won_match) {
      return { text: 'Win', color: 'text-green-600 dark:text-green-400' };
    } else {
      return { text: 'Loss', color: 'text-red-600 dark:text-red-400' };
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
            const allPlayers = match.player_stats || [];

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
                    <button
                      onClick={() =>
                        (window.location.href = `/matches/${matchId}`)
                      }
                      className="text-left hover:text-blue-400 hover:underline cursor-pointer transition-colors"
                    >
                      {match.match_details?.map || 'Unknown Map'}
                    </button>
                  </TableCell>
                  <TableCell>
                    <span className="font-mono">
                      {match.match_details
                        ? `${match.match_details.winning_team_score} - ${match.match_details.losing_team_score}`
                        : 'Processing...'}
                    </span>
                  </TableCell>
                  <TableCell>
                    {(() => {
                      const result = getMatchResult(match);
                      return (
                        <span className={result.color}>{result.text}</span>
                      );
                    })()}
                  </TableCell>
                  <TableCell>
                    {match.match_details
                      ? capitalizeFirst(match.match_details.match_type) ||
                        'Unknown'
                      : 'Processing...'}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center justify-between">
                      <span>
                        {formatDate(
                          match.match_details?.created_at || match.created_at
                        )}
                      </span>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                          (window.location.href = `/matches/${matchId}`)
                        }
                        className="ml-2 h-6 px-2 text-xs"
                      >
                        View
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
                {isExpanded && (
                  <TableRow>
                    <TableCell colSpan={6} className="p-0">
                      <Scoreboard
                        players={allPlayers}
                        variant="expanded"
                        sortColumn={matchSortStates[matchId]?.column}
                        sortDirection={matchSortStates[matchId]?.direction}
                        onSort={column => handleSort(matchId, column)}
                        match={match}
                      />
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
