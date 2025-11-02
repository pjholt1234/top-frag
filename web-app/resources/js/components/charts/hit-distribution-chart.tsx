import { useState } from 'react';
import { QUALITY_COLORS } from '@/constants/colors';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

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

interface HitDistributionChartProps {
  hitData: HitData;
  weaponStats?: WeaponStats;
  showWeaponStats?: boolean;
  weapons?: Weapon[];
  selectedWeapon?: string;
  onWeaponChange?: (weapon: string) => void;
  loadingWeapons?: boolean;
}

export const HitDistributionChart = ({
  hitData,
  weaponStats,
  showWeaponStats = true,
  weapons = [],
  selectedWeapon = 'all',
  onWeaponChange,
  loadingWeapons = false,
}: HitDistributionChartProps) => {
  const [hoveredRegion, setHoveredRegion] = useState<string | null>(null);
  const [mousePosition, setMousePosition] = useState({ x: 0, y: 0 });

  // Ensure all values are numbers
  const headHits = Number(hitData.head_hits_total) || 0;
  const upperChestHits = Number(hitData.upper_chest_hits_total) || 0;
  const chestHits = Number(hitData.chest_hits_total) || 0;
  const legsHits = Number(hitData.legs_hits_total) || 0;

  // Calculate total hits
  const totalHits = headHits + upperChestHits + chestHits + legsHits;

  // Calculate percentages (ensuring they add up to 100%)
  const percentages = {
    head: totalHits > 0 ? (headHits / totalHits) * 100 : 0,
    upper_chest: totalHits > 0 ? (upperChestHits / totalHits) * 100 : 0,
    chest: totalHits > 0 ? (chestHits / totalHits) * 100 : 0,
    legs: totalHits > 0 ? (legsHits / totalHits) * 100 : 0,
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

  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      {/* Left Column - Weapon Filter & Statistics */}
      {showWeaponStats && weaponStats && (
        <div className="col-span-2">
          <div className="space-y-4">
            {/* Weapon Filter */}
            {weapons.length > 0 && onWeaponChange && (
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  Select Weapon
                </label>
                <Select
                  value={selectedWeapon}
                  onValueChange={onWeaponChange}
                  disabled={loadingWeapons || weapons.length === 0}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue
                      placeholder={
                        loadingWeapons
                          ? 'Loading weapons...'
                          : weapons.length === 0
                            ? 'No weapons available'
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
            )}

            {/* Weapon Statistics */}
            <div className="space-y-3">
              <h3 className="text-sm font-semibold text-gray-300">
                Weapon Statistics
              </h3>
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
                    {(Number(weaponStats.accuracy_all_shots) || 0).toFixed(1)}%
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
          </div>
        </div>
      )}

      {/* Right Column - Player Silhouette */}
      <div
        className={`${showWeaponStats && weaponStats ? 'col-span-1' : 'col-span-3'} relative flex items-start justify-center rounded-lg pt-2`}
        style={{
          backgroundColor: 'oklch(0.13 0.028 261.692)',
          minHeight: '200px',
        }}
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
              {percentages[hoveredRegion as keyof typeof percentages].toFixed(
                1
              )}
              %
            </div>
            <div className="text-xs text-gray-400">
              {Number(
                hitData[`${hoveredRegion}_hits_total` as keyof HitData]
              ) || 0}{' '}
              hits
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
