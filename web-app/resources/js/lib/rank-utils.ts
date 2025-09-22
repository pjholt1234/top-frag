import React from 'react';
import { CompetitiveRank } from '@/components/shared/competitive-rank';
import { PremierRank } from '@/components/shared/premier-rank';

interface RankDisplayProps {
  gameMode: string | null | undefined;
  matchType: string | null | undefined;
  rankValue: number;
  variant?: 'sm' | 'md' | 'lg';
}

export const getRankDisplay = ({
  gameMode,
  matchType,
  rankValue,
  variant = 'sm',
}: RankDisplayProps): React.ReactElement | null => {
  // Check if we have a valid rank value
  if (rankValue === null || rankValue === undefined) {
    return null;
  }

  // For competitive mode
  if (gameMode === 'competitive' && matchType === 'valve') {
    return React.createElement(CompetitiveRank, {
      rank: rankValue,
      size: variant,
    });
  }

  // For premier mode
  if (gameMode === 'premier' && matchType === 'valve') {
    return React.createElement(PremierRank, { rank: rankValue, size: variant });
  }

  return null;
};

export const hasValidRank = (rankValue: number | null | undefined): boolean => {
  return rankValue !== null && rankValue !== undefined && rankValue > 0;
};
