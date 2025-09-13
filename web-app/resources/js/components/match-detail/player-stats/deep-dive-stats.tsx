import { Card, CardContent, CardTitle } from '@/components/ui/card';

interface DeepDiveData {
  round_swing: number;
  impact: number;
}

interface OpeningTimingData {
  first_kills: number;
  first_deaths: number;
  avg_time_to_death: number | string;
  avg_time_to_contact: number | string;
}

interface DeepDiveStatsProps {
  deepDive?: DeepDiveData;
  openingTiming?: OpeningTimingData;
}

export function DeepDiveStats({ deepDive, openingTiming }: DeepDiveStatsProps) {
  return (
    <>
      <Card>
        <CardContent>
          <CardTitle className="mb-2">Advanced Metrics</CardTitle>
          {deepDive ? (
            <div>
              <div className="grid grid-cols-2 gap-4 relative">
                <div className="absolute left-1/2 top-0 bottom-0 w-px bg-gray-600 transform -translate-x-1/2"></div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-yellow-500">
                    {deepDive.round_swing}
                  </div>
                  <div className="text-sm text-gray-400">Round Swing</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-orange-500">
                    {deepDive.impact}
                  </div>
                  <div className="text-sm text-gray-400">Impact</div>
                </div>
              </div>
            </div>
          ) : (
            <div className="flex items-center justify-center h-32">
              <div className="text-center">
                <div className="text-gray-400 text-lg mb-2">🔍</div>
                <p className="text-gray-400">Advanced metrics coming soon</p>
                <p className="text-gray-500 text-sm mt-2">
                  Round swing and impact analysis will be displayed here
                </p>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
      <Card>
        <CardContent>
          <CardTitle className="mb-2">Opening & Timing</CardTitle>
          {openingTiming && (
            <div>
              <div className="grid grid-cols-2 gap-4 relative">
                <div className="absolute left-1/2 top-0 bottom-0 w-px bg-gray-600 transform -translate-x-1/2"></div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-green-500">
                    {openingTiming.first_kills}
                  </div>
                  <div className="text-sm text-gray-400">First Kills</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-red-500">
                    {openingTiming.first_deaths}
                  </div>
                  <div className="text-sm text-gray-400">First Deaths</div>
                </div>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}
