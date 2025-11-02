import { useState } from 'react';
import { DashboardFilters } from '@/components/dashboard/filters';
import { RanksTab } from '@/components/dashboard/ranks-tab';

export interface PageFilters {
  date_from: string;
  date_to: string;
  game_type: string;
  map: string;
  past_match_count: number;
}

const RanksPage = () => {
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
        <h1 className="text-3xl font-bold tracking-tight">Rank History</h1>
        <p className="text-muted-foreground">
          Track your competitive, premier, and Faceit rank progression
        </p>
      </div>

      <DashboardFilters
        filters={filters}
        onFiltersChange={setFilters}
        disableGameType={true}
        disableMap={true}
      />

      <RanksTab filters={filters} />
    </div>
  );
};

export default RanksPage;
