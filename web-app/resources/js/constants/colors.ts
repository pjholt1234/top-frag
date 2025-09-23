// Player complexion role colors
export const COMPLEXION_COLORS = {
  opener: {
    text: 'text-yellow-500',
    hex: '#eab308',
    rgb: 'rgb(234, 179, 8)',
    name: 'yellow-500',
  },
  closer: {
    text: 'text-purple-500',
    hex: '#8b5cf6',
    rgb: 'rgb(139, 92, 246)',
    name: 'purple-500',
  },
  support: {
    text: 'text-blue-500',
    hex: '#3b82f6',
    rgb: 'rgb(59, 130, 246)',
    name: 'blue-500',
  },
  fragger: {
    text: 'text-red-500',
    hex: '#ef4444',
    rgb: 'rgb(239, 68, 68)',
    name: 'red-500',
  },
} as const;

// Type for complexion role keys
export type ComplexionRole = keyof typeof COMPLEXION_COLORS;

// Helper function to get color by role
export const getComplexionColor = (role: ComplexionRole) =>
  COMPLEXION_COLORS[role];

// Helper function to get all roles
export const getComplexionRoles = (): ComplexionRole[] =>
  Object.keys(COMPLEXION_COLORS) as ComplexionRole[];

// Quality level types and colors
export type QualityLevel = 'poor' | 'fair' | 'good' | 'excellent';
export type ColorFormat = 'hex' | 'text' | 'bg';

export interface ColorScheme {
  poor: {
    hex: string;
    text: string;
    bg: string;
  };
  fair: {
    hex: string;
    text: string;
    bg: string;
  };
  good: {
    hex: string;
    text: string;
    bg: string;
  };
  excellent: {
    hex: string;
    text: string;
    bg: string;
  };
}

export const QUALITY_COLORS: ColorScheme = {
  poor: {
    hex: '#f87171', // red-400
    text: 'text-red-600',
    bg: 'bg-red-600',
  },
  fair: {
    hex: '#fb923c', // orange-400
    text: 'text-orange-500',
    bg: 'bg-orange-500',
  },
  good: {
    hex: '#22c55e', // green-500
    text: 'text-green-500',
    bg: 'bg-green-500',
  },
  excellent: {
    hex: '#16a34a', // green-600
    text: 'text-green-600',
    bg: 'bg-green-600',
  },
};

/**
 * Get color for a quality level in the specified format
 * @param level - The quality level (poor, fair, good, excellent)
 * @param format - The color format (hex, text, bg)
 * @returns The color value in the requested format
 */
export function getQualityColor(
  level: QualityLevel,
  format: ColorFormat
): string {
  return QUALITY_COLORS[level][format];
}

/**
 * Get quality level based on a numeric rating (0-100)
 * @param rating - The numeric rating (0-100)
 * @returns The quality level
 */
export function getQualityLevel(rating: number): QualityLevel {
  if (rating >= 75) return 'excellent';
  if (rating >= 50) return 'good';
  if (rating >= 25) return 'fair';
  return 'poor';
}

/**
 * Get color for a numeric rating in the specified format
 * @param rating - The numeric rating (0-100)
 * @param format - The color format (hex, text, bg)
 * @returns The color value in the requested format
 */
export function getRatingColor(rating: number, format: ColorFormat): string {
  const level = getQualityLevel(rating);
  return getQualityColor(level, format);
}

/**
 * Get color for a numeric rating with custom thresholds
 * @param value - The numeric value
 * @param thresholds - Array of threshold values [poor, fair, good, excellent]
 * @param format - The color format (hex, text, bg)
 * @returns The color value in the requested format
 */
export function getCustomRatingColor(
  value: number,
  thresholds: [number, number, number, number],
  format: ColorFormat
): string {
  const [fairThreshold, goodThreshold, excellentThreshold] = thresholds;

  if (value >= excellentThreshold) return getQualityColor('excellent', format);
  if (value >= goodThreshold) return getQualityColor('good', format);
  if (value >= fairThreshold) return getQualityColor('fair', format);
  return getQualityColor('poor', format);
}

// Premier rank colors
export interface PremierRankColors {
  barColor: string;
  backgroundColor: string;
}

export interface PremierRankTier {
  min: number;
  max: number;
  barColor: string;
  backgroundColor: string;
}

export const PREMIER_RANK_TIERS: PremierRankTier[] = [
  { min: 0, max: 4999, barColor: '#a7b1c3', backgroundColor: '#434955' },
  { min: 5000, max: 9999, barColor: '#7aaad6', backgroundColor: '#1f2f3f' },
  { min: 10000, max: 14999, barColor: '#455cda', backgroundColor: '#0d1440' },
  { min: 15000, max: 19999, barColor: '#aa53f1', backgroundColor: '#2f0f3f' },
  { min: 20000, max: 24999, barColor: '#aa53f1', backgroundColor: '#2f0f3f' },
  { min: 25000, max: 29999, barColor: '#f12431', backgroundColor: '#4a0308' },
  {
    min: 30000,
    max: Infinity,
    barColor: '#ebc507',
    backgroundColor: '#4a3a00',
  },
];

/**
 * Get Premier rank colors based on rank value
 * @param rank - The Premier rank value
 * @returns The colors for the rank tier
 */
export function getPremierRankColors(rank: number): PremierRankColors {
  const tier = PREMIER_RANK_TIERS.find(
    tier => rank >= tier.min && rank <= tier.max
  );
  return tier || PREMIER_RANK_TIERS[0]; // Fallback to first tier if no match
}
