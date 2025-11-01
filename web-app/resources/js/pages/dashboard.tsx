import { useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DashboardFilters } from '@/components/dashboard/filters';
import { SummaryTab } from '@/components/dashboard/summary-tab';
import { PlayerStatsTab } from '@/components/dashboard/player-stats-tab';
import { AimTab } from '@/components/dashboard/aim-tab';
import { UtilityTab } from '@/components/dashboard/utility-tab';
import { MapStatsTab } from '@/components/dashboard/map-stats-tab';
import { RanksTab } from '@/components/dashboard/ranks-tab';
import { Card, CardContent } from '@/components/ui/card';

export interface DashboardFilters {
  date_from: string;
  date_to: string;
  game_type: string;
  map: string;
  past_match_count: number;
}

const Dashboard = () => {
  const [filters, setFilters] = useState<DashboardFilters>({
    date_from: '',
    date_to: '',
    game_type: 'all',
    map: 'all',
    past_match_count: 10,
  });

  const [activeTab, setActiveTab] = useState('summary');

  return (
    <>
      <style>
        {`
          [data-state="active"] {
            border: none !important;
            border-bottom: 2px solid #f97316 !important;
          }

          [data-state="inactive"] {
            border: none !important;
            border-bottom: 2px solid transparent !important;
          }

          .border-0 {
            border: none !important;
            border-bottom: none !important;
          }

          [data-slot="tabs-content"] {
            border: none !important;
            border-bottom: none !important;
          }

          [role="tab"] {
            border: none !important;
            border-bottom: 2px solid transparent !important;
            transition: border-bottom-color 0.2s ease-in-out !important;
          }

          [data-state="active"][role="tab"] {
            border-bottom: 2px solid #f97316 !important;
          }
        `}
      </style>
      <div className="space-y-6">
        <div className="flex items-center justify-between mt-4">
          <div>
            <h1 className="text-3xl font-bold mb-2">Dashboard</h1>
            <p className="text-muted-foreground">
              View your performance statistics and trends
            </p>
          </div>
        </div>

        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <Card className="p-0">
            <CardContent className="pt-6">
              <DashboardFilters
                filters={filters}
                onFiltersChange={setFilters}
                disableGameType={activeTab === 'ranks'}
                disableMap={activeTab === 'ranks'}
              />

              <div className="mt-6">
                <TabsList className="grid w-full grid-cols-6 bg-transparent p-0 rounded-b-lg">
                  <TabsTrigger
                    value="summary"
                    className="!bg-transparent rounded-none"
                  >
                    Summary
                  </TabsTrigger>
                  <TabsTrigger
                    value="player-stats"
                    className="!bg-transparent rounded-none"
                  >
                    Player Stats
                  </TabsTrigger>
                  <TabsTrigger
                    value="aim"
                    className="!bg-transparent rounded-none"
                  >
                    Aim
                  </TabsTrigger>
                  <TabsTrigger
                    value="utility"
                    className="!bg-transparent rounded-none"
                  >
                    Utility
                  </TabsTrigger>
                  <TabsTrigger
                    value="map-stats"
                    className="!bg-transparent rounded-none"
                  >
                    Map Stats
                  </TabsTrigger>
                  <TabsTrigger
                    value="ranks"
                    className="!bg-transparent rounded-none"
                  >
                    Ranks
                  </TabsTrigger>
                </TabsList>
              </div>
            </CardContent>
          </Card>

          <div className="mt-6">
            <TabsContent value="summary" className="mt-0">
              <SummaryTab filters={filters} />
            </TabsContent>

            <TabsContent value="player-stats" className="mt-0">
              <PlayerStatsTab filters={filters} />
            </TabsContent>

            <TabsContent value="aim" className="mt-0">
              <AimTab filters={filters} />
            </TabsContent>

            <TabsContent value="utility" className="mt-0">
              <UtilityTab filters={filters} />
            </TabsContent>

            <TabsContent value="map-stats" className="mt-0">
              <MapStatsTab filters={filters} />
            </TabsContent>

            <TabsContent value="ranks" className="mt-0">
              <RanksTab filters={filters} />
            </TabsContent>
          </div>
        </Tabs>
      </div>
    </>
  );
};

export default Dashboard;
