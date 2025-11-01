import { useState, useEffect, useMemo } from 'react';
import { api } from '@/lib/api';
import { DashboardFilters } from '@/pages/dashboard';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
  getWinRateColor,
  getAvgKillsColor,
  getAvgAssistsColor,
  getAvgDeathsColor,
  getKdColor,
  getAdrColor,
  getOpeningKillsColor,
  getOpeningDeathsColor,
  getComplexionScoreColor,
} from '@/lib/utils';

interface MapStats {
  map: string;
  matches: number;
  wins: number;
  win_rate: number;
  avg_kills: number;
  avg_assists: number;
  avg_deaths: number;
  avg_kd: number;
  avg_adr: number;
  avg_opening_kills: number;
  avg_opening_deaths: number;
  avg_complexion: {
    fragger: number;
    support: number;
    opener: number;
    closer: number;
  };
}

interface MapStatsData {
  maps: MapStats[];
  total_matches: number;
}

interface MapStatsTabProps {
  filters: DashboardFilters;
}

type SortField =
  | 'map'
  | 'matches'
  | 'wins'
  | 'win_rate'
  | 'avg_kills'
  | 'avg_assists'
  | 'avg_deaths'
  | 'avg_kd'
  | 'avg_adr'
  | 'avg_opening_kills'
  | 'avg_opening_deaths'
  | 'fragger'
  | 'support'
  | 'opener'
  | 'closer';
type SortDirection = 'asc' | 'desc' | null;

export const MapStatsTab = ({ filters }: MapStatsTabProps) => {
  const [data, setData] = useState<MapStatsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Sort states
  const [sortField, setSortField] = useState<SortField>('matches');
  const [sortDirection, setSortDirection] = useState<SortDirection>('desc');

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      setError(null);

      try {
        const response = await api.get('/dashboard/map-stats', {
          params: {
            date_from: filters.date_from || undefined,
            date_to: filters.date_to || undefined,
            game_type:
              filters.game_type === 'all' ? undefined : filters.game_type,
            map: filters.map === 'all' ? undefined : filters.map,
            past_match_count: filters.past_match_count,
          },
          requireAuth: true,
        });
        setData(response.data);
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to load map stats');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [filters]);

  // Helper functions (defined before useMemo)
  const formatMapName = (map: string) => {
    return map.replace('de_', '').replace('cs_', '').replace(/_/g, ' ');
  };

  // Sort map data (useMemo must be called before any early returns)
  const sortedMaps = useMemo(() => {
    if (!data?.maps) return [];

    let sorted = [...data.maps];

    // Sort the data
    if (sortField && sortDirection) {
      sorted = sorted.sort((a, b) => {
        let aValue: any;
        let bValue: any;

        if (sortField === 'map') {
          aValue = formatMapName(a.map);
          bValue = formatMapName(b.map);
        } else if (
          ['fragger', 'support', 'opener', 'closer'].includes(sortField)
        ) {
          aValue = a.avg_complexion[sortField as keyof typeof a.avg_complexion];
          bValue = b.avg_complexion[sortField as keyof typeof b.avg_complexion];
        } else {
          aValue = a[sortField];
          bValue = b[sortField];
        }

        if (typeof aValue === 'string') {
          return sortDirection === 'asc'
            ? aValue.localeCompare(bValue)
            : bValue.localeCompare(aValue);
        }

        return sortDirection === 'asc' ? aValue - bValue : bValue - aValue;
      });
    }

    return sorted;
  }, [data, sortField, sortDirection]);

  // Handle column sorting
  const handleSort = (field: SortField) => {
    if (sortField === field) {
      // Cycle through: desc -> asc -> null -> desc
      if (sortDirection === 'desc') {
        setSortDirection('asc');
      } else if (sortDirection === 'asc') {
        setSortDirection(null);
        setSortField('matches'); // Default back to matches
      } else {
        setSortDirection('desc');
      }
    } else {
      setSortField(field);
      setSortDirection('desc');
    }
  };

  // Get sort icon for column
  const getSortIcon = (field: SortField) => {
    if (sortField !== field) {
      return <ArrowUpDown className="ml-1 h-4 w-4 inline-block opacity-30" />;
    }
    if (sortDirection === 'desc') {
      return <ArrowDown className="ml-1 h-4 w-4 inline-block" />;
    }
    if (sortDirection === 'asc') {
      return <ArrowUp className="ml-1 h-4 w-4 inline-block" />;
    }
    return <ArrowUpDown className="ml-1 h-4 w-4 inline-block opacity-30" />;
  };

  // Early returns after all hooks
  if (loading) {
    return <MapStatsTabSkeleton />;
  }

  if (error) {
    return (
      <div className="text-center py-12">
        <p className="text-red-500">{error}</p>
      </div>
    );
  }

  if (!data || data.maps.length === 0) {
    return (
      <div className="text-center py-12">
        <p className="text-muted-foreground">No map data available</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead
                  className="cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('map')}
                >
                  Map {getSortIcon('map')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('matches')}
                >
                  Matches {getSortIcon('matches')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('wins')}
                >
                  Wins {getSortIcon('wins')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('win_rate')}
                >
                  Win Rate {getSortIcon('win_rate')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('avg_kills')}
                >
                  Avg Kills {getSortIcon('avg_kills')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('avg_assists')}
                >
                  Avg Assists {getSortIcon('avg_assists')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('avg_deaths')}
                >
                  Avg Deaths {getSortIcon('avg_deaths')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('avg_kd')}
                >
                  Avg K/D {getSortIcon('avg_kd')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('avg_adr')}
                >
                  Avg ADR {getSortIcon('avg_adr')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('avg_opening_kills')}
                >
                  Opening Kills {getSortIcon('avg_opening_kills')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('avg_opening_deaths')}
                >
                  Opening Deaths {getSortIcon('avg_opening_deaths')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('fragger')}
                >
                  Fragger {getSortIcon('fragger')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('support')}
                >
                  Support {getSortIcon('support')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('opener')}
                >
                  Entry {getSortIcon('opener')}
                </TableHead>
                <TableHead
                  className="text-center cursor-pointer hover:bg-muted/50 select-none"
                  onClick={() => handleSort('closer')}
                >
                  Closer {getSortIcon('closer')}
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {sortedMaps.map(mapStats => (
                <TableRow key={mapStats.map}>
                  <TableCell className="font-medium capitalize">
                    {formatMapName(mapStats.map)}
                  </TableCell>
                  <TableCell className="text-center">
                    {mapStats.matches}
                  </TableCell>
                  <TableCell className="text-center">{mapStats.wins}</TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getWinRateColor(mapStats.win_rate)
                    )}
                  >
                    {mapStats.win_rate}%
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getAvgKillsColor(mapStats.avg_kills)
                    )}
                  >
                    {mapStats.avg_kills}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getAvgAssistsColor(mapStats.avg_assists)
                    )}
                  >
                    {mapStats.avg_assists}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getAvgDeathsColor(mapStats.avg_deaths)
                    )}
                  >
                    {mapStats.avg_deaths}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getKdColor(mapStats.avg_kd)
                    )}
                  >
                    {mapStats.avg_kd}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getAdrColor(mapStats.avg_adr)
                    )}
                  >
                    {mapStats.avg_adr}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getOpeningKillsColor(mapStats.avg_opening_kills)
                    )}
                  >
                    {mapStats.avg_opening_kills}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getOpeningDeathsColor(mapStats.avg_opening_deaths)
                    )}
                  >
                    {mapStats.avg_opening_deaths}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getComplexionScoreColor(mapStats.avg_complexion.fragger)
                    )}
                  >
                    {mapStats.avg_complexion.fragger}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getComplexionScoreColor(mapStats.avg_complexion.support)
                    )}
                  >
                    {mapStats.avg_complexion.support}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getComplexionScoreColor(mapStats.avg_complexion.opener)
                    )}
                  >
                    {mapStats.avg_complexion.opener}
                  </TableCell>
                  <TableCell
                    className={cn(
                      'text-center font-semibold',
                      getComplexionScoreColor(mapStats.avg_complexion.closer)
                    )}
                  >
                    {mapStats.avg_complexion.closer}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
};

const MapStatsTabSkeleton = () => {
  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <Skeleton className="h-6 w-48 mb-2" />
          <Skeleton className="h-4 w-64" />
        </CardHeader>
        <CardContent>
          <Skeleton className="h-96 w-full" />
        </CardContent>
      </Card>
    </div>
  );
};
