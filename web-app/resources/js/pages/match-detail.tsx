import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  IconArrowLeft,
  IconCalendar,
  IconUsers,
  IconTrophy,
} from '@tabler/icons-react';
import { Scoreboard } from '@/components/shared/scoreboard';
import MatchGrenadesView from '@/components/match-detail/grenades-view';
import { MatchUtilityAnalysis } from '@/components/match-detail/utility-analysis';
import { MatchPlayerStats } from '@/components/match-detail/player-stats';
import { TopPlayers } from '@/components/match-detail/top-players';
import { HeadToHead } from '@/components/match-detail/head-to-head';
import { AimTracking } from '@/components/match-detail/aim-tracking';
import { MatchAchievementsNotification } from '@/components/match-detail/match-achievements-notification';
import { getMapMetadata } from '@/config/maps';

interface Scoreboard {
  rank_value: number;
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
  game_mode: string | null;
}

interface Achievement {
  award_name: string;
}

interface Match {
  id: number;
  created_at: string;
  is_completed: boolean;
  match_details: MatchDetails | null;
  player_stats: Scoreboard[] | null;
  achievements?: Achievement[];
  processing_status: string | null;
  progress_percentage: number | null;
  current_step: string | null;
  error_message: string | null;
  match_type: string;
}

const MatchDetail = () => {
  const { id, tab, playerId } = useParams<{
    id: string;
    tab: string;
    playerId?: string;
  }>();
  const navigate = useNavigate();
  const [match, setMatch] = useState<Match | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Tab mapping between URL parameters and tab values
  const tabMapping = {
    'match-details': 'match-details',
    'player-stats': 'player-stats',
    'utility-analysis': 'utility',
    'grenade-explorer': 'grenades',
    'head-to-head': 'head-to-head',
    'aim-tracking': 'aim-tracking',
  };

  // Get current tab from route parameter
  const getCurrentTab = () => {
    if (tab && tab in tabMapping) {
      return tabMapping[tab as keyof typeof tabMapping];
    }
    return 'match-details';
  };

  // Handle tab change and update URL
  const handleTabChange = (value: string) => {
    const urlTab = Object.keys(tabMapping).find(
      key => tabMapping[key as keyof typeof tabMapping] === value
    );
    if (urlTab) {
      navigate(`/matches/${id}/${urlTab}`);
    }
  };

  useEffect(() => {
    const fetchMatch = async () => {
      if (!id) return;

      try {
        setLoading(true);
        const response = await api.get<Match>(`/matches/${id}/match-details`, {
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
          <h1 className="text-3xl font-bold text-red-500 mb-4">Error</h1>
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
          <h1 className="text-3xl font-bold text-gray-400 mb-4">
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
      <div className="container mx-auto px-4 py-4">
        {/* Match Details Card */}
        {match.match_details && (
          <Tabs
            value={getCurrentTab()}
            onValueChange={handleTabChange}
            className="w-full"
          >
            <Card className="mb-4 pb-0 pt-4 relative overflow-hidden">
              {(() => {
                const mapMetadata = getMapMetadata(match.match_details.map);
                return mapMetadata ? (
                  <div
                    className="absolute inset-0 opacity-20"
                    style={{
                      backgroundImage: `url(${mapMetadata.backgroundPath})`,
                      backgroundSize: `${mapMetadata.backgroundScale * 100}%`,
                      backgroundPosition: `${mapMetadata.backgroundPosition.x}% ${mapMetadata.backgroundPosition.y}%`,
                      backgroundRepeat: 'no-repeat',
                    }}
                  />
                ) : null;
              })()}
              <CardContent className="p-0 relative z-10">
                <div className="px-4 mb-4">
                  <div className="flex items-center flex">
                    <div className="flex items-center gap-4 mb-2">
                      <div className="flex items-center gap-2">
                        {(() => {
                          const mapMetadata = getMapMetadata(
                            match.match_details.map
                          );
                          return mapMetadata ? (
                            <img
                              src={mapMetadata.logoPath}
                              alt={`${mapMetadata.displayName} logo`}
                              className="w-12 h-12 object-contain"
                            />
                          ) : (
                            <div className="w-12 h-12 bg-gray-600 rounded"></div>
                          );
                        })()}
                        <h1 className="text-3xl font-bold tracking-tight text-white font-bold">
                          {(() => {
                            const mapMetadata = getMapMetadata(
                              match.match_details.map
                            );
                            return mapMetadata
                              ? mapMetadata.displayName
                              : match.match_details.map;
                          })()}
                        </h1>
                      </div>
                      <div className="flex items-center gap-2">
                        <IconTrophy className="w-6 h-6 text-gray-400" />
                        <h1 className="text-3xl font-bold tracking-tight text-green-500 font-bold">
                          {match.match_details.winning_team_score}
                        </h1>
                        <span className="text-3xl text-gray-400">-</span>
                        <h1 className="text-3xl font-bold tracking-tight text-red-500 font-bold">
                          {match.match_details.losing_team_score}
                        </h1>
                      </div>
                    </div>
                    <div className="flex items-center ml-auto mr-0 gap-4">
                      <div className="flex items-center gap-1">
                        <IconUsers className="w-4 h-4 text-gray-400" />
                        <span className="text-sm text-gray-400">
                          {match.match_details.match_type}
                        </span>
                      </div>
                      <div className="flex items-center gap-1">
                        <IconCalendar className="w-4 h-4 text-gray-400" />
                        <span className="text-sm text-gray-400">
                          {formatDate(match.match_details.created_at)}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <TabsList className="grid w-full grid-cols-6 bg-transparent p-0 rounded-b-lg px-3">
                  <TabsTrigger
                    value="match-details"
                    className="!bg-transparent rounded-none"
                  >
                    Match Details
                  </TabsTrigger>
                  <TabsTrigger
                    value="player-stats"
                    className="!bg-transparent rounded-none"
                  >
                    Player Stats
                  </TabsTrigger>
                  <TabsTrigger
                    value="aim-tracking"
                    className="!bg-transparent rounded-none"
                  >
                    Aim Tracking
                  </TabsTrigger>
                  <TabsTrigger
                    value="utility"
                    className="!bg-transparent rounded-none"
                  >
                    Utility Analysis
                  </TabsTrigger>
                  <TabsTrigger
                    value="grenades"
                    className="!bg-transparent rounded-none"
                  >
                    Grenade Explorer
                  </TabsTrigger>
                  <TabsTrigger
                    value="head-to-head"
                    className="!bg-transparent rounded-none"
                  >
                    Head to Head
                  </TabsTrigger>
                </TabsList>
              </CardContent>
            </Card>

            <TabsContent value="match-details" className="mt-0">
              {match.player_stats && match.player_stats.length > 0 ? (
                <>
                  <TopPlayers matchId={match.id} />
                  {match.achievements && match.achievements.length > 0 && (
                    <MatchAchievementsNotification
                      achievements={match.achievements}
                    />
                  )}
                  <Scoreboard
                    players={match.player_stats}
                    variant="expanded"
                    match={match}
                  />
                </>
              ) : (
                <p className="text-center text-gray-400">
                  No player statistics available for this match.
                </p>
              )}
            </TabsContent>

            <TabsContent value="player-stats" className="mt-0">
              <CardContent className="p-0">
                <MatchPlayerStats
                  matchId={match.id}
                  selectedPlayerId={playerId}
                />
              </CardContent>
            </TabsContent>

            <TabsContent value="aim-tracking" className="mt-0">
              <CardContent className="p-0">
                <AimTracking matchId={match.id} />
              </CardContent>
            </TabsContent>

            <TabsContent value="utility" className="mt-0">
              <CardContent className="p-0">
                <MatchUtilityAnalysis
                  matchId={match.id}
                  selectedPlayerId={playerId}
                />
              </CardContent>
            </TabsContent>

            <TabsContent value="grenades" className="mt-0 mb-6">
              <CardContent className="p-0">
                <MatchGrenadesView
                  matchId={match?.id?.toString() || ''}
                  hideMapAndMatchFilters={true}
                  showHeader={false}
                  showFavourites={true}
                  initialFilters={{
                    map: match?.match_details?.map || '',
                    grenadeType: 'fire_grenades',
                  }}
                />
              </CardContent>
            </TabsContent>

            <TabsContent value="head-to-head" className="mt-0">
              <CardContent className="p-0">
                <HeadToHead matchId={match.id} />
              </CardContent>
            </TabsContent>
          </Tabs>
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
                  {(() => {
                    const errorMsg = match.error_message;
                    const linkMatch = errorMsg.match(
                      /<a href='([^']+)'>([^<]+)<\/a>/
                    );
                    if (linkMatch) {
                      const [fullMatch, href, linkText] = linkMatch;
                      const parts = errorMsg.split(fullMatch);
                      return (
                        <>
                          {parts[0]}
                          <Link
                            to={href}
                            className="underline hover:text-red-300"
                          >
                            {linkText}
                          </Link>
                          {parts[1]}
                        </>
                      );
                    }
                    return errorMsg;
                  })()}
                </p>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </>
  );
};

export default MatchDetail;
