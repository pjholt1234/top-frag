import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { MatchesTable } from '@/components/your-matches/table';
import { MatchesFilters } from '@/components/your-matches/filters';
import { MatchesTableSkeleton } from '@/components/your-matches/table-skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';

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
  step_progress: number | null;
  total_steps: number | null;
  current_step_num: number | null;
  start_time: string | null;
  last_update_time: string | null;
  error_code: string | null;
  context: Record<string, any> | null;
  is_final: boolean | null;
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

interface ClanMember {
  id: number;
  name: string;
  steam_persona_name: string | null;
}

interface MatchesTabProps {
  clanId: number;
}

export function MatchesTab({ clanId }: MatchesTabProps) {
  const [matches, setMatches] = useState<UnifiedMatch[]>([]);
  const [inProgressJobs, setInProgressJobs] = useState<UnifiedMatch[]>([]);
  const [pagination, setPagination] = useState<PaginationData | undefined>(
    undefined
  );
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [clanMembers, setClanMembers] = useState<ClanMember[]>([]);
  const [selectedMemberIds, setSelectedMemberIds] = useState<number[]>([]);
  const [filters, setFilters] = useState<MatchFilters>({
    map: '',
    match_type: '',
    player_was_participant: '',
    player_won_match: '',
    date_from: '',
    date_to: '',
  });

  // Fetch clan members
  useEffect(() => {
    const fetchMembers = async () => {
      try {
        const response = await api.get<{ data: ClanMember[] }>(
          `/clans/${clanId}/members`,
          {
            requireAuth: true,
          }
        );
        setClanMembers(response.data.data || []);
      } catch (err) {
        console.error('Error fetching clan members:', err);
      }
    };

    fetchMembers();
  }, [clanId]);

  // Fetch matches
  useEffect(() => {
    const fetchMatches = async () => {
      try {
        setLoading(true);
        setError(null);

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

        // Add member filter if any members are selected
        if (selectedMemberIds.length > 0) {
          params.member_ids = selectedMemberIds.join(',');
        }

        const response = await api.get<MatchesResponse>(
          `/clans/${clanId}/matches`,
          {
            requireAuth: true,
            params,
          }
        );

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

        setMatches(completedMatches);
        setInProgressJobs(inProgressJobs);
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
  }, [clanId, currentPage, filters, selectedMemberIds]);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  const handleFiltersChange = (newFilters: MatchFilters) => {
    setFilters(newFilters);
    setCurrentPage(1);
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
    setSelectedMemberIds([]);
    setCurrentPage(1);
  };

  const handleMemberToggle = (memberId: number) => {
    setSelectedMemberIds(prev => {
      if (prev.includes(memberId)) {
        return prev.filter(id => id !== memberId);
      } else {
        return [...prev, memberId];
      }
    });
    setCurrentPage(1);
  };

  return (
    <div className="space-y-6">
      {/* Member Filter */}
      {clanMembers.length > 0 && (
        <Card>
          <CardContent className="pt-6">
            <div className="space-y-2">
              <Label>Filter by Clan Member</Label>
              <Select
                value={
                  selectedMemberIds.length === 0
                    ? 'all'
                    : selectedMemberIds.length === 1
                      ? selectedMemberIds[0].toString()
                      : 'multiple'
                }
                onValueChange={value => {
                  if (value === 'all') {
                    setSelectedMemberIds([]);
                  } else if (value === 'multiple') {
                    // Keep current selection
                    return;
                  } else {
                    setSelectedMemberIds([parseInt(value)]);
                  }
                  setCurrentPage(1);
                }}
              >
                <SelectTrigger className="w-full">
                  <SelectValue
                    placeholder={
                      selectedMemberIds.length === 0
                        ? 'All members'
                        : selectedMemberIds.length === 1
                          ? clanMembers.find(m => m.id === selectedMemberIds[0])
                              ?.steam_persona_name ||
                            clanMembers.find(m => m.id === selectedMemberIds[0])
                              ?.name ||
                            'Selected member'
                          : `${selectedMemberIds.length} members selected`
                    }
                  />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All members</SelectItem>
                  {clanMembers.map(member => (
                    <SelectItem key={member.id} value={member.id.toString()}>
                      {member.steam_persona_name || member.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {selectedMemberIds.length > 0 && (
                <div className="flex flex-wrap gap-2 mt-2">
                  {selectedMemberIds.map(memberId => {
                    const member = clanMembers.find(m => m.id === memberId);
                    return (
                      <div
                        key={memberId}
                        className="flex items-center gap-1 px-2 py-1 bg-muted rounded text-sm"
                      >
                        <span>
                          {member?.steam_persona_name || member?.name}
                        </span>
                        <button
                          onClick={() => handleMemberToggle(memberId)}
                          className="ml-1 hover:text-destructive"
                        >
                          ×
                        </button>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      )}

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
              {Object.values(filters).some(value => value !== '') ||
              selectedMemberIds.length > 0
                ? 'Try adjusting your filters'
                : 'No matches found for this clan'}
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
}
