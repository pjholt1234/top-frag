import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

interface LeaderboardEntry {
  position: number;
  user_id: number;
  user_name: string;
  user_avatar: string | null;
  value: number;
}

interface AllTimeTopThreeProps {
  clanId: number;
  leaderboardType: string;
}

interface LeaderboardData {
  [key: string]: LeaderboardEntry[];
}

export function AllTimeTopThree({
  clanId,
  leaderboardType,
}: AllTimeTopThreeProps) {
  const [topThree, setTopThree] = useState<
    Array<{
      user_id: number;
      user_name: string;
      user_avatar: string | null;
      points: number;
    }>
  >([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchAllTimeData = async () => {
      try {
        setLoading(true);
        // Fetch all leaderboard periods to calculate all-time top 3
        // This is a simplified approach - in production, you might want a dedicated endpoint
        const periods = ['week', 'month'];
        const allEntries: LeaderboardEntry[] = [];

        for (const period of periods) {
          try {
            const response = await api.get<{
              data: LeaderboardData;
            }>(`/clans/${clanId}/leaderboards`, {
              requireAuth: true,
              params: {
                period,
                type: leaderboardType,
              },
            });

            const leaderboardData = response.data.data;
            if (leaderboardData && leaderboardData[leaderboardType]) {
              allEntries.push(...leaderboardData[leaderboardType]);
            }
          } catch (err) {
            console.error(`Error fetching ${period} leaderboard:`, err);
          }
        }

        // Calculate points: 3 for 1st, 2 for 2nd, 1 for 3rd
        const userPoints: Record<
          number,
          {
            user_id: number;
            user_name: string;
            user_avatar: string | null;
            points: number;
          }
        > = {};

        allEntries.forEach(entry => {
          if (!userPoints[entry.user_id]) {
            userPoints[entry.user_id] = {
              user_id: entry.user_id,
              user_name: entry.user_name,
              user_avatar: entry.user_avatar,
              points: 0,
            };
          }

          if (entry.position === 1) {
            userPoints[entry.user_id].points += 3;
          } else if (entry.position === 2) {
            userPoints[entry.user_id].points += 2;
          } else if (entry.position === 3) {
            userPoints[entry.user_id].points += 1;
          }
        });

        // Sort by points and get top 3
        const sorted = Object.values(userPoints)
          .sort((a, b) => b.points - a.points)
          .slice(0, 3);

        setTopThree(sorted);
      } catch (err) {
        console.error('Error fetching all-time top 3:', err);
        setTopThree([]);
      } finally {
        setLoading(false);
      }
    };

    if (leaderboardType) {
      fetchAllTimeData();
    }
  }, [clanId, leaderboardType]);

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>All-Time Top 3</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {[1, 2, 3].map(i => (
              <div
                key={i}
                className="h-12 bg-muted animate-pulse rounded"
              ></div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (topThree.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>All-Time Top 3</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center text-muted-foreground text-sm py-4">
            No all-time data available
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>All-Time Top 3</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-3">
          {topThree.map((user, index) => (
            <div
              key={user.user_id}
              className="flex items-center gap-3 p-2 rounded-lg bg-muted/50"
            >
              <div className="flex items-center justify-center w-8 h-8 rounded-full bg-muted">
                {index === 0 && <span className="text-lg">🥇</span>}
                {index === 1 && <span className="text-lg">🥈</span>}
                {index === 2 && <span className="text-lg">🥉</span>}
              </div>
              <Avatar className="h-8 w-8">
                <AvatarImage
                  src={user.user_avatar || undefined}
                  alt={user.user_name}
                />
                <AvatarFallback>
                  {user.user_name.charAt(0).toUpperCase()}
                </AvatarFallback>
              </Avatar>
              <div className="flex-1">
                <div className="font-medium text-sm">{user.user_name}</div>
              </div>
              <Badge variant="outline">{user.points} pts</Badge>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
