import React from 'react';

interface ZoomSliderProps {
  zoomLevel: number;
  onZoomChange: (zoomLevel: number) => void;
  minZoom?: number;
  maxZoom?: number;
  height?: number;
}

const ZoomSlider: React.FC<ZoomSliderProps> = ({
  zoomLevel,
  onZoomChange,
  minZoom = 1.0,
  maxZoom = 5.0,
  height = 512,
}) => {
  // Calculate slider position based on zoom level
  const getSliderPosition = () => {
    const sliderRange = maxZoom - minZoom;
    const zoomProgress = (zoomLevel - minZoom) / sliderRange;
    // Clamp the progress to ensure we can reach the full range
    const clampedProgress = Math.max(0, Math.min(1, zoomProgress));
    return height - clampedProgress * height; // Invert for top-to-bottom
  };

  // Handle slider click
  const handleSliderClick = (e: React.MouseEvent<HTMLDivElement>) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const clickY = e.clientY - rect.top;
    const sliderHeight = rect.height;

    // Clamp clickY to slider bounds
    const clampedClickY = Math.max(0, Math.min(sliderHeight, clickY));
    const clickPercentage = 1 - clampedClickY / sliderHeight; // Invert for top-to-bottom

    // Convert to zoom level
    const newZoomLevel = minZoom + clickPercentage * (maxZoom - minZoom);
    const clampedZoomLevel = Math.max(minZoom, Math.min(maxZoom, newZoomLevel));

    onZoomChange(clampedZoomLevel);
  };

  return (
    <div className="flex flex-col items-center">
      <div
        className="relative w-8 cursor-pointer"
        style={{ height: `${height}px` }}
        onClick={handleSliderClick}
        title={`Zoom: ${Math.round(zoomLevel * 100)}%`}
      >
        {/* Main slider bar */}
        <div
          className="absolute left-1/2 transform -translate-x-1/2 w-2 h-full rounded-sm"
          style={{ backgroundColor: 'oklch(1 0 0 / 1)' }}
        ></div>

        {/* Top cap */}
        <div
          className="absolute top-0 left-1/2 transform -translate-x-1/2 w-6 h-1"
          style={{ backgroundColor: 'oklch(1 0 0 / 1)' }}
        ></div>

        {/* Bottom cap */}
        <div
          className="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-6 h-1"
          style={{ backgroundColor: 'oklch(1 0 0 / 1)' }}
        ></div>

        {/* Tick marks */}
        {[1, 2, 3, 4, 5].map(tick => {
          const tickPosition = (height / 6) * tick; // 6 divisions (0-5)
          return (
            <div
              key={tick}
              className="absolute w-3 h-0.5"
              style={{
                left: '50%',
                transform: 'translateX(-50%)',
                top: `${tickPosition}px`,
                backgroundColor: 'oklch(1 0 0 / 1)',
              }}
            ></div>
          );
        })}

        {/* Slider thumb */}
        <div
          className="absolute w-4 h-4 bg-custom-orange border-2 border-black rounded-full cursor-pointer z-10"
          style={{
            left: '50%',
            transform: 'translateX(-50%)',
            top: `${getSliderPosition() - 8}px`, // Center the thumb
          }}
        ></div>

        {/* Invisible click overlay to ensure full range is clickable */}
        <div
          className="absolute inset-0 cursor-pointer"
          style={{ zIndex: 5 }}
        ></div>
      </div>

      {/* Zoom percentage display */}
      <div className="mt-4 text-xs text-gray-600 font-medium">
        {Math.round(zoomLevel * 100)}%
      </div>
    </div>
  );
};

export default ZoomSlider;
