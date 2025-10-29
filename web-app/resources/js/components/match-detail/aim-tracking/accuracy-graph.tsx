import { useState, useEffect, useCallback } from 'react';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { api } from '@/lib/api';
import { QUALITY_COLORS } from '@/constants/colors';
import { Info } from 'lucide-react';

interface HitData {
  head_hits_total: number;
  upper_chest_hits_total: number;
  chest_hits_total: number;
  legs_hits_total: number;
}

interface WeaponStats {
  shots_fired: number;
  shots_hit: number;
  accuracy_all_shots: number;
  headshot_accuracy: number;
  spraying_accuracy: number;
}

interface Weapon {
  value: string;
  label: string;
}

interface AccuracyGraphProps {
  matchId: number;
  playerSteamId: string;
  weapons: Weapon[];
  loadingWeapons: boolean;
}

export const AccuracyGraph = ({
  matchId,
  playerSteamId,
  weapons,
  loadingWeapons,
}: AccuracyGraphProps) => {
  const [selectedWeapon, setSelectedWeapon] = useState('all');
  const [data, setData] = useState<HitData | null>(null);
  const [weaponStats, setWeaponStats] = useState<WeaponStats | null>(null);
  const [loading, setLoading] = useState(false);

  const fetchData = useCallback(async () => {
    if (!playerSteamId) return;

    try {
      setLoading(true);
      const params = new URLSearchParams();
      params.append('player_steam_id', playerSteamId);

      if (selectedWeapon && selectedWeapon !== 'all') {
        params.append('weapon_name', selectedWeapon);
      }

      const endpoint =
        selectedWeapon && selectedWeapon !== 'all'
          ? `/matches/${matchId}/aim-tracking/weapon`
          : `/matches/${matchId}/aim-tracking`;

      const response = await api.get<HitData & WeaponStats>(
        `${endpoint}?${params.toString()}`,
        { requireAuth: true }
      );

      setData({
        head_hits_total: response.data.head_hits_total,
        upper_chest_hits_total: response.data.upper_chest_hits_total,
        chest_hits_total: response.data.chest_hits_total,
        legs_hits_total: response.data.legs_hits_total,
      });

      setWeaponStats({
        shots_fired: response.data.shots_fired,
        shots_hit: response.data.shots_hit,
        accuracy_all_shots: response.data.accuracy_all_shots,
        headshot_accuracy: response.data.headshot_accuracy,
        spraying_accuracy: response.data.spraying_accuracy,
      });
    } catch (error: any) {
      if (error.response?.status === 404) {
        setData(null);
        setWeaponStats(null);
      } else {
        console.error('Error fetching accuracy data:', error);
      }
    } finally {
      setLoading(false);
    }
  }, [matchId, playerSteamId, selectedWeapon]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Reset weapon selection when player changes
  useEffect(() => {
    setSelectedWeapon('all');
    setWeaponStats(null);
  }, [playerSteamId]);
  // Use provided data or fallback to zeros
  const hitData: HitData = data || {
    head_hits_total: 0,
    upper_chest_hits_total: 0,
    chest_hits_total: 0,
    legs_hits_total: 0,
  };

  const [hoveredRegion, setHoveredRegion] = useState<string | null>(null);
  const [mousePosition, setMousePosition] = useState({ x: 0, y: 0 });

  // Calculate total hits
  const totalHits =
    hitData.head_hits_total +
    hitData.upper_chest_hits_total +
    hitData.chest_hits_total +
    hitData.legs_hits_total;

  // Calculate percentages
  const percentages = {
    head: totalHits > 0 ? (hitData.head_hits_total / totalHits) * 100 : 0,
    upper_chest:
      totalHits > 0 ? (hitData.upper_chest_hits_total / totalHits) * 100 : 0,
    chest: totalHits > 0 ? (hitData.chest_hits_total / totalHits) * 100 : 0,
    legs: totalHits > 0 ? (hitData.legs_hits_total / totalHits) * 100 : 0,
  };

  // Get color based on percentage (0-100)
  const getColor = (percentage: number): string => {
    if (percentage < 10) {
      return 'transparent';
    }

    // Scale from 10% to 50% -> lightness from 0.15 to 0.75
    // The base color is oklch(0.75 0.18 42)
    // Cap at 50% so regions with high hit concentration show max brightness
    const normalizedPercentage = Math.min(percentage, 50) / 50;
    const lightness = 0.15 + normalizedPercentage * 0.6;

    return `oklch(${lightness} 0.18 42)`;
  };

  const getAccuracyColor = (value: number): string => {
    if (value < 30) return QUALITY_COLORS.poor.hex;
    if (value < 40) return QUALITY_COLORS.fair.hex;
    if (value < 50) return QUALITY_COLORS.good.hex;
    return QUALITY_COLORS.excellent.hex;
  };

  const handleMouseMove = (e: React.MouseEvent, region: string) => {
    setHoveredRegion(region);
    setMousePosition({ x: e.clientX, y: e.clientY });
  };

  const handleMouseLeave = () => {
    setHoveredRegion(null);
  };

  const getRegionLabel = (region: string): string => {
    switch (region) {
      case 'head':
        return 'Head';
      case 'upper_chest':
        return 'Upper Chest';
      case 'chest':
        return 'Chest';
      case 'legs':
        return 'Legs';
      default:
        return region;
    }
  };

  if (!data && !loading) {
    return (
      <Card>
        <CardContent>
          <div className="grid grid-cols-3 lg:grid-cols-3 gap-8 items-start">
            {/* Left Column - Title & Filter */}
            <div className="col-span-2">
              <div className="flex items-center justify-between mb-3">
                <CardTitle className="mb-0">Hit Distribution</CardTitle>
                <div className="group relative">
                  <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
                  <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                    <div className="text-sm">
                      <div className="font-semibold text-white mb-2">
                        Hit Distribution
                      </div>
                      <div className="text-gray-300 mb-3">
                        Shows where your shots are landing on the enemy body.
                        Visualizes hit percentage across different body regions.
                      </div>
                      <div className="text-xs text-gray-400">
                        <div className="font-medium mb-1">Body Regions:</div>
                        <div className="space-y-1">
                          <div>
                            <span className="text-orange-400">Head:</span>{' '}
                            Highest damage multiplier
                          </div>
                          <div>
                            <span className="text-orange-400">
                              Upper Chest:
                            </span>{' '}
                            High damage region
                          </div>
                          <div>
                            <span className="text-orange-400">Chest:</span>{' '}
                            Standard damage
                          </div>
                          <div>
                            <span className="text-orange-400">Legs:</span>{' '}
                            Lowest damage
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Select Weapon
                </label>
                <Select
                  value={selectedWeapon}
                  onValueChange={setSelectedWeapon}
                  disabled={loadingWeapons || weapons.length === 0}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue
                      placeholder={
                        loadingWeapons
                          ? 'Loading weapons...'
                          : 'Select a weapon...'
                      }
                    />
                  </SelectTrigger>
                  <SelectContent>
                    {weapons.map(weapon => (
                      <SelectItem key={weapon.value} value={weapon.value}>
                        {weapon.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Weapon Statistics */}
              {weaponStats && (
                <div className="mt-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-300">
                      Weapon Statistics
                    </h3>
                    <div className="group relative">
                      <Info className="h-3 w-3 text-gray-400 hover:text-gray-300 cursor-help" />
                      <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                        <div className="text-sm">
                          <div className="font-semibold text-white mb-2">
                            Weapon Statistics
                          </div>
                          <div className="text-gray-300 mb-3">
                            Detailed accuracy metrics for the selected weapon or
                            all weapons combined.
                          </div>
                          <div className="text-xs text-gray-400">
                            <div className="font-medium mb-1">Metrics:</div>
                            <div className="space-y-1">
                              <div>
                                <span className="text-blue-400">
                                  Overall Accuracy:
                                </span>{' '}
                                % of shots that hit
                              </div>
                              <div>
                                <span className="text-blue-400">
                                  Headshot %:
                                </span>{' '}
                                % of hits to the head
                              </div>
                              <div>
                                <span className="text-blue-400">
                                  Spray Accuracy:
                                </span>{' '}
                                Accuracy during sustained fire
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-400">Shots Fired</span>
                      <span className="text-white font-medium">
                        {Number(weaponStats.shots_fired) || 0}
                      </span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-400">Shots Hit</span>
                      <span className="text-white font-medium">
                        {Number(weaponStats.shots_hit) || 0}
                      </span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-400">Overall Accuracy</span>
                      <span
                        className="font-medium"
                        style={{
                          color: getAccuracyColor(
                            Number(weaponStats.accuracy_all_shots) || 0
                          ),
                        }}
                      >
                        {(Number(weaponStats.accuracy_all_shots) || 0).toFixed(
                          1
                        )}
                        %
                      </span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-400">Headshot %</span>
                      <span
                        className="font-medium"
                        style={{
                          color: getAccuracyColor(
                            Number(weaponStats.headshot_accuracy) || 0
                          ),
                        }}
                      >
                        {(Number(weaponStats.headshot_accuracy) || 0).toFixed(
                          1
                        )}
                        %
                      </span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-400">Spray Accuracy</span>
                      <span
                        className="font-medium"
                        style={{
                          color: getAccuracyColor(
                            Number(weaponStats.spraying_accuracy) || 0
                          ),
                        }}
                      >
                        {(Number(weaponStats.spraying_accuracy) || 0).toFixed(
                          1
                        )}
                        %
                      </span>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Right Column - Chart */}
            <div
              className="col-span-1 flex items-center justify-center rounded-lg text-gray-400"
              style={{ backgroundColor: 'oklch(0.13 0.028 261.692)' }}
            >
              No hit distribution data available
            </div>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardContent>
        <div className="grid grid-cols-3 lg:grid-cols-3 gap-8 items-start">
          {/* Left Column - Title & Filter */}
          <div className="col-span-2">
            <div className="flex items-center justify-between mb-6">
              <CardTitle className="mb-0">Hit Distribution</CardTitle>
              <div className="group relative">
                <Info className="h-4 w-4 text-gray-400 hover:text-gray-300 cursor-help" />
                <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                  <div className="text-sm">
                    <div className="font-semibold text-white mb-2">
                      Hit Distribution
                    </div>
                    <div className="text-gray-300 mb-3">
                      Shows where your shots are landing on the enemy body.
                      Visualizes hit percentage across different body regions.
                    </div>
                    <div className="text-xs text-gray-400">
                      <div className="font-medium mb-1">Body Regions:</div>
                      <div className="space-y-1">
                        <div>
                          <span className="text-orange-400">Head:</span> Highest
                          damage multiplier
                        </div>
                        <div>
                          <span className="text-orange-400">Upper Chest:</span>{' '}
                          High damage region
                        </div>
                        <div>
                          <span className="text-orange-400">Chest:</span>{' '}
                          Standard damage
                        </div>
                        <div>
                          <span className="text-orange-400">Legs:</span> Lowest
                          damage
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Select Weapon
              </label>
              <Select
                value={selectedWeapon}
                onValueChange={setSelectedWeapon}
                disabled={loadingWeapons || weapons.length === 0}
              >
                <SelectTrigger className="w-full">
                  <SelectValue
                    placeholder={
                      loadingWeapons
                        ? 'Loading weapons...'
                        : 'Select a weapon...'
                    }
                  />
                </SelectTrigger>
                <SelectContent>
                  {weapons.map(weapon => (
                    <SelectItem key={weapon.value} value={weapon.value}>
                      {weapon.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Weapon Statistics */}
            {weaponStats && (
              <div className="mt-8 space-y-2">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold text-gray-300">
                    Weapon Statistics
                  </h3>
                  <div className="group relative">
                    <Info className="h-3 w-3 text-gray-400 hover:text-gray-300 cursor-help" />
                    <div className="absolute top-full right-0 mt-2 w-80 p-3 bg-gray-800 border border-gray-600 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                      <div className="text-sm">
                        <div className="font-semibold text-white mb-2">
                          Weapon Statistics
                        </div>
                        <div className="text-gray-300 mb-3">
                          Detailed accuracy metrics for the selected weapon or
                          all weapons combined.
                        </div>
                        <div className="text-xs text-gray-400">
                          <div className="font-medium mb-1">Metrics:</div>
                          <div className="space-y-1">
                            <div>
                              <span className="text-blue-400">
                                Overall Accuracy:
                              </span>{' '}
                              % of shots that hit
                            </div>
                            <div>
                              <span className="text-blue-400">Headshot %:</span>{' '}
                              % of hits to the head
                            </div>
                            <div>
                              <span className="text-blue-400">
                                Spray Accuracy:
                              </span>{' '}
                              Accuracy during sustained fire
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Shots Fired</span>
                    <span className="text-white font-medium">
                      {Number(weaponStats.shots_fired) || 0}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Shots Hit</span>
                    <span className="text-white font-medium">
                      {Number(weaponStats.shots_hit) || 0}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Overall Accuracy</span>
                    <span
                      className="font-medium"
                      style={{
                        color: getAccuracyColor(
                          Number(weaponStats.accuracy_all_shots) || 0
                        ),
                      }}
                    >
                      {(Number(weaponStats.accuracy_all_shots) || 0).toFixed(1)}
                      %
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Headshot %</span>
                    <span
                      className="font-medium"
                      style={{
                        color: getAccuracyColor(
                          Number(weaponStats.headshot_accuracy) || 0
                        ),
                      }}
                    >
                      {(Number(weaponStats.headshot_accuracy) || 0).toFixed(1)}%
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Spray Accuracy</span>
                    <span
                      className="font-medium"
                      style={{
                        color: getAccuracyColor(
                          Number(weaponStats.spraying_accuracy) || 0
                        ),
                      }}
                    >
                      {(Number(weaponStats.spraying_accuracy) || 0).toFixed(1)}%
                    </span>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Right Column - Chart */}
          <div
            className="col-span-1 relative flex items-center justify-center rounded-lg h-full"
            style={{ backgroundColor: 'oklch(0.13 0.028 261.692)' }}
          >
            <svg
              width="200"
              height="280"
              viewBox="50 0 200 280"
              className="max-w-full"
            >
              {/* Head */}
              <ellipse
                cx="150"
                cy="45"
                rx="25"
                ry="25"
                fill={getColor(percentages.head)}
                stroke="white"
                strokeWidth="2"
                className="cursor-pointer transition-all duration-200 hover:opacity-80"
                onMouseMove={e => handleMouseMove(e, 'head')}
                onMouseLeave={handleMouseLeave}
              />

              {/* Upper Chest (Shoulders + Upper Torso) */}
              <path
                d="M 115 72 L 115 120 L 185 120 L 185 72 Z"
                fill={getColor(percentages.upper_chest)}
                stroke="white"
                strokeWidth="2"
                className="cursor-pointer transition-all duration-200 hover:opacity-80"
                onMouseMove={e => handleMouseMove(e, 'upper_chest')}
                onMouseLeave={handleMouseLeave}
              />

              {/* Arms */}
              {/* Left Arm - top-left corner rounded */}
              <path
                d="M 96 160 L 96 81 A 9 9 0 0 1 105 72 L 114 72 L 114 160 Z"
                fill="transparent"
                stroke="white"
                strokeWidth="2"
              />
              {/* Right Arm - top-right corner rounded */}
              <path
                d="M 185 160 L 185 72 L 194 72 A 9 9 0 0 1 203 81 L 203 160 Z"
                fill="transparent"
                stroke="white"
                strokeWidth="2"
              />

              {/* Chest (Lower Torso) */}
              <path
                d="M 115 120 L 115 168 L 185 168 L 185 120 Z"
                fill={getColor(percentages.chest)}
                stroke="white"
                strokeWidth="2"
                className="cursor-pointer transition-all duration-200 hover:opacity-80"
                onMouseMove={e => handleMouseMove(e, 'chest')}
                onMouseLeave={handleMouseLeave}
              />

              {/* Legs */}
              <g>
                {/* Left Leg */}
                <rect
                  x="115"
                  y="168"
                  width="32"
                  height="95"
                  fill={getColor(percentages.legs)}
                  stroke="white"
                  strokeWidth="2"
                  className="cursor-pointer transition-all duration-200 hover:opacity-80"
                  onMouseMove={e => handleMouseMove(e, 'legs')}
                  onMouseLeave={handleMouseLeave}
                />
                {/* Right Leg */}
                <rect
                  x="153"
                  y="168"
                  width="32"
                  height="95"
                  fill={getColor(percentages.legs)}
                  stroke="white"
                  strokeWidth="2"
                  className="cursor-pointer transition-all duration-200 hover:opacity-80"
                  onMouseMove={e => handleMouseMove(e, 'legs')}
                  onMouseLeave={handleMouseLeave}
                />
              </g>
            </svg>

            {/* Tooltip */}
            {hoveredRegion && (
              <div
                className="fixed z-50 bg-gray-900 text-white px-3 py-2 rounded-lg shadow-lg border border-gray-700 pointer-events-none"
                style={{
                  left: mousePosition.x + 10,
                  top: mousePosition.y + 10,
                }}
              >
                <div className="text-sm font-semibold">
                  {getRegionLabel(hoveredRegion)}
                </div>
                <div className="text-xs text-gray-300">
                  {percentages[
                    hoveredRegion as keyof typeof percentages
                  ].toFixed(1)}
                  %
                </div>
                <div className="text-xs text-gray-400">
                  {hitData[`${hoveredRegion}_hits_total` as keyof HitData]} hits
                </div>
              </div>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
};
