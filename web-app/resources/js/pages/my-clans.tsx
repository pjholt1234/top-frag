import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CreateClanModal } from '@/components/clans/create-clan-modal';
import { IconUsers } from '@tabler/icons-react';

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

interface ClansResponse {
  data: Clan[];
}

const MyClans = () => {
  const navigate = useNavigate();
  const [clans, setClans] = useState<Clan[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchClans = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await api.get<ClansResponse>('/clans', {
        requireAuth: true,
      });
      setClans(response.data.data || []);
    } catch (err: unknown) {
      console.error('Error fetching clans:', err);
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to load clans';
      setError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchClans();
  }, []);

  const handleClanClick = (clanId: number) => {
    navigate(`/clans/${clanId}/overview`);
  };

  const handleCreateSuccess = (newClan: Clan) => {
    fetchClans();
    navigate(`/clans/${newClan.id}/overview`);
  };

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between mt-4">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">My Clans</h1>
            <p className="text-muted-foreground">
              Manage your clans and view clan statistics
            </p>
          </div>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3].map(i => (
            <Card key={i} className="animate-pulse">
              <CardContent className="p-6">
                <div className="h-6 bg-gray-700 rounded w-3/4 mb-2"></div>
                <div className="h-4 bg-gray-700 rounded w-1/2"></div>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between mt-4">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">My Clans</h1>
            <p className="text-muted-foreground">
              Manage your clans and view clan statistics
            </p>
          </div>
        </div>
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <p className="text-destructive mb-2">Error loading clans</p>
            <p className="text-muted-foreground text-sm">{error}</p>
            <Button onClick={fetchClans} className="mt-4">
              Try Again
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between mt-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">My Clans</h1>
          <p className="text-muted-foreground">
            Manage your clans and view clan statistics
          </p>
        </div>
        <CreateClanModal onSuccess={handleCreateSuccess} />
      </div>

      {clans.length === 0 ? (
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <IconUsers className="h-16 w-16 text-muted-foreground mx-auto mb-4" />
            <p className="text-muted-foreground mb-2">No clans found</p>
            <p className="text-sm text-muted-foreground mb-4">
              Create a clan to get started
            </p>
            <CreateClanModal onSuccess={handleCreateSuccess} />
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {clans.map(clan => (
            <Card
              key={clan.id}
              className="cursor-pointer hover:bg-muted/50 transition-colors"
              onClick={() => handleClanClick(clan.id)}
            >
              <CardContent className="p-6">
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h3 className="text-xl font-semibold mb-1">
                      {clan.tag ? `[${clan.tag}] ` : ''}
                      {clan.name}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                      {clan.members.length} member
                      {clan.members.length !== 1 ? 's' : ''}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <IconUsers className="h-4 w-4" />
                  <span>Owner: {clan.owner.name}</span>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
};

export default MyClans;
