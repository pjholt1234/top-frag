interface TopAimerIconProps {
  className?: string;
  size?: number;
  color?: string;
}

export function TopAimerIcon({
  className = '',
  size = 24,
  color = 'currentColor',
}: TopAimerIconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
    >
      <circle
        cx="12"
        cy="12"
        r="10"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle
        cx="12"
        cy="12"
        r="6"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx="12" cy="12" r="2" fill={color} />
      <line
        x1="12"
        y1="2"
        x2="12"
        y2="4"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
      />
      <line
        x1="12"
        y1="20"
        x2="12"
        y2="22"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
      />
      <line
        x1="2"
        y1="12"
        x2="4"
        y2="12"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
      />
      <line
        x1="20"
        y1="12"
        x2="22"
        y2="12"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}
