interface DifferenceMakerIconProps {
  className?: string;
  size?: number;
  color?: string;
}

export function DifferenceMakerIcon({
  className = '',
  size = 24,
  color = 'currentColor',
}: DifferenceMakerIconProps) {
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
        d="M3 12L7 8L12 13L17 8L21 12"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M3 18L7 14L12 19L17 14L21 18"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path d="M12 2V6" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path
        d="M12 10V14"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}
