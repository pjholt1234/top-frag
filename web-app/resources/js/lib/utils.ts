import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

// Standardized color scheme for ratings/quality indicators
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
    text: 'text-red-400',
    bg: 'bg-red-500',
  },
  fair: {
    hex: '#fb923c', // orange-400
    text: 'text-orange-400',
    bg: 'bg-orange-400',
  },
  good: {
    hex: '#86efac', // green-300
    text: 'text-green-300',
    bg: 'bg-green-400',
  },
  excellent: {
    hex: '#4ade80', // green-400
    text: 'text-green-400',
    bg: 'bg-green-600',
  },
};

/**
 * Get color for a quality level in the specified format
 * @param level - The quality level (poor, fair, good, excellent)
 * @param format - The color format (hex, text, bg)
 * @returns The color value in the requested format
 */
export function getQualityColor(level: QualityLevel, format: ColorFormat): string {
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
  const [poorThreshold, fairThreshold, goodThreshold, excellentThreshold] = thresholds;

  if (value >= excellentThreshold) return getQualityColor('excellent', format);
  if (value >= goodThreshold) return getQualityColor('good', format);
  if (value >= fairThreshold) return getQualityColor('fair', format);
  return getQualityColor('poor', format);
}

/**
 * Returns the appropriate color class for ADR (Average Damage per Round) values
 * @param adr - The ADR value as an integer
 * @returns Tailwind CSS color class
 */
export function getAdrColor(adr: number): string {
  if (adr < 40) {
    return 'text-red-600 dark:text-red-400';
  } else if (adr >= 41 && adr <= 50) {
    return 'text-orange-600 dark:text-orange-400';
  } else if (adr >= 51 && adr <= 60) {
    return 'text-yellow-600 dark:text-yellow-400';
  } else if (adr >= 61 && adr <= 70) {
    return ''; // Default text color (white/neutral)
  } else if (adr >= 71 && adr <= 90) {
    return 'text-green-600 dark:text-green-400';
  } else {
    // 91+
    return 'text-green-800 dark:text-green-300';
  }
}
