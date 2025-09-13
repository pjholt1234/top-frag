import React from 'react';

interface FraggerIconProps {
  className?: string;
  size?: number;
  color?: string;
}

export function FraggerIcon({
  className = '',
  size = 24,
  color = 'currentColor',
}: FraggerIconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
    >
      <path
        d="M21 12C21 16.9706 16.9706 21 12 21M21 12C21 7.02944 16.9706 3 12 3M21 12H17M12 21C7.02944 21 3 16.9706 3 12M12 21V17M3 12C3 7.02944 7.02944 3 12 3M3 12H7M12 3V7M12 12H12.01"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
