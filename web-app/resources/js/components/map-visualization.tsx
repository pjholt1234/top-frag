import React, { useEffect, useRef, useState, useCallback } from 'react';
import { getMapMetadata } from '../config/maps';

interface MapVisualizationProps {
  mapName: string;
  grenadePositions?: Array<{
    x: number;
    y: number;
    grenade_type?: string;
    player_name?: string;
    round_number?: number;
  }>;
}

const MapVisualization: React.FC<MapVisualizationProps> = ({
  mapName,
  grenadePositions = [],
}) => {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const imageRef = useRef<HTMLImageElement>(null);
  const [imageLoaded, setImageLoaded] = useState(false);

  // Get map metadata for coordinate conversion
  const mapMetadata = getMapMetadata(mapName);

  // Convert in-game coordinates to pixel coordinates
  const convertGameToPixelCoords = useCallback(
    (gameX: number, gameY: number) => {
      if (!mapMetadata) {
        console.error(`Map metadata not found for: ${mapName}`);
        return { x: 0, y: 0 };
      }

      // Apply offset to get coordinates relative to radar origin
      const offsetX = gameX + mapMetadata.offset.x;
      const offsetY = gameY + mapMetadata.offset.y;

      // Convert to pixel coordinates
      const pixelX = offsetX / mapMetadata.resolution;
      const pixelY = 1024 - offsetY / mapMetadata.resolution; // Flip Y-axis

      return { x: pixelX, y: pixelY };
    },
    [mapMetadata, mapName]
  );

  // Convert pixel coordinates back to in-game coordinates (reverse calculation)
  // const convertPixelToGameCoords = (pixelX: number, pixelY: number) => {
  //     if (!mapMetadata) {
  //         console.error(`Map metadata not found for: ${mapName}`);
  //         return { x: 0, y: 0 };
  //     }

  //     // Convert pixel coordinates to in-game units
  //     const offsetX = pixelX * mapMetadata.resolution;
  //     const offsetY = pixelY * mapMetadata.resolution;

  //     // Remove offset
  //     const gameX = offsetX - mapMetadata.offset.x;
  //     const gameY = offsetY - mapMetadata.offset.y;

  //     return { x: gameX, y: gameY };
  // };

  // Draw grenade positions on canvas
  const drawGrenadePositions = useCallback(() => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');

    if (!canvas || !ctx || !imageLoaded) return;

    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw grenade positions
    grenadePositions.forEach(position => {
      const pixelCoords = convertGameToPixelCoords(position.x, position.y);

      // Get color based on grenade type
      const colors: { [key: string]: string } = {
        incendiary: '#ff0000', // Red
        molotov: '#ff0000', // Red
        smoke: '#ffffff', // White
        he: '#ffa500', // Orange
        flashbang: '#ffff00', // Yellow
        decoy: '#0000ff', // Blue
      };

      const fillColor = colors[position.grenade_type || ''] || '#ff0000';

      // Draw colored circle
      ctx.beginPath();
      ctx.arc(pixelCoords.x, pixelCoords.y, 15, 0, 2 * Math.PI);
      ctx.fillStyle = fillColor;
      ctx.fill();
      ctx.strokeStyle = 'white';
      ctx.lineWidth = 3;
      ctx.stroke();
    });
  }, [grenadePositions, imageLoaded, convertGameToPixelCoords]);

  // Handle image load
  const handleImageLoad = () => {
    const canvas = canvasRef.current;
    const image = imageRef.current;

    if (canvas && image) {
      // Set canvas internal resolution to match display size (1024x1024)
      canvas.width = 1024;
      canvas.height = 1024;
      setImageLoaded(true);
    }
  };

  // Redraw when grenade positions change or image loads
  useEffect(() => {
    if (imageLoaded) {
      drawGrenadePositions();
    }
  }, [drawGrenadePositions, imageLoaded]);

  // Handle mouse move to show cursor coordinates
  const handleMouseMove = (_event: React.MouseEvent<HTMLDivElement>) => {
    // const rect = event.currentTarget.getBoundingClientRect();
    // const x = event.clientX - rect.left;
    // const y = event.clientY - rect.top;
    // Convert to in-game coordinates
    // const gameCoords = convertPixelToGameCoords(x, y);
    // TODO: Use gameCoords for tooltip or other functionality
  };

  return (
    <div
      className="relative inline-block"
      onMouseMove={handleMouseMove}
      style={{ cursor: 'crosshair' }}
    >
      <img
        ref={imageRef}
        src={mapMetadata?.imagePath || `/images/maps/${mapName}.png`}
        alt={`${mapName} map`}
        onLoad={handleImageLoad}
        className="block"
        style={{ width: '1024px', height: '1024px' }}
      />
      <canvas
        ref={canvasRef}
        className="absolute top-0 left-0 pointer-events-none"
        style={{ width: '1024px', height: '1024px' }}
      />
    </div>
  );
};

export default MapVisualization;
