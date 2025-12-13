import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { IconArrowLeft } from '@tabler/icons-react';
import { OverviewTab } from '@/components/clans/overview-tab';
import { MatchesTab } from '@/components/clans/matches-tab';
import { LeaderboardsTab } from '@/components/clans/leaderboards-tab';

interface ClanMember {
  id: number;
  name: string;
  email: string;
  steam_id: string | null;
  steam_persona_name: string | null;
  steam_avatar: string | null;
  is_owner: boolean;
}

interface Clan {
  id: number;
  name: string;
  tag: string | null;
  invite_link: string;
  owner: {
    id: number;
    name: string;
    email: string;
  };
  members: ClanMember[];
  created_at: string;
  updated_at: string;
}

const ClanDetail = () => {
  const { id, tab } = useParams<{
    id: string;
    tab?: string;
  }>();
  const navigate = useNavigate();
  const [clan, setClan] = useState<Clan | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Tab mapping between URL parameters and tab values
  const tabMapping = {
    overview: 'overview',
    matches: 'matches',
    leaderboards: 'leaderboards',
  };

  // Get current tab from route parameter
  const getCurrentTab = () => {
    if (tab && tab in tabMapping) {
      return tabMapping[tab as keyof typeof tabMapping];
    }
    return 'overview';
  };

  // Handle tab change and update URL
  const handleTabChange = (value: string) => {
    const urlTab = Object.keys(tabMapping).find(
      key => tabMapping[key as keyof typeof tabMapping] === value
    );
    if (urlTab) {
      navigate(`/clans/${id}/${urlTab}`);
    }
  };

  useEffect(() => {
    const fetchClan = async () => {
      if (!id) return;

      try {
        setLoading(true);
        setError(null);
        const response = await api.get<{ data: Clan }>(`/clans/${id}`, {
          requireAuth: true,
        });
        setClan(response.data.data);
      } catch (err: unknown) {
        console.error('Error fetching clan:', err);
        setError(err instanceof Error ? err.message : 'Failed to load clan');
      } finally {
        setLoading(false);
      }
    };

    fetchClan();
  }, [id]);

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
          <Button onClick={() => navigate('/clans')}>
            <IconArrowLeft className="w-4 h-4 mr-2" />
            Back to Clans
          </Button>
        </div>
      </div>
    );
  }

  if (!clan) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <h1 className="text-3xl font-bold text-gray-400 mb-4">
            Clan Not Found
          </h1>
          <Button onClick={() => navigate('/clans')}>
            <IconArrowLeft className="w-4 h-4 mr-2" />
            Back to Clans
          </Button>
        </div>
      </div>
    );
  }

  const displayTitle = clan.tag ? `[${clan.tag}] - ${clan.name}` : clan.name;

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
        <Tabs
          value={getCurrentTab()}
          onValueChange={handleTabChange}
          className="w-full"
        >
          <Card className="mb-4 pb-0 pt-4">
            <CardContent className="p-0">
              <div className="px-4 mb-4">
                <div className="flex items-center gap-4 mb-2">
                  <h1 className="text-3xl font-bold tracking-tight text-white">
                    {displayTitle}
                  </h1>
                </div>
              </div>

              <TabsList className="grid w-full grid-cols-3 bg-transparent p-0 rounded-b-lg px-3">
                <TabsTrigger
                  value="overview"
                  className="!bg-transparent rounded-none"
                >
                  Overview
                </TabsTrigger>
                <TabsTrigger
                  value="matches"
                  className="!bg-transparent rounded-none"
                >
                  Matches
                </TabsTrigger>
                <TabsTrigger
                  value="leaderboards"
                  className="!bg-transparent rounded-none"
                >
                  Leaderboards
                </TabsTrigger>
              </TabsList>
            </CardContent>
          </Card>

          <TabsContent value="overview" className="mt-0">
            {id && <OverviewTab clanId={parseInt(id)} clan={clan} />}
          </TabsContent>

          <TabsContent value="matches" className="mt-0">
            {id && <MatchesTab clanId={parseInt(id)} />}
          </TabsContent>

          <TabsContent value="leaderboards" className="mt-0">
            {id && <LeaderboardsTab clanId={parseInt(id)} />}
          </TabsContent>
        </Tabs>
      </div>
    </>
  );
};

export default ClanDetail;
