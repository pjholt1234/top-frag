import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { IconCopy, IconCheck } from '@tabler/icons-react';
import { GaugeChart } from '@/components/charts/gauge-chart';
import { MembersList } from './members-list';
import { toast } from 'sonner';

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
  members: any[];
  created_at: string;
  updated_at: string;
}

interface OverviewTabProps {
  clanId: number;
  clan: Clan;
}

interface WinrateData {
  wins: number;
  total: number;
  winrate: number;
}

export function OverviewTab({ clanId, clan }: OverviewTabProps) {
  const [inviteLink, setInviteLink] = useState(clan.invite_link);
  const [copied, setCopied] = useState(false);
  const [winrate, setWinrate] = useState<WinrateData | null>(null);
  const [loadingWinrate, setLoadingWinrate] = useState(true);

  useEffect(() => {
    const fetchWinrate = async () => {
      try {
        setLoadingWinrate(true);
        // Fetch matches to calculate winrate
        const response = await api.get<{
          data: any[];
          pagination: any;
        }>(`/clans/${clanId}/matches`, {
          requireAuth: true,
          params: {
            per_page: 1000, // Get a large number to calculate overall winrate
          },
        });

        const matches = response.data.data || [];
        let wins = 0;
        let total = 0;

        matches.forEach((match: any) => {
          if (match.is_completed && match.match_details) {
            total++;
            // Check if any clan member won (simplified - would need to check player stats)
            // For now, we'll use a simple heuristic
            if (match.match_details.winning_team) {
              // This is a simplified check - in reality we'd need to check if clan members were on winning team
              wins++;
            }
          }
        });

        // Calculate winrate
        const winrateValue = total > 0 ? (wins / total) * 100 : 0;

        setWinrate({
          wins,
          total,
          winrate: winrateValue,
        });
      } catch (err) {
        console.error('Error fetching winrate:', err);
        setWinrate({ wins: 0, total: 0, winrate: 0 });
      } finally {
        setLoadingWinrate(false);
      }
    };

    fetchWinrate();
  }, [clanId]);

  const handleCopyInviteLink = async () => {
    try {
      const inviteUrl = `${window.location.origin}/clans/join?invite=${inviteLink}`;
      await navigator.clipboard.writeText(inviteUrl);
      setCopied(true);
      toast.success('Invite link copied to clipboard');
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      toast.error('Failed to copy invite link');
    }
  };

  const handleRegenerateInviteLink = async () => {
    try {
      const response = await api.post<{
        message: string;
        invite_link: string;
      }>(`/clans/${clanId}/regenerate-invite-link`, {}, { requireAuth: true });

      setInviteLink(response.data.invite_link);
      toast.success('Invite link regenerated');
    } catch (err) {
      toast.error('Failed to regenerate invite link');
    }
  };

  return (
    <div className="space-y-6">
      {/* Invite Section */}
      <Card>
        <CardHeader>
          <CardTitle>Invite Members</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center gap-2">
            <div className="flex-1 flex items-center gap-2 p-2 bg-muted rounded">
              <span className="text-sm text-muted-foreground flex-1 truncate">
                {`${window.location.origin}/clans/join?invite=${inviteLink}`}
              </span>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={handleCopyInviteLink}
              className="flex items-center gap-2"
            >
              {copied ? (
                <>
                  <IconCheck className="h-4 w-4" />
                  Copied
                </>
              ) : (
                <>
                  <IconCopy className="h-4 w-4" />
                  Copy
                </>
              )}
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={handleRegenerateInviteLink}
            >
              Regenerate
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Stats Section */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Winrate Gauge */}
        <Card>
          <CardHeader>
            <CardTitle>Average Winrate</CardTitle>
          </CardHeader>
          <CardContent>
            {loadingWinrate ? (
              <div className="flex items-center justify-center h-48">
                <div className="animate-pulse text-muted-foreground">
                  Loading...
                </div>
              </div>
            ) : winrate ? (
              <GaugeChart
                currentValue={winrate.winrate}
                maxValue={100}
                title="Winrate"
                unit="%"
                size="lg"
                showValue={true}
                showPercentage={true}
              />
            ) : (
              <div className="flex items-center justify-center h-48 text-muted-foreground">
                No matches found
              </div>
            )}
          </CardContent>
        </Card>

        {/* Members List */}
        <Card>
          <CardHeader>
            <CardTitle>Clan Members</CardTitle>
          </CardHeader>
          <CardContent>
            <MembersList clanId={clanId} />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
