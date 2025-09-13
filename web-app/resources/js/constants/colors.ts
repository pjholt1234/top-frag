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
    text: 'text-orange-600',
    bg: 'bg-orange-600',
  },
  good: {
    hex: '#86efac', // green-300
    text: 'text-green-300',
    bg: 'bg-green-400',
  },
  excellent: {
    hex: '#4ade80', // green-400
    text: 'text-green-400',
    bg: 'bg-green-400',
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
