import React from 'react';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { getPremierRankColors } from '@/constants/colors';

interface PremierRankProps {
  rank: number;
  className?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  width?: number;
  height?: number;
}


const formatRank = (rank: number): string => {
  if (rank === 0) {
    return "---"
  }
  return rank.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};

const getSizeConfig = (size: 'sm' | 'md' | 'lg' | 'xl' | undefined) => {
  const configs = {
    sm: { width: 65, height: 25 },
    md: { width: 89, height: 32 },
    lg: { width: 133, height: 48 },
    xl: { width: 178, height: 64 },
  };
  return configs[size || 'md'];
};

export const PremierRank: React.FC<PremierRankProps> = ({
  rank,
  className = '',
  size = 'lg',
  width,
  height,
}) => {
  const colors = getPremierRankColors(rank);
  const formattedRank = formatRank(rank);
  const sizeConfig = getSizeConfig(size);

  // Use custom width/height if provided, otherwise use size config
  const finalWidth = width || sizeConfig.width;
  const finalHeight = height || sizeConfig.height;

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <svg
          width={finalWidth}
          height={finalHeight}
          viewBox="0 0 178 64"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          className={className}
        >
          <g clipPath="url(#clip0_632_1563)">
            {/* Left side bar */}
            <path d="M25 0H21L9 64H13L25 0Z" fill="#656565" />

            {/* Main rank bar with dynamic colors */}
            <path d="M178 0H33.9996L22 64H166L178 0Z" fill={colors.barColor} />

            {/* Inner bar with background color */}
            <path
              d="M176.25 1.5H33.24L21.6562 62.5H164.666L176.25 1.5Z"
              fill={colors.backgroundColor}
            />

            {/* Decorative elements with opacity */}
            <path
              opacity="0.2"
              d="M46.1141 4L54 4L40.8859 61H33L46.1141 4Z"
              fill={colors.barColor}
            />
            <path
              opacity="0.3"
              d="M36.7301 4L42 4L30.2699 61H25L36.7301 4Z"
              fill={colors.barColor}
            />
            <path
              opacity="0.1"
              d="M56.8737 4L72 4L59.1263 61H44L56.8737 4Z"
              fill={colors.barColor}
            />
            <path
              opacity="0.1"
              d="M75.7813 4L110 4L97.2187 61H63L75.7813 4Z"
              fill={colors.barColor}
            />

            {/* Left side elements */}
            <path d="M18 0H27L18 64H3.25L18 0Z" fill="#3A3A3A" />
            <path d="M12 0H21L9 64H0L12 0Z" fill={colors.backgroundColor} />
            <path
              d="M24.9997 0H33.9997L22 64H13L24.9997 0Z"
              fill={colors.barColor}
            />

            {/* Gradient overlays for the main bar */}
            <path d="M25 0H33L21 64H13L25 0Z" fill={colors.barColor} />
            <path d="M12 0H20L8 64H0L12 0Z" fill={colors.barColor} />

            {/* Rank text */}
            <text
              x="96"
              y="36"
              textAnchor="middle"
              dominantBaseline="middle"
              fill={colors.barColor}
              fontSize="34"
              fontWeight="bold"
              fontFamily="Arial, sans-serif"
              fontStyle="italic"
            >
              {formattedRank}
            </text>
          </g>

          <defs>
            <linearGradient
              id="paint0_linear_632_1563"
              x1="187.49"
              y1="48.7288"
              x2="30.4973"
              y2="20.5012"
              gradientUnits="userSpaceOnUse"
            >
              <stop offset="0.9053" stopColor={colors.barColor} />
              <stop offset="1" stopColor={colors.barColor} />
            </linearGradient>
            <linearGradient
              id="paint1_linear_632_1563"
              x1="185.411"
              y1="47.9446"
              x2="26.5628"
              y2="33.7951"
              gradientUnits="userSpaceOnUse"
            >
              <stop
                offset="0.862691"
                stopColor={colors.backgroundColor}
                stopOpacity="0.44"
              />
              <stop offset="1" stopColor={colors.backgroundColor} />
            </linearGradient>
            <linearGradient
              id="paint2_linear_632_1563"
              x1="23.4998"
              y1="1"
              x2="23.4998"
              y2="63"
              gradientUnits="userSpaceOnUse"
            >
              <stop stopColor={colors.barColor} />
              <stop offset="1" stopColor={colors.barColor} />
            </linearGradient>
            <linearGradient
              id="paint3_linear_632_1563"
              x1="23.4998"
              y1="1"
              x2="23.4998"
              y2="63"
              gradientUnits="userSpaceOnUse"
            >
              <stop stopColor={colors.barColor} />
              <stop offset="1" stopColor={colors.barColor} />
            </linearGradient>
            <linearGradient
              id="paint4_linear_632_1563"
              x1="10.4998"
              y1="1"
              x2="10.4998"
              y2="63"
              gradientUnits="userSpaceOnUse"
            >
              <stop stopColor={colors.barColor} />
              <stop offset="1" stopColor={colors.barColor} />
            </linearGradient>
            <linearGradient
              id="paint5_linear_632_1563"
              x1="10.4998"
              y1="1"
              x2="10.4998"
              y2="63"
              gradientUnits="userSpaceOnUse"
            >
              <stop stopColor={colors.barColor} />
              <stop offset="1" stopColor={colors.barColor} />
            </linearGradient>
            <clipPath id="clip0_632_1563">
              <rect width="178" height="64" fill="white" />
            </clipPath>
          </defs>
        </svg>
      </TooltipTrigger>
      <TooltipContent>
        <p>Rank: {rank.toLocaleString()}</p>
      </TooltipContent>
    </Tooltip>
  );
};

export default PremierRank;
