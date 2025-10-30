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

/**
 * Returns the appropriate color class for K/D ratio values
 * @param kd - The K/D ratio value
 * @returns Tailwind CSS color class
 */
export function getKdColor(kd: number): string {
  if (kd < 0.8) {
    return QUALITY_COLORS.poor.text;
  } else if (kd >= 0.8 && kd < 1.0) {
    return QUALITY_COLORS.fair.text;
  } else if (kd >= 1.0 && kd < 1.3) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}

/**
 * Returns the appropriate color class for Win Rate percentage values
 * @param winRate - The win rate as a percentage (0-100)
 * @returns Tailwind CSS color class
 */
export function getWinRateColor(winRate: number): string {
  if (winRate < 40) {
    return QUALITY_COLORS.poor.text;
  } else if (winRate >= 40 && winRate < 50) {
    return QUALITY_COLORS.fair.text;
  } else if (winRate >= 50 && winRate < 60) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}

/**
 * Returns the appropriate color class for Average Kills values
 * @param avgKills - The average kills per match
 * @returns Tailwind CSS color class
 */
export function getAvgKillsColor(avgKills: number): string {
  if (avgKills < 12) {
    return QUALITY_COLORS.poor.text;
  } else if (avgKills >= 12 && avgKills < 16) {
    return QUALITY_COLORS.fair.text;
  } else if (avgKills >= 16 && avgKills < 20) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}

/**
 * Returns the appropriate color class for Impact Rating values
 * @param impact - The impact rating value
 * @returns Tailwind CSS color class
 */
export function getImpactColor(impact: number): string {
  if (impact < 0) {
    return QUALITY_COLORS.poor.text;
  }

  if (impact >= 3.5) {
    return QUALITY_COLORS.excellent.text;
  }
  if (impact >= 2.5) {
    return QUALITY_COLORS.good.text;
  }
  if (impact >= 1.5) {
    return QUALITY_COLORS.fair.text;
  }

  return QUALITY_COLORS.poor.text;
}

/**
 * Returns the appropriate color class for Round Swing percentage values
 * @param roundSwing - The round swing percentage
 * @returns Tailwind CSS color class
 */
export function getRoundSwingColor(roundSwing: number): string {
  if (roundSwing < 0) {
    return QUALITY_COLORS.poor.text;
  }

  if (roundSwing >= 10) {
    return QUALITY_COLORS.excellent.text;
  }
  if (roundSwing >= 5) {
    return QUALITY_COLORS.good.text;
  }
  if (roundSwing >= 2) {
    return QUALITY_COLORS.fair.text;
  }

  return QUALITY_COLORS.poor.text;
}

/**
 * Returns the appropriate color class for Opening Kills values
 * @param openingKills - The average opening kills per match
 * @returns Tailwind CSS color class
 */
export function getOpeningKillsColor(openingKills: number): string {
  if (openingKills < 0.5) {
    return QUALITY_COLORS.poor.text;
  } else if (openingKills >= 0.5 && openingKills < 1.0) {
    return QUALITY_COLORS.fair.text;
  } else if (openingKills >= 1.0 && openingKills < 1.5) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}

/**
 * Returns the appropriate color class for Opening Deaths values
 * Note: Lower is better for deaths
 * @param openingDeaths - The average opening deaths per match
 * @returns Tailwind CSS color class
 */
export function getOpeningDeathsColor(openingDeaths: number): string {
  if (openingDeaths >= 1.5) {
    return QUALITY_COLORS.poor.text;
  } else if (openingDeaths >= 1.0 && openingDeaths < 1.5) {
    return QUALITY_COLORS.fair.text;
  } else if (openingDeaths >= 0.5 && openingDeaths < 1.0) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}

/**
 * Returns the appropriate color class for Average Assists values
 * @param avgAssists - The average assists per match
 * @returns Tailwind CSS color class
 */
export function getAvgAssistsColor(avgAssists: number): string {
  if (avgAssists < 3) {
    return QUALITY_COLORS.poor.text;
  } else if (avgAssists >= 3 && avgAssists < 5) {
    return QUALITY_COLORS.fair.text;
  } else if (avgAssists >= 5 && avgAssists < 7) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}

/**
 * Returns the appropriate color class for Average Deaths values
 * Note: Lower is better for deaths
 * @param avgDeaths - The average deaths per match
 * @returns Tailwind CSS color class
 */
export function getAvgDeathsColor(avgDeaths: number): string {
  if (avgDeaths >= 20) {
    return QUALITY_COLORS.poor.text;
  } else if (avgDeaths >= 16 && avgDeaths < 20) {
    return QUALITY_COLORS.fair.text;
  } else if (avgDeaths >= 12 && avgDeaths < 16) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}

/**
 * Returns the appropriate color class for Player Complexion (role) scores
 * @param score - The complexion score (0-100)
 * @returns Tailwind CSS color class
 */
export function getComplexionScoreColor(score: number): string {
  if (score < 25) {
    return QUALITY_COLORS.poor.text;
  } else if (score >= 25 && score < 50) {
    return QUALITY_COLORS.fair.text;
  } else if (score >= 50 && score < 75) {
    return QUALITY_COLORS.good.text;
  }

  return QUALITY_COLORS.excellent.text;
}
