import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

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
}

export function MembersList({ clanId }: MembersListProps) {
  const [members, setMembers] = useState<ClanMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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
                      {(member.steam_persona_name || member.name)
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
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
