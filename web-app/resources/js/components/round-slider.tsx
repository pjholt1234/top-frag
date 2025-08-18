import React from 'react';

interface RoundSliderProps {
  selectedRound: number | null; // null means "all rounds"
  onRoundChange: (round: number | null) => void;
  maxRounds: number;
  width?: number;
}

const RoundSlider: React.FC<RoundSliderProps> = ({
  selectedRound,
  onRoundChange,
  maxRounds,
  width = 512,
}) => {
  // Calculate slider position based on selected round
  const getSliderPosition = () => {
    if (selectedRound === null) {
      return 0; // All the way to the left for "all rounds"
    }
    const roundProgress = selectedRound / maxRounds;
    const clampedProgress = Math.max(0, Math.min(1, roundProgress));
    return clampedProgress * width;
  };

  // Handle slider click
  const handleSliderClick = (e: React.MouseEvent<HTMLDivElement>) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    const sliderWidth = rect.width;

    // Clamp clickX to slider bounds
    const clampedClickX = Math.max(0, Math.min(sliderWidth, clickX));
    const clickPercentage = clampedClickX / sliderWidth;

    // Convert to round number
    const newRound = Math.round(clickPercentage * maxRounds);
    const clampedRound = Math.max(0, Math.min(maxRounds, newRound));

    // If clicked at the very beginning (first 5% of slider), select "all rounds"
    if (clickPercentage <= 0.05) {
      onRoundChange(null);
    } else {
      onRoundChange(clampedRound);
    }
  };

  return (
    <div className="flex flex-col items-center">
      <div
        className="relative h-8 cursor-pointer"
        style={{ width: `${width}px` }}
        onClick={handleSliderClick}
        title={selectedRound === null ? 'All Rounds' : `Round ${selectedRound}`}
      >
        {/* Main slider bar */}
        <div
          className="absolute top-1/2 transform -translate-y-1/2 w-full h-2 rounded-sm"
          style={{ backgroundColor: 'oklch(1 0 0 / 1)' }}
        ></div>

        {/* Left cap */}
        <div
          className="absolute left-0 top-1/2 transform -translate-y-1/2 w-1 h-6"
          style={{ backgroundColor: 'oklch(1 0 0 / 1)' }}
        ></div>

        {/* Right cap */}
        <div
          className="absolute right-0 top-1/2 transform -translate-y-1/2 w-1 h-6"
          style={{ backgroundColor: 'oklch(1 0 0 / 1)' }}
        ></div>

        {/* Round tick marks */}
        {Array.from({ length: maxRounds + 1 }, (_, i) => {
          const tickPosition = (width / maxRounds) * i;
          // Ensure the last tick doesn't go past the right edge
          const clampedPosition = Math.min(tickPosition, width - 2);
          const isMultipleOf5 = i % 5 === 0;
          const isFirstRound = i === 0;

          return (
            <div key={i}>
              {/* Tick mark */}
              <div
                className={`absolute ${
                  isMultipleOf5 ? 'w-1 h-4' : 'w-0.5 h-2'
                }`}
                style={{
                  left: `${clampedPosition}px`,
                  top: isMultipleOf5 ? '50%' : 'calc(50% + 1px)',
                  transform: isMultipleOf5 ? 'translateY(-50%)' : 'none',
                  zIndex: 1,
                  backgroundColor: 'oklch(1 0 0 / 1)',
                }}
              ></div>

              {/* Label for multiples of 5 */}
              {isMultipleOf5 && !isFirstRound && (
                <div
                  className="absolute text-xs text-gray-600 font-medium"
                  style={{
                    left: `${clampedPosition}px`,
                    top: '100%',
                    transform: 'translateX(-50%)',
                    marginTop: '4px',
                  }}
                >
                  {i}
                </div>
              )}

              {/* "All" label for first position */}
              {isFirstRound && (
                <div
                  className="absolute text-xs text-gray-600 font-medium"
                  style={{
                    left: `${tickPosition}px`,
                    top: '100%',
                    transform: 'translateX(-50%)',
                    marginTop: '4px',
                  }}
                >
                  All
                </div>
              )}
            </div>
          );
        })}

        {/* Slider thumb */}
        <div
          className="absolute w-4 h-4 bg-custom-orange border-2 border-black rounded-full cursor-pointer z-10"
          style={{
            top: '50%',
            transform: 'translateY(-50%)',
            left: `${getSliderPosition() - 8}px`, // Center the thumb
          }}
        ></div>

        {/* Invisible click overlay to ensure full range is clickable */}
        <div
          className="absolute inset-0 cursor-pointer"
          style={{ zIndex: 5 }}
        ></div>
      </div>

      {/* Round display */}
      <div className="mt-6 text-xs text-gray-600 font-medium">
        {selectedRound === null ? 'All Rounds' : `Round ${selectedRound}`}
      </div>
    </div>
  );
};

export default RoundSlider;
