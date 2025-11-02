import { useState } from 'react';
import { DashboardFilters } from '@/components/dashboard/filters';
import { MapStatsTab } from '@/components/dashboard/map-stats-tab';

export interface PageFilters {
  date_from: string;
  date_to: string;
  game_type: string;
  map: string;
  past_match_count: number;
}

const MapStatsPage = () => {
  const [filters, setFilters] = useState<PageFilters>({
    date_from: '',
    date_to: '',
    game_type: 'all',
    map: 'all',
    past_match_count: 10,
  });

  return (
    <div className="space-y-6">
      <div className="mt-4">
        <h1 className="text-3xl font-bold tracking-tight">Map Statistics</h1>
        <p className="text-muted-foreground">
          View your performance across different maps
        </p>
      </div>

      <DashboardFilters
        filters={filters}
        onFiltersChange={setFilters}
        disableGameType={false}
        disableMap={false}
      />

      <MapStatsTab filters={filters} />
    </div>
  );
};

export default MapStatsPage;
