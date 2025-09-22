import React from 'react';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';

interface CompetitiveRankProps {
  rank: number; // 0-18
  className?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  width?: number;
  height?: number;
}

const getSizeConfig = (size: 'sm' | 'md' | 'lg' | 'xl' | undefined) => {
  const configs = {
    sm: { width: 65, height: 25 },
    md: { width: 89, height: 32 },
    lg: { width: 133, height: 48 },
    xl: { width: 178, height: 64 },
  };
  return configs[size || 'md'];
};

const getRankInfo = (rank: number) => {
  const rankNames = {
    0: 'Unranked',
    1: 'Silver I',
    2: 'Silver II',
    3: 'Silver III',
    4: 'Silver IV',
    5: 'Silver Elite',
    6: 'Silver Elite Master',
    7: 'Gold Nova I',
    8: 'Gold Nova II',
    9: 'Gold Nova III',
    10: 'Gold Nova Master',
    11: 'Master Guardian I',
    12: 'Master Guardian II',
    13: 'Master Guardian Elite',
    14: 'Distinguished Master Guardian',
    15: 'Legendary Eagle',
    16: 'Legendary Eagle Master',
    17: 'Supreme Master First Class',
    18: 'Global Elite',
  };

  const rankImages = {
    0: '0.png',
    1: '1.png',
    2: '2.png',
    3: '3.png',
    4: '4.png',
    5: '5.png',
    6: '6.png',
    7: '7.png',
    8: '8.png',
    9: '9.png',
    10: '10.png',
    11: '11.png',
    12: '12.png',
    13: '13.png',
    14: '14.png',
    15: '15.png',
    16: '16.png',
    17: '17.png',
    18: '18.png',
  };

  return {
    name: rankNames[rank as keyof typeof rankNames] || 'Unknown',
    image: rankImages[rank as keyof typeof rankImages] || '0.png',
  };
};

export const CompetitiveRank: React.FC<CompetitiveRankProps> = ({
  rank,
  className = '',
  size = 'md',
  width,
  height,
}) => {
  const sizeConfig = getSizeConfig(size);
  const rankInfo = getRankInfo(rank);

  // Use custom width/height if provided, otherwise use size config
  const finalWidth = width || sizeConfig.width;
  const finalHeight = height || sizeConfig.height;

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <img
          src={`/images/ranks/valve-competitive-ranks/${rankInfo.image}`}
          alt={rankInfo.name}
          width={finalWidth}
          height={finalHeight}
          className={className}
        />
      </TooltipTrigger>
      <TooltipContent>
        <p>{rankInfo.name}</p>
      </TooltipContent>
    </Tooltip>
  );
};

export default CompetitiveRank;
