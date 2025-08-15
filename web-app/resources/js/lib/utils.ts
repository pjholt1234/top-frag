import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
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
