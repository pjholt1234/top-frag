import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';
import { PremierRank } from '@/components/shared/premier-rank';
import { CompetitiveRank } from '@/components/shared/competitive-rank';

interface RankHistory {
  rank: string;
  rank_value: number;
  date: string;
  timestamp: number;
}

interface CompetitiveMapRank {
  map: string;
  current_rank: string;
  current_rank_value: number;
  trend: 'up' | 'down' | 'neutral';
  history: RankHistory[];
}

interface CompetitiveRankData {
  rank_type: 'competitive';
  maps: CompetitiveMapRank[];
}

interface GlobalRankData {
  rank_type: 'premier' | 'faceit';
  current_rank: string | null;
  current_rank_value: number | null;
  trend: 'up' | 'down' | 'neutral';
  history: RankHistory[];
}

interface RankStatsData {
  competitive: CompetitiveRankData | [];
  premier: GlobalRankData | [];
  faceit: GlobalRankData | [];
}

interface RanksTabProps {
  filters: DashboardFilters;
}

const formatMapName = (map: string) => {
  const name = map.replace('de_', '').replace('cs_', '').replace(/_/g, ' ');
  return name.charAt(0).toUpperCase() + name.slice(1);
};

// Format date for display (e.g., "Jan 15" or "Jan 15, 2024" if different year)
const formatDate = (dateString: string): string => {
  const date = new Date(dateString);
  const now = new Date();
  const options: Intl.DateTimeFormatOptions = {
    month: 'short',
    day: 'numeric',
  };

  // Add year if it's different from current year
  if (date.getFullYear() !== now.getFullYear()) {
    options.year = 'numeric';
  }

  return date.toLocaleDateString('en-US', options);
};

// Format date for axis (shorter format)
const formatAxisDate = (dateString: string): string => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Custom tooltip for competitive ranks
const CompetitiveRankTooltip = ({ active, payload }: any) => {
  if (active && payload && payload.length) {
    const data = payload[0].payload;
    return (
      <div className="bg-gray-800 border border-gray-600 rounded-lg p-3">
        <p className="text-white font-medium mb-2">{formatDate(data.date)}</p>
        <div className="flex items-center gap-2">
          <img
            src={`/images/ranks/valve-competitive-ranks/${data.rank_value}.png`}
            alt={data.rank}
            width={65}
            height={25}
            className="object-contain"
          />
          <div>
            <p className="text-sm text-gray-300">{data.rank}</p>
            <p className="text-xs text-gray-400">Value: {data.rank_value}</p>
          </div>
        </div>
      </div>
    );
  }
  return null;
};

// Custom tooltip for premier ranks
const PremierRankTooltip = ({ active, payload }: any) => {
  if (active && payload && payload.length) {
    const data = payload[0].payload;
    return (
      <div className="bg-gray-800 border border-gray-600 rounded-lg p-3">
        <p className="text-white font-medium mb-2">{formatDate(data.date)}</p>
        <div className="flex items-center gap-2">
          <PremierRank rank={data.rank_value} size="sm" />
          <div>
            <p className="text-sm text-gray-300">
              Rating: {data.rank_value.toLocaleString()}
            </p>
          </div>
        </div>
      </div>
    );
  }
  return null;
};

// Custom tooltip for faceit ranks
const FaceitRankTooltip = ({ active, payload }: any) => {
  if (active && payload && payload.length) {
    const data = payload[0].payload;
    return (
      <div className="bg-gray-800 border border-gray-600 rounded-lg p-3">
        <p className="text-white font-medium mb-2">{formatDate(data.date)}</p>
        <div>
          <p className="text-sm text-gray-300">
            ELO: {data.rank_value.toLocaleString()}
          </p>
          <p className="text-xs text-gray-400">Rating</p>
        </div>
      </div>
    );
  }
  return null;
};

// Get rank name from value
const getRankName = (rankValue: number): string => {
  const rankNames: { [key: number]: string } = {
    0: 'Unranked',
    1: 'Silver I',
    2: 'Silver II',
    3: 'Silver III',
    4: 'Silver IV',
    5: 'Silver Elite',
    6: 'Silver Elite Master',
    7: 'Gold Nova I',
    8: 'Gold Nova II',
    9: 'Gold Nova III',
    10: 'Gold Nova Master',
    11: 'Master Guardian I',
    12: 'Master Guardian II',
    13: 'Master Guardian Elite',
    14: 'Distinguished Master Guardian',
    15: 'Legendary Eagle',
    16: 'Legendary Eagle Master',
    17: 'Supreme Master First Class',
    18: 'Global Elite',
  };
  return rankNames[rankValue] || `Rank ${rankValue}`;
};

export const RanksTab = ({ filters }: RanksTabProps) => {
  const [data, setData] = useState<RankStatsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedCompetitiveMap, setSelectedCompetitiveMap] =
    useState<string>('');

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      setError(null);

      try {
        const response = await api.get('/ranks', {
          params: {
            date_from: filters.date_from || undefined,
            date_to: filters.date_to || undefined,
            past_match_count: filters.past_match_count,
          },
          requireAuth: true,
        });
        setData(response.data);
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to load rank stats');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [filters]);

  // Set default competitive map when data loads
  useEffect(() => {
    if (
      data &&
      !Array.isArray(data.competitive) &&
      (data.competitive as CompetitiveRankData).maps?.length > 0
    ) {
      const maps = (data.competitive as CompetitiveRankData).maps;
      if (
        !selectedCompetitiveMap ||
        !maps.find(m => m.map === selectedCompetitiveMap)
      ) {
        setSelectedCompetitiveMap(maps[0].map);
      }
    }
  }, [data, selectedCompetitiveMap]);

  if (loading) {
    return <RanksTabSkeleton />;
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <p className="text-red-500">{error}</p>
      </div>
    );
  }

  if (!data) {
    return null;
  }

  const hasCompetitive = Array.isArray(data.competitive)
    ? data.competitive.length > 0
    : (data.competitive as CompetitiveRankData).maps?.length > 0;
  const hasPremier =
    !Array.isArray(data.premier) &&
    (data.premier as GlobalRankData).current_rank !== null;
  const hasFaceit =
    !Array.isArray(data.faceit) &&
    (data.faceit as GlobalRankData).current_rank !== null;

  const hasAnyRank = hasCompetitive || hasPremier || hasFaceit;

  // Count how many rank types are present to determine grid columns
  const rankTypeCount = [hasCompetitive, hasPremier, hasFaceit].filter(
    Boolean
  ).length;
  const gridColsClass =
    rankTypeCount === 3
      ? 'lg:grid-cols-3'
      : rankTypeCount === 2
        ? 'lg:grid-cols-2'
        : 'lg:grid-cols-1';

  // Get available competitive maps for the filter
  const competitiveMaps = hasCompetitive
    ? (data.competitive as CompetitiveRankData).maps.map(m => m.map)
    : [];

  // Get selected competitive map data
  const selectedMapData =
    hasCompetitive && selectedCompetitiveMap
      ? (data.competitive as CompetitiveRankData).maps.find(
          m => m.map === selectedCompetitiveMap
        )
      : null;

  if (!hasAnyRank) {
    return (
      <div className="text-center py-12">
        <p className="text-muted-foreground">
          No rank data available for the selected period
        </p>
      </div>
    );
  }

  // Prepare chart data with rank names and formatted dates
  const prepareCompetitiveData = (history: RankHistory[]) => {
    return history.map(h => ({
      ...h,
      rankName: getRankName(h.rank_value),
      displayDate: formatAxisDate(h.date),
    }));
  };

  const prepareGlobalData = (history: RankHistory[]) => {
    return history.map(h => ({
      ...h,
      rankName: h.rank,
      displayDate: formatAxisDate(h.date),
    }));
  };

  return (
    <div className={`grid grid-cols-1 ${gridColsClass} gap-6`}>
      {/* Competitive Ranks */}
      {hasCompetitive && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between gap-3">
              <CardTitle className="text-base whitespace-nowrap">
                Competitive Ranks
              </CardTitle>
              <div className="flex items-center gap-2">
                <Select
                  value={selectedCompetitiveMap}
                  onValueChange={setSelectedCompetitiveMap}
                >
                  <SelectTrigger
                    id="competitive-map"
                    className="text-xs h-7 w-[140px]"
                  >
                    <SelectValue placeholder="Select map" />
                  </SelectTrigger>
                  <SelectContent>
                    {competitiveMaps.map(map => (
                      <SelectItem key={map} value={map}>
                        {formatMapName(map)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {selectedMapData && (
                  <CompetitiveRank
                    rank={selectedMapData.current_rank_value}
                    size="sm"
                  />
                )}
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {!selectedMapData ? (
              <div className="text-center py-12">
                <p className="text-muted-foreground text-sm">
                  No rank data for selected map
                </p>
              </div>
            ) : (
              <div className="h-[350px]">
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart
                    data={prepareCompetitiveData(selectedMapData.history)}
                    margin={{ top: 5, right: 10, left: 0, bottom: 5 }}
                  >
                    <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                    <XAxis
                      dataKey="displayDate"
                      stroke="#9CA3AF"
                      fontSize={10}
                    />
                    <YAxis
                      stroke="#9CA3AF"
                      fontSize={10}
                      domain={[0, 18]}
                      ticks={[0, 6, 12, 18]}
                      tickFormatter={value => getRankName(value).split(' ')[0]}
                    />
                    <Tooltip content={<CompetitiveRankTooltip />} />
                    <Line
                      type="monotone"
                      dataKey="rank_value"
                      stroke="#f97316"
                      strokeWidth={2}
                      dot={{ fill: '#f97316', r: 3 }}
                      activeDot={{ r: 5 }}
                    />
                  </LineChart>
                </ResponsiveContainer>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Premier Rank */}
      {hasPremier && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base">Premier Rating</CardTitle>
              <PremierRank
                rank={(data.premier as GlobalRankData).current_rank_value || 0}
                size="sm"
              />
            </div>
          </CardHeader>
          <CardContent>
            <div className="h-[350px]">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart
                  data={prepareGlobalData(
                    (data.premier as GlobalRankData).history
                  )}
                  margin={{ top: 5, right: 10, left: 0, bottom: 5 }}
                >
                  <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                  <XAxis dataKey="displayDate" stroke="#9CA3AF" fontSize={10} />
                  <YAxis
                    stroke="#9CA3AF"
                    fontSize={10}
                    tickFormatter={value => value.toLocaleString()}
                  />
                  <Tooltip content={<PremierRankTooltip />} />
                  <Line
                    type="monotone"
                    dataKey="rank_value"
                    stroke="#3b82f6"
                    strokeWidth={2}
                    dot={{ fill: '#3b82f6', r: 3 }}
                    activeDot={{ r: 5 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Faceit Rank */}
      {hasFaceit && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base">Faceit ELO</CardTitle>
              <div className="bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded px-3 py-1">
                <span className="text-sm font-bold">
                  {(
                    (data.faceit as GlobalRankData).current_rank_value || 0
                  ).toLocaleString()}
                </span>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <div className="h-[350px]">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart
                  data={prepareGlobalData(
                    (data.faceit as GlobalRankData).history
                  )}
                  margin={{ top: 5, right: 10, left: 0, bottom: 5 }}
                >
                  <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                  <XAxis dataKey="displayDate" stroke="#9CA3AF" fontSize={10} />
                  <YAxis
                    stroke="#9CA3AF"
                    fontSize={10}
                    tickFormatter={value => value.toLocaleString()}
                  />
                  <Tooltip content={<FaceitRankTooltip />} />
                  <Line
                    type="monotone"
                    dataKey="rank_value"
                    stroke="#f97316"
                    strokeWidth={2}
                    dot={{ fill: '#f97316', r: 3 }}
                    activeDot={{ r: 5 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
};

const RanksTabSkeleton = () => {
  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <Skeleton className="h-6 w-48" />
        </CardHeader>
        <CardContent>
          <Skeleton className="h-[300px] w-full" />
        </CardContent>
      </Card>
    </div>
  );
};
