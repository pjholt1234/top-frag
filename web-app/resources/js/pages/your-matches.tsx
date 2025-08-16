import { MatchesTable } from '@/components/matches-table';
import { api } from '../lib/api';
import { useState, useEffect } from 'react';

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
  match_id: number;
  map: string;
  winning_team_score: number;
  losing_team_score: number;
  winning_team_name: string | null;
  player_won_match: boolean;
  match_type: string | null;
  match_date: string;
  player_was_participant: boolean;
}

interface Match {
  match_details: MatchDetails;
  player_stats: PlayerStats[];
}

interface PaginationData {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number;
  to: number;
}

interface MatchesResponse {
  data: Match[];
  pagination: PaginationData;
}

const YourMatches = () => {
  const [matches, setMatches] = useState<Match[]>([]);
  const [pagination, setPagination] = useState<PaginationData | undefined>(
    undefined
  );
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);

  useEffect(() => {
    const fetchMatches = async () => {
      try {
        setLoading(true);
        const response = await api.get<MatchesResponse>('/matches', {
          requireAuth: true,
          params: {
            page: currentPage,
          },
        });
        console.log(response.data);
        setMatches(response.data.data);
        setPagination(response.data.pagination);
        setError(null);
      } catch (err: unknown) {
        console.error('Error fetching matches:', err);
        const errorMessage =
          err instanceof Error ? err.message : 'Failed to fetch matches';
        setError(errorMessage);
      } finally {
        setLoading(false);
      }
    };

    fetchMatches();
  }, [currentPage]);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
          <p className="text-muted-foreground">Loading matches...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <p className="text-destructive mb-2">Error loading matches</p>
          <p className="text-muted-foreground text-sm">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Your Matches</h1>
        <p className="text-muted-foreground">
          View your match history and detailed player statistics
        </p>
      </div>

      {matches.length === 0 ? (
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <p className="text-muted-foreground mb-2">No matches found</p>
            <p className="text-sm text-muted-foreground">
              Upload a demo file to see your matches here
            </p>
          </div>
        </div>
      ) : (
        <MatchesTable
          matches={matches}
          pagination={pagination}
          onPageChange={handlePageChange}
        />
      )}
    </div>
  );
};

export default YourMatches;
