import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/hooks/use-auth';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { IconArrowLeftRight } from '@tabler/icons-react';
import { toast } from 'sonner';

interface ClanMember {
  id: number;
  name: string;
  email: string;
  steam_id: string | null;
  steam_persona_name: string | null;
  steam_avatar: string | null;
  steam_avatar_medium: string | null;
  is_owner: boolean;
  faceit_rank?: {
    rank: string;
    rank_value: number;
  } | null;
  premier_rank?: {
    rank: string;
    rank_value: number;
  } | null;
}

interface MembersResponse {
  data: ClanMember[];
}

interface MembersListProps {
  clanId: number;
  clanOwnerId?: number;
}

export function MembersList({ clanId, clanOwnerId }: MembersListProps) {
  const { user } = useAuth();
  const [members, setMembers] = useState<ClanMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [transferDialogOpen, setTransferDialogOpen] = useState(false);
  const [selectedMember, setSelectedMember] = useState<ClanMember | null>(null);
  const [isTransferring, setIsTransferring] = useState(false);

  useEffect(() => {
    const fetchMembers = async () => {
      try {
        setLoading(true);
        setError(null);
        const response = await api.get<MembersResponse>(
          `/clans/${clanId}/members`,
          {
            requireAuth: true,
          }
        );
        setMembers(response.data.data || []);
      } catch (err: unknown) {
        console.error('Error fetching members:', err);
        setError(err instanceof Error ? err.message : 'Failed to load members');
      } finally {
        setLoading(false);
      }
    };

    fetchMembers();
  }, [clanId]);

  const handleTransferClick = (member: ClanMember) => {
    setSelectedMember(member);
    setTransferDialogOpen(true);
  };

  const handleTransferOwnership = async () => {
    if (!selectedMember) return;

    try {
      setIsTransferring(true);
      await api.post(
        `/clans/${clanId}/transfer-ownership/${selectedMember.id}`,
        {},
        { requireAuth: true }
      );
      toast.success('Ownership transferred successfully');
      setTransferDialogOpen(false);
      setSelectedMember(null);
      // Refresh members list
      const response = await api.get<MembersResponse>(
        `/clans/${clanId}/members`,
        {
          requireAuth: true,
        }
      );
      setMembers(response.data.data || []);
    } catch (err: any) {
      console.error('Error transferring ownership:', err);
      toast.error(
        err.message || err.data?.message || 'Failed to transfer ownership'
      );
    } finally {
      setIsTransferring(false);
    }
  };

  const isOwner = user && clanOwnerId && user.id === clanOwnerId;

  if (loading) {
    return (
      <div className="space-y-2">
        {[1, 2, 3].map(i => (
          <div key={i} className="h-12 bg-muted animate-pulse rounded"></div>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center text-destructive text-sm py-4">{error}</div>
    );
  }

  if (members.length === 0) {
    return (
      <div className="text-center text-muted-foreground text-sm py-4">
        No members found
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Member</TableHead>
            <TableHead>FaceIT</TableHead>
            <TableHead>Premier</TableHead>
            <TableHead>Role</TableHead>
            {isOwner && <TableHead>Actions</TableHead>}
          </TableRow>
        </TableHeader>
        <TableBody>
          {members.map(member => (
            <TableRow key={member.id}>
              <TableCell>
                <div className="flex items-center gap-3">
                  <Avatar className="h-8 w-8">
                    <AvatarImage
                      src={
                        member.steam_avatar_medium ||
                        member.steam_avatar ||
                        undefined
                      }
                      alt={member.steam_persona_name || member.name}
                    />
                    <AvatarFallback>
                      {(member.steam_persona_name || member.name || '?')
                        .charAt(0)
                        .toUpperCase()}
                    </AvatarFallback>
                  </Avatar>
                  <div>
                    <div className="font-medium">
                      {member.steam_persona_name || member.name}
                    </div>
                    {member.steam_persona_name &&
                      member.name !== member.steam_persona_name && (
                        <div className="text-xs text-muted-foreground">
                          {member.name}
                        </div>
                      )}
                  </div>
                </div>
              </TableCell>
              <TableCell>
                {member.faceit_rank ? (
                  <Badge variant="outline">
                    Level {member.faceit_rank.rank} (
                    {member.faceit_rank.rank_value})
                  </Badge>
                ) : (
                  <span className="text-muted-foreground text-sm">-</span>
                )}
              </TableCell>
              <TableCell>
                {member.premier_rank ? (
                  <Badge variant="outline">
                    {member.premier_rank.rank} ({member.premier_rank.rank_value}
                    )
                  </Badge>
                ) : (
                  <span className="text-muted-foreground text-sm">-</span>
                )}
              </TableCell>
              <TableCell>
                {member.is_owner ? (
                  <Badge variant="default">Owner</Badge>
                ) : (
                  <Badge variant="secondary">Member</Badge>
                )}
              </TableCell>
              {isOwner && (
                <TableCell>
                  {!member.is_owner && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleTransferClick(member)}
                      className="flex items-center gap-2"
                    >
                      <IconArrowLeftRight className="h-4 w-4" />
                      Transfer
                    </Button>
                  )}
                </TableCell>
              )}
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {/* Transfer Ownership Confirmation Dialog */}
      <Dialog open={transferDialogOpen} onOpenChange={setTransferDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Transfer Ownership</DialogTitle>
            <DialogDescription>
              Are you sure you want to transfer ownership of this clan to{' '}
              <strong>
                {selectedMember?.steam_persona_name || selectedMember?.name}
              </strong>
              ?
              <br />
              <br />
              Once ownership is transferred, you will become a regular member
              and will no longer have owner privileges. This action cannot be
              undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setTransferDialogOpen(false)}
              disabled={isTransferring}
            >
              Cancel
            </Button>
            <Button
              variant="default"
              onClick={handleTransferOwnership}
              disabled={isTransferring}
              className="flex items-center gap-2"
            >
              <IconArrowLeftRight className="h-4 w-4" />
              {isTransferring ? 'Transferring...' : 'Transfer Ownership'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
