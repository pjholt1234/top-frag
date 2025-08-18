import React, { useState, useCallback, useMemo } from 'react';
import { Stage, Layer, Image, Circle, Line } from 'react-konva';
import { getMapMetadata } from '../config/maps';
import ZoomSlider from './zoom-slider';
import RoundSlider from './round-slider';

interface MapVisualizationProps {
  mapName: string;
  grenadePositions?: Array<{
    x: number;
    y: number;
    grenade_type?: string;
    player_name?: string;
    round_number?: number;
    player_x?: number;
    player_y?: number;
  }>;
}

const MapVisualizationKonva: React.FC<MapVisualizationProps> = ({
  mapName,
  grenadePositions = [],
}) => {
  const [selectedMarkerIndex, setSelectedMarkerIndex] = useState<number | null>(
    null
  );
  const [zoomLevel, setZoomLevel] = useState(1.0);
  const [stagePosition, setStagePosition] = useState({ x: 0, y: 0 });
  const [image, setImage] = useState<HTMLImageElement | null>(null);
  const [selectedRound, setSelectedRound] = useState<number | null>(null);

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

      // Convert to pixel coordinates (scale to 512x512)
      const pixelX = (offsetX / mapMetadata.resolution) * 0.5; // Scale from 1024 to 512
      const pixelY = (1024 - offsetY / mapMetadata.resolution) * 0.5; // Flip Y-axis and scale

      return { x: pixelX, y: pixelY };
    },
    [mapMetadata, mapName]
  );

  // Load map image
  React.useEffect(() => {
    const img = new window.Image();
    img.src = mapMetadata?.imagePath || `/images/maps/${mapName}.png`;
    img.onload = () => {
      setImage(img);
    };
  }, [mapName, mapMetadata]);

  // Reset position when zoom level changes to 1.0
  React.useEffect(() => {
    if (zoomLevel === 1.0) {
      setStagePosition({ x: 0, y: 0 });
    }
  }, [zoomLevel]);

  // Get color based on grenade type
  const getGrenadeColor = (grenadeType: string) => {
    const colors: { [key: string]: string } = {
      incendiary: '#ff0000', // Red
      molotov: '#ff0000', // Red
      smoke: '#ffffff', // White
      he: '#ffa500', // Orange
      flashbang: '#ffff00', // Yellow
      decoy: '#0000ff', // Blue
    };
    return colors[grenadeType] || '#ff0000';
  };

  // Handle marker click
  const handleMarkerClick = (index: number) => {
    setSelectedMarkerIndex(selectedMarkerIndex === index ? null : index);
  };

  // Handle stage wheel for zoom
  const handleWheel = (e: any) => {
    e.evt.preventDefault();

    const scaleBy = 1.02;
    const stage = e.target.getStage();
    const oldScale = stage.scaleX();

    const mousePointTo = {
      x: stage.getPointerPosition().x / oldScale - stage.x() / oldScale,
      y: stage.getPointerPosition().y / oldScale - stage.y() / oldScale,
    };

    const newScale = e.evt.deltaY > 0 ? oldScale * scaleBy : oldScale / scaleBy;
    const clampedScale = Math.max(1, Math.min(5, newScale));

    setZoomLevel(clampedScale);

    const newPos = {
      x:
        -(mousePointTo.x - stage.getPointerPosition().x / clampedScale) *
        clampedScale,
      y:
        -(mousePointTo.y - stage.getPointerPosition().y / clampedScale) *
        clampedScale,
    };

    // Apply bounds checking
    const boundedPos = constrainPosition(newPos, clampedScale);
    setStagePosition(boundedPos);
  };

  // Constrain position to keep map visible
  const constrainPosition = (pos: { x: number; y: number }, scale: number) => {
    const stageWidth = 512;
    const stageHeight = 512;
    const mapWidth = stageWidth * scale;
    const mapHeight = stageHeight * scale;

    const maxX = 0;
    const minX = -(mapWidth - stageWidth);
    const maxY = 0;
    const minY = -(mapHeight - stageHeight);

    return {
      x: Math.max(minX, Math.min(maxX, pos.x)),
      y: Math.max(minY, Math.min(maxY, pos.y)),
    };
  };

  // Handle zoom change from slider
  const handleZoomChange = (newZoomLevel: number) => {
    setZoomLevel(newZoomLevel);
  };

  // Calculate max rounds from grenade positions
  const maxRounds = useMemo(() => {
    if (grenadePositions.length === 0) return 30; // Default to 30 rounds
    return Math.max(...grenadePositions.map(pos => pos.round_number || 0));
  }, [grenadePositions]);

  // Filter grenade positions based on selected round
  const filteredGrenadePositions = useMemo(() => {
    if (selectedRound === null) {
      return grenadePositions; // Show all rounds
    }
    return grenadePositions.filter(pos => pos.round_number === selectedRound);
  }, [grenadePositions, selectedRound]);

  return (
    <div className="flex flex-col items-start gap-4">
      <div className="flex items-start gap-4">
        {/* Zoom Slider */}
        <ZoomSlider
          zoomLevel={zoomLevel}
          onZoomChange={handleZoomChange}
          minZoom={1.0}
          maxZoom={5.0}
          height={490}
        />

        {/* Map Container */}
        <div
          className="border rounded-lg overflow-hidden"
          style={{ borderColor: 'oklch(1 0 0 / 0.1)' }}
        >
          <Stage
            width={512}
            height={512}
            scaleX={zoomLevel}
            scaleY={zoomLevel}
            x={stagePosition.x}
            y={stagePosition.y}
            onWheel={handleWheel}
            draggable={zoomLevel > 1.0}
            onDragEnd={e => {
              const stage = e.target;
              const boundedPos = constrainPosition(
                { x: stage.x(), y: stage.y() },
                zoomLevel
              );
              setStagePosition(boundedPos);
            }}
          >
            <Layer>
              {/* Map Image */}
              {image && <Image image={image} width={512} height={512} />}

              {/* Grenade Markers */}
              {filteredGrenadePositions.map((position, index) => {
                const pixelCoords = convertGameToPixelCoords(
                  position.x,
                  position.y
                );
                const isSelected = selectedMarkerIndex === index;
                const opacity = isSelected
                  ? 1.0
                  : selectedMarkerIndex !== null
                    ? 0.3
                    : 1.0;

                return (
                  <React.Fragment key={index}>
                    {/* Grenade Marker */}
                    <Circle
                      x={pixelCoords.x}
                      y={pixelCoords.y}
                      radius={8}
                      fill={getGrenadeColor(position.grenade_type || '')}
                      stroke="white"
                      strokeWidth={2}
                      opacity={opacity}
                      onClick={() => handleMarkerClick(index)}
                      onTap={() => handleMarkerClick(index)}
                    />

                    {/* Player Position Line and X (when selected) */}
                    {isSelected &&
                      position.player_x !== undefined &&
                      position.player_y !== undefined && (
                        <>
                          <Line
                            points={[
                              pixelCoords.x,
                              pixelCoords.y,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).x,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).y,
                            ]}
                            stroke="rgba(255, 255, 255, 0.6)"
                            strokeWidth={2}
                            dash={[5, 5]}
                          />
                          <Line
                            points={[
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).x - 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).y - 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).x + 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).y + 6,
                            ]}
                            stroke="rgba(255, 255, 255, 0.8)"
                            strokeWidth={3}
                          />
                          <Line
                            points={[
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).x - 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).y + 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).x + 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y
                              ).y - 6,
                            ]}
                            stroke="rgba(255, 255, 255, 0.8)"
                            strokeWidth={3}
                          />
                        </>
                      )}
                  </React.Fragment>
                );
              })}
            </Layer>
          </Stage>
        </div>
      </div>

      {/* Round Slider */}
      <div className="w-full flex justify-start ml-12">
        <RoundSlider
          selectedRound={selectedRound}
          onRoundChange={setSelectedRound}
          maxRounds={maxRounds}
          width={512}
        />
      </div>
    </div>
  );
};

export default MapVisualizationKonva;
