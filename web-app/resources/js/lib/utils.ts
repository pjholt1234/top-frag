import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import {
  type QualityLevel,
  type ColorFormat,
  getQualityColor,
  getQualityLevel,
  getRatingColor,
  getCustomRatingColor,
  QUALITY_COLORS,
} from '@/constants/colors';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

// Re-export quality color functions for backward compatibility
export {
  type QualityLevel,
  type ColorFormat,
  getQualityColor,
  getQualityLevel,
  getRatingColor,
  getCustomRatingColor,
};

/**
 * Returns the appropriate color class for ADR (Average Damage per Round) values
 * @param adr - The ADR value as an integer
 * @returns Tailwind CSS color class
 */
export function getAdrColor(adr: number): string {
  if (adr <= 40) {
    return QUALITY_COLORS.poor.text;
  } else if (adr >= 41 && adr <= 60) {
    return QUALITY_COLORS.fair.text;
  } else if (adr >= 61 && adr <= 80) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}
