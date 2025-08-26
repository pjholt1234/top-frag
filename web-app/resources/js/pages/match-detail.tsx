import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  IconArrowLeft,
  IconMapPin,
  IconCalendar,
  IconUsers,
  IconTrophy,
} from '@tabler/icons-react';
import { PlayerStatsTable } from '@/components/player-stats-table';
import MatchGrenadesView from '../components/match-grenades-view';

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

const MatchDetail = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [match, setMatch] = useState<Match | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchMatch = async () => {
      if (!id) return;

      try {
        setLoading(true);
        const response = await api.get<Match>(`/matches/${id}`, {
          requireAuth: true,
        });
        setMatch(response.data);
        setError(null);
      } catch (err: unknown) {
        console.error('Error fetching match:', err);
        setError(err instanceof Error ? err.message : 'Failed to load match');
      } finally {
        setLoading(false);
      }
    };

    fetchMatch();
  }, [id]);

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="animate-pulse">
          <div className="h-8 bg-gray-700 rounded w-1/4 mb-4"></div>
          <div className="h-64 bg-gray-700 rounded mb-4"></div>
          <div className="h-96 bg-gray-700 rounded"></div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-500 mb-4">Error</h1>
          <p className="text-gray-400 mb-4">{error}</p>
          <Button onClick={() => navigate('/')}>
            <IconArrowLeft className="w-4 h-4 mr-2" />
            Back to Matches
          </Button>
        </div>
      </div>
    );
  }

  if (!match) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-gray-400 mb-4">
            Match Not Found
          </h1>
          <Button onClick={() => navigate('/')}>
            <IconArrowLeft className="w-4 h-4 mr-2" />
            Back to Matches
          </Button>
        </div>
      </div>
    );
  }

  return (
    <>
      <style>
        {`
                    [data-state="active"] {
                        border: none !important;
                        border-bottom: 2px solid #f97316 !important;
                    }
                    
                    [data-state="inactive"] {
                        border: none !important;
                        border-bottom: 2px solid transparent !important;
                    }
                    
                    .border-0 {
                        border: none !important;
                        border-bottom: none !important;
                    }
                    
                    [data-slot="tabs-content"] {
                        border: none !important;
                        border-bottom: none !important;
                    }
                    
                    [role="tab"] {
                        border: none !important;
                        border-bottom: 2px solid transparent !important;
                        transition: border-bottom-color 0.2s ease-in-out !important;
                    }
                    
                    [data-state="active"][role="tab"] {
                        border-bottom: 2px solid #f97316 !important;
                    }
                `}
      </style>
      <div className="container mx-auto px-4 py-8">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <Button variant="ghost" onClick={() => navigate('/')}>
            <IconArrowLeft className="w-4 h-4 mr-2" />
            Back to Matches
          </Button>
        </div>

        {/* Match Details Card */}
        {match.match_details && (
          <Card className="mb-6">
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <CardTitle className="flex items-center gap-2">
                  <IconMapPin className="w-5 h-5" />
                  {match.match_details.map}
                </CardTitle>
                <div className="flex items-center gap-2">
                  <IconCalendar className="w-4 h-4 text-gray-400" />
                  <span className="text-sm text-gray-400">
                    {formatDate(match.match_details.created_at)}
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  <IconUsers className="w-4 h-4 text-gray-400" />
                  <span className="text-sm text-gray-400">
                    {match.match_details.match_type}
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  <IconTrophy className="w-4 h-4 text-gray-400" />
                  <span className="text-sm text-gray-400">
                    {match.match_details.winning_team_score} -{' '}
                    {match.match_details.losing_team_score}
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  <Badge
                    className={
                      match.match_details.winning_team === 'A'
                        ? 'bg-blue-500'
                        : 'bg-orange-500'
                    }
                  >
                    Team {match.match_details.winning_team} Won
                  </Badge>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Processing Status */}
        {!match.is_completed && (
          <Card className="mb-6 border-yellow-500">
            <CardContent className="pt-6">
              <div className="flex items-center gap-2 mb-2">
                <div className="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                <span className="font-medium">Processing Match</span>
              </div>
              {match.progress_percentage !== null && (
                <div className="w-full bg-gray-700 rounded-full h-2 mb-2">
                  <div
                    className="bg-yellow-500 h-2 rounded-full transition-all duration-300"
                    style={{ width: `${match.progress_percentage}%` }}
                  ></div>
                </div>
              )}
              <p className="text-sm text-gray-400">
                {match.current_step || match.processing_status}
              </p>
              {match.error_message && (
                <p className="text-sm text-red-400 mt-2">
                  {match.error_message}
                </p>
              )}
            </CardContent>
          </Card>
        )}

        {/* Tabs Section */}
        {match.is_completed && (
          <Card className="p-0">
            <Tabs defaultValue="player-stats" className="w-full">
              <TabsList className="grid w-full grid-cols-3 bg-transparent p-0 rounded-t-lg mb-6">
                <TabsTrigger
                  value="player-stats"
                  className="bg-transparent rounded-none shadow-none hover:bg-gray-800/50 transition-all duration-200"
                >
                  Player Statistics
                </TabsTrigger>
                <TabsTrigger
                  value="grenades"
                  className="bg-transparent rounded-none shadow-none hover:bg-gray-800/50 transition-all duration-200"
                >
                  Grenades
                </TabsTrigger>
                <TabsTrigger
                  value="details"
                  className="bg-transparent rounded-none shadow-none hover:bg-gray-800/50 transition-all duration-200"
                >
                  Details
                </TabsTrigger>
              </TabsList>

              <TabsContent value="player-stats" className="mt-0 pb-4">
                {match.player_stats && match.player_stats.length > 0 ? (
                  <PlayerStatsTable
                    players={match.player_stats}
                    variant="expanded"
                    match={match}
                  />
                ) : (
                  <p className="text-center text-gray-400">
                    No player statistics available for this match.
                  </p>
                )}
              </TabsContent>

              <TabsContent value="grenades" className="mt-0">
                <CardContent>
                  <MatchGrenadesView
                    hideMapAndMatchFilters={true}
                    showHeader={false}
                    showFavourites={true}
                    initialFilters={{
                      map: match?.match_details?.map || '',
                      matchId: match?.id?.toString() || '',
                      grenadeType: 'fire_grenades',
                    }}
                  />
                </CardContent>
              </TabsContent>

              <TabsContent value="details" className="mt-0">
                <CardContent>
                  <p className="text-center text-gray-400">
                    Additional match details will be displayed here.
                  </p>
                </CardContent>
              </TabsContent>
            </Tabs>
          </Card>
        )}
      </div>
    </>
  );
};

export default MatchDetail;
