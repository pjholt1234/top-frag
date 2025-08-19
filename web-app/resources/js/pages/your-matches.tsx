import { MatchesTable } from '@/components/matches-table';
import { MatchesFilters } from '@/components/matches-filters';
import { MatchesTableSkeleton } from '@/components/matches-table-skeleton';
import { UploadDemoModal } from '@/components/upload-demo-modal';
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
  id: number;
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

interface MatchesResponse {
  data: UnifiedMatch[];
  pagination: PaginationData;
}

interface MatchFilters {
  map: string;
  match_type: string;
  player_was_participant: string;
  player_won_match: string;
  date_from: string;
  date_to: string;
}

const YourMatches = () => {
  const [matches, setMatches] = useState<UnifiedMatch[]>([]);
  const [inProgressJobs, setInProgressJobs] = useState<UnifiedMatch[]>([]);
  const [pagination, setPagination] = useState<PaginationData | undefined>(
    undefined
  );
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [filters, setFilters] = useState<MatchFilters>({
    map: '',
    match_type: '',
    player_was_participant: '',
    player_won_match: '',
    date_from: '',
    date_to: '',
  });

  // Poll matches every 5 seconds to get real-time updates including in-progress jobs
  useEffect(() => {
    const pollMatches = async () => {
      try {
        // Build query parameters
        const params: Record<string, string> = {
          page: currentPage.toString(),
        };

        // Add filters to params
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== '') {
            params[key] = value;
          }
        });

        const response = await api.get<MatchesResponse>('/matches', {
          requireAuth: true,
          params,
        });

        console.log('API Response:', response.data);
        console.log('All matches:', response.data.data);

        // Ensure we have valid data structure
        if (!response.data.data || !Array.isArray(response.data.data)) {
          throw new Error(
            'Invalid response format: missing or invalid data array'
          );
        }

        // Extract in-progress jobs from the unified response
        const inProgressJobs = response.data.data.filter(
          match => match && typeof match === 'object' && !match.is_completed
        );
        const completedMatches = response.data.data.filter(
          match => match && typeof match === 'object' && match.is_completed
        );

        console.log('In-progress jobs:', inProgressJobs);
        console.log('Completed matches:', completedMatches);

        setMatches(completedMatches);
        setInProgressJobs(inProgressJobs);
        setPagination(response.data.pagination);
        setLoading(false);
        setError(null);
      } catch (err: unknown) {
        console.error('Error polling matches:', err);
        const errorMessage =
          err instanceof Error ? err.message : 'Failed to fetch matches';
        setError(errorMessage);
        setLoading(false);
      }
    };

    // Initial call
    pollMatches();

    // Set up interval for polling every 2 seconds
    const interval = setInterval(pollMatches, 2000);

    // Cleanup interval on component unmount
    return () => clearInterval(interval);
  }, [currentPage, filters]);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  const handleFiltersChange = (newFilters: MatchFilters) => {
    setFilters(newFilters);
    setCurrentPage(1); // Reset to first page when filters change
  };

  const handleClearFilters = () => {
    setFilters({
      map: '',
      match_type: '',
      player_was_participant: '',
      player_won_match: '',
      date_from: '',
      date_to: '',
    });
    setCurrentPage(1);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between mt-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Your Matches</h1>
          <p className="text-muted-foreground">
            View your match history and detailed player statistics
          </p>
        </div>
        <UploadDemoModal
          onUploadSuccess={() => {
            // Refresh matches after successful upload
            setCurrentPage(1);
          }}
        />
      </div>

      <MatchesFilters
        filters={filters}
        onFiltersChange={handleFiltersChange}
        onClearFilters={handleClearFilters}
      />

      {error ? (
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <p className="text-destructive mb-2">Error loading matches</p>
            <p className="text-muted-foreground text-sm">{error}</p>
          </div>
        </div>
      ) : loading ? (
        <MatchesTableSkeleton rows={10} />
      ) : matches.length === 0 && inProgressJobs.length === 0 ? (
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <p className="text-muted-foreground mb-2">No matches found</p>
            <p className="text-sm text-muted-foreground">
              {Object.values(filters).some(value => value !== '')
                ? 'Try adjusting your filters or upload a demo file to see your matches here'
                : 'Upload a demo file to see your matches here'}
            </p>
          </div>
        </div>
      ) : (
        <MatchesTable
          matches={[...inProgressJobs, ...matches]}
          pagination={pagination}
          onPageChange={handlePageChange}
        />
      )}
    </div>
  );
};

export default YourMatches;
