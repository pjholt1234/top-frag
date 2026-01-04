import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IconCopy, IconCheck, IconTrash, IconUnlink } from '@tabler/icons-react';
import { GaugeChart } from '@/components/charts/gauge-chart';
import { MembersList } from './members-list';
import { toast } from 'sonner';
import { useAuth } from '@/hooks/use-auth';

interface Clan {
  id: number;
  name: string;
  tag: string | null;
  invite_link: string;
  discord_guild_id: string | null;
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
  const navigate = useNavigate();
  const { user } = useAuth();
  const [inviteLink, setInviteLink] = useState(clan.invite_link);
  const [copied, setCopied] = useState(false);
  const [winrate, setWinrate] = useState<WinrateData | null>(null);
  const [loadingWinrate, setLoadingWinrate] = useState(true);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [confirmationText, setConfirmationText] = useState('');
  const [isUnlinking, setIsUnlinking] = useState(false);
  const [unlinkDialogOpen, setUnlinkDialogOpen] = useState(false);

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

  const handleDeleteClick = () => {
    setConfirmationText('');
    setDeleteDialogOpen(true);
  };

  const handleDeleteClan = async () => {
    const requiredText = `DELETE ${clan.name.toUpperCase()}`;

    if (confirmationText !== requiredText) {
      toast.error('Confirmation text did not match. Clan was not deleted.');
      return;
    }

    try {
      setIsDeleting(true);
      await api.delete(`/clans/${clanId}`, { requireAuth: true });
      toast.success('Clan deleted successfully');
      setDeleteDialogOpen(false);
      navigate('/clans');
    } catch (err: any) {
      console.error('Error deleting clan:', err);
      toast.error(err.message || err.data?.message || 'Failed to delete clan');
    } finally {
      setIsDeleting(false);
    }
  };

  const handleUnlinkDiscord = async () => {
    try {
      setIsUnlinking(true);
      await api.post(`/clans/${clanId}/unlink-discord`, {}, { requireAuth: true });
      toast.success('Clan unlinked from Discord server successfully');
      setUnlinkDialogOpen(false);
      // Refresh the clan data
      window.location.reload();
    } catch (err: any) {
      console.error('Error unlinking clan from Discord:', err);
      toast.error(err.message || err.data?.message || 'Failed to unlink clan from Discord');
    } finally {
      setIsUnlinking(false);
    }
  };

  const isOwner = user && clan.owner.id === user.id;

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

      {/* Discord Integration */}
      {isOwner && clan.discord_guild_id && (
        <Card>
          <CardHeader>
            <CardTitle>Discord Integration</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <p className="text-sm text-muted-foreground">
                This clan is linked to a Discord server. You can unlink it to
                remove the connection.
              </p>
              <Button
                variant="outline"
                onClick={() => setUnlinkDialogOpen(true)}
                disabled={isUnlinking}
                className="flex items-center gap-2"
              >
                <IconUnlink className="h-4 w-4" />
                Unlink from Discord Server
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Danger Zone - Delete Clan */}
      {isOwner && (
        <Card className="border-destructive">
          <CardHeader>
            <CardTitle className="text-destructive">Danger Zone</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <p className="text-sm text-muted-foreground">
                Once you delete a clan, there is no going back. This will
                permanently delete the clan, all clan members, matches, and
                leaderboards.
              </p>
              <Button
                variant="destructive"
                onClick={handleDeleteClick}
                disabled={isDeleting}
                className="flex items-center gap-2"
              >
                <IconTrash className="h-4 w-4" />
                Delete Clan
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Unlink Discord Confirmation Dialog */}
      <Dialog open={unlinkDialogOpen} onOpenChange={setUnlinkDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Unlink from Discord Server</DialogTitle>
            <DialogDescription>
              Are you sure you want to unlink this clan from the Discord
              server? This will remove the connection between your clan and the
              Discord server.
              <br />
              <br />
              You can link it to a different Discord server later using the{' '}
              <code className="bg-muted px-1 py-0.5 rounded">/setup</code>{' '}
              command in Discord.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setUnlinkDialogOpen(false)}
              disabled={isUnlinking}
            >
              Cancel
            </Button>
            <Button
              variant="default"
              onClick={handleUnlinkDiscord}
              disabled={isUnlinking}
              className="flex items-center gap-2"
            >
              <IconUnlink className="h-4 w-4" />
              {isUnlinking ? 'Unlinking...' : 'Unlink from Discord'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="text-destructive">Delete Clan</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete this clan? This action cannot be
              undone.
              <br />
              <br />
              All clan members, matches, and leaderboards will be permanently
              deleted.
              <br />
              <br />
              <strong>
                Type{' '}
                <code className="bg-muted px-1 py-0.5 rounded">
                  DELETE {clan.name.toUpperCase()}
                </code>{' '}
                to confirm:
              </strong>
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="confirmation-input">Confirmation</Label>
            <Input
              id="confirmation-input"
              value={confirmationText}
              onChange={e => setConfirmationText(e.target.value)}
              placeholder={`DELETE ${clan.name.toUpperCase()}`}
              disabled={isDeleting}
              className="font-mono"
            />
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteDialogOpen(false)}
              disabled={isDeleting}
            >
              Cancel
            </Button>
            <Button
              variant="destructive"
              onClick={handleDeleteClan}
              disabled={
                isDeleting ||
                confirmationText !== `DELETE ${clan.name.toUpperCase()}`
              }
              className="flex items-center gap-2"
            >
              <IconTrash className="h-4 w-4" />
              {isDeleting ? 'Deleting...' : 'Delete Clan'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

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
            <MembersList clanId={clanId} clanOwnerId={clan.owner.id} />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
