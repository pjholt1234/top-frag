import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { Card, CardContent } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-react';
import { LeaderboardTable } from './leaderboard-table';
import { AllTimeTopThree } from './all-time-top-three';
import { useAuth } from '@/hooks/use-auth';

interface LeaderboardEntry {
  position: number;
  user_id: number;
  user_name: string;
  user_avatar: string | null;
  value: number;
}

interface LeaderboardData {
  [key: string]: LeaderboardEntry[];
}

interface LeaderboardsTabProps {
  clanId: number;
}

const LEADERBOARD_TYPES = [
  { value: 'aim', label: 'Aim' },
  { value: 'impact', label: 'Impact' },
  { value: 'round_swing', label: 'Round Swing' },
  { value: 'fragger', label: 'Fragger' },
  { value: 'support', label: 'Support' },
  { value: 'opener', label: 'Opener' },
  { value: 'closer', label: 'Closer' },
];

export function LeaderboardsTab({ clanId }: LeaderboardsTabProps) {
  const { user } = useAuth();
  const [leaderboardType, setLeaderboardType] = useState('impact');
  const [period, setPeriod] = useState<'week' | 'month'>('week');
  const [customStartDate, setCustomStartDate] = useState('');
  const [customEndDate, setCustomEndDate] = useState('');
  const [useCustomDates, setUseCustomDates] = useState(false);
  const [leaderboardData, setLeaderboardData] = useState<LeaderboardEntry[]>(
    []
  );
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchLeaderboard = async () => {
      try {
        setLoading(true);
        setError(null);

        const params: Record<string, string> = {
          period: useCustomDates ? 'custom' : period,
          type: leaderboardType,
        };

        if (useCustomDates) {
          if (customStartDate) params.start_date = customStartDate;
          if (customEndDate) params.end_date = customEndDate;
        }

        const response = await api.get<{
          data: LeaderboardData;
          start_date: string;
          end_date: string;
        }>(`/clans/${clanId}/leaderboards`, {
          requireAuth: true,
          params,
        });

        const data = response.data.data;
        if (data && data[leaderboardType]) {
          setLeaderboardData(data[leaderboardType]);
        } else {
          setLeaderboardData([]);
        }
      } catch (err: unknown) {
        console.error('Error fetching leaderboard:', err);
        setError(
          err instanceof Error ? err.message : 'Failed to load leaderboard'
        );
        setLeaderboardData([]);
      } finally {
        setLoading(false);
      }
    };

    fetchLeaderboard();
  }, [
    clanId,
    leaderboardType,
    period,
    useCustomDates,
    customStartDate,
    customEndDate,
  ]);

  const handlePreviousWeek = () => {
    // Calculate previous week
    const end = new Date();
    end.setDate(end.getDate() - 7);
    const start = new Date(end);
    start.setDate(start.getDate() - 7);
    setCustomStartDate(start.toISOString().split('T')[0]);
    setCustomEndDate(end.toISOString().split('T')[0]);
    setUseCustomDates(true);
    setPeriod('week');
  };

  const handleNextWeek = () => {
    // Calculate next week
    const start = new Date();
    start.setDate(start.getDate() + 7);
    const end = new Date(start);
    end.setDate(end.getDate() + 7);
    setCustomStartDate(start.toISOString().split('T')[0]);
    setCustomEndDate(end.toISOString().split('T')[0]);
    setUseCustomDates(true);
    setPeriod('week');
  };

  const handlePreviousMonth = () => {
    // Calculate previous month
    const end = new Date();
    end.setMonth(end.getMonth() - 1);
    end.setDate(0); // Last day of previous month
    const start = new Date(end);
    start.setDate(1); // First day of previous month
    setCustomStartDate(start.toISOString().split('T')[0]);
    setCustomEndDate(end.toISOString().split('T')[0]);
    setUseCustomDates(true);
    setPeriod('month');
  };

  const handleNextMonth = () => {
    // Calculate next month
    const start = new Date();
    start.setMonth(start.getMonth() + 1);
    start.setDate(1);
    const end = new Date(start);
    end.setMonth(end.getMonth() + 1);
    end.setDate(0);
    setCustomStartDate(start.toISOString().split('T')[0]);
    setCustomEndDate(end.toISOString().split('T')[0]);
    setUseCustomDates(true);
    setPeriod('month');
  };

  const handleResetPeriod = () => {
    setUseCustomDates(false);
    setCustomStartDate('');
    setCustomEndDate('');
  };

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div className="lg:col-span-2 space-y-6">
        {/* Filters */}
        <Card>
          <CardContent className="pt-6 space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Leaderboard Type</Label>
                <Select
                  value={leaderboardType}
                  onValueChange={setLeaderboardType}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {LEADERBOARD_TYPES.map(type => (
                      <SelectItem key={type.value} value={type.value}>
                        {type.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label>Period</Label>
                <Select
                  value={useCustomDates ? 'custom' : period}
                  onValueChange={value => {
                    if (value === 'custom') {
                      setUseCustomDates(true);
                    } else {
                      setUseCustomDates(false);
                      setPeriod(value as 'week' | 'month');
                    }
                  }}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="week">This Week</SelectItem>
                    <SelectItem value="month">This Month</SelectItem>
                    <SelectItem value="custom">Custom Range</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            {useCustomDates && (
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Start Date</Label>
                  <Input
                    type="date"
                    value={customStartDate}
                    onChange={e => setCustomStartDate(e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label>End Date</Label>
                  <Input
                    type="date"
                    value={customEndDate}
                    onChange={e => setCustomEndDate(e.target.value)}
                  />
                </div>
              </div>
            )}

            {/* Period Navigation */}
            <div className="flex items-center gap-2">
              {period === 'week' ? (
                <>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handlePreviousWeek}
                  >
                    <IconChevronLeft className="h-4 w-4" />
                    Previous
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleResetPeriod}
                  >
                    This Week
                  </Button>
                  <Button variant="outline" size="sm" onClick={handleNextWeek}>
                    Next
                    <IconChevronRight className="h-4 w-4" />
                  </Button>
                </>
              ) : (
                <>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handlePreviousMonth}
                  >
                    <IconChevronLeft className="h-4 w-4" />
                    Previous
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleResetPeriod}
                  >
                    This Month
                  </Button>
                  <Button variant="outline" size="sm" onClick={handleNextMonth}>
                    Next
                    <IconChevronRight className="h-4 w-4" />
                  </Button>
                </>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Leaderboard Table */}
        <Card>
          <CardContent className="pt-6">
            {error ? (
              <div className="text-center text-destructive text-sm py-4">
                {error}
              </div>
            ) : (
              <LeaderboardTable
                entries={leaderboardData}
                loading={loading}
                currentUserId={user?.id}
              />
            )}
          </CardContent>
        </Card>
      </div>

      {/* All-Time Top 3 Sidebar */}
      <div>
        <AllTimeTopThree clanId={clanId} leaderboardType={leaderboardType} />
      </div>
    </div>
  );
}
