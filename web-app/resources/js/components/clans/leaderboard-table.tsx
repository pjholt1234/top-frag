import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

interface LeaderboardEntry {
  position: number;
  user_id: number;
  user_name: string;
  user_avatar: string | null;
  value: number;
}

interface LeaderboardTableProps {
  entries: LeaderboardEntry[];
  loading?: boolean;
  currentUserId?: number;
}

export function LeaderboardTable({
  entries,
  loading = false,
  currentUserId,
}: LeaderboardTableProps) {
  if (loading) {
    return (
      <div className="space-y-2">
        {[1, 2, 3, 4, 5].map(i => (
          <div key={i} className="h-12 bg-muted animate-pulse rounded"></div>
        ))}
      </div>
    );
  }

  if (entries.length === 0) {
    return (
      <div className="text-center text-muted-foreground text-sm py-8">
        No leaderboard data available
      </div>
    );
  }

  const getPositionBadge = (position: number) => {
    if (position === 1) {
      return <Badge className="bg-yellow-600">🥇</Badge>;
    } else if (position === 2) {
      return <Badge className="bg-gray-400">🥈</Badge>;
    } else if (position === 3) {
      return <Badge className="bg-amber-700">🥉</Badge>;
    }
    return <span className="text-muted-foreground">#{position}</span>;
  };

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-16">Rank</TableHead>
          <TableHead>Player</TableHead>
          <TableHead className="text-right">Value</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {entries.map(entry => (
          <TableRow
            key={entry.user_id}
            className={currentUserId === entry.user_id ? 'bg-muted/50' : ''}
          >
            <TableCell>
              <div className="flex items-center gap-2">
                {getPositionBadge(entry.position)}
              </div>
            </TableCell>
            <TableCell>
              <div className="flex items-center gap-3">
                <Avatar className="h-8 w-8">
                  <AvatarImage
                    src={entry.user_avatar || undefined}
                    alt={entry.user_name}
                  />
                  <AvatarFallback>
                    {entry.user_name.charAt(0).toUpperCase()}
                  </AvatarFallback>
                </Avatar>
                <span className="font-medium">{entry.user_name}</span>
                {currentUserId === entry.user_id && (
                  <Badge variant="outline" className="text-xs">
                    You
                  </Badge>
                )}
              </div>
            </TableCell>
            <TableCell className="text-right">
              <span className="font-semibold">{entry.value.toFixed(2)}</span>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}
