import React, { useState, useCallback, useMemo } from 'react';
import { Stage, Layer, Image, Circle, Line } from 'react-konva';
import { getMapMetadata } from '../config/maps';
import ZoomSlider from './zoom-slider';
import RoundSlider from './round-slider';
import { useGrenadeLibrary } from '../hooks/useGrenadeLibrary';
import { useMatchGrenades } from '../hooks/useMatchGrenades';

interface MapVisualizationProps {
  mapName: string;
  grenadePositions?: Array<{
    x: number;
    y: number;
    z?: number;
    grenade_type?: string;
    player_name?: string;
    round_number?: number;
    player_x?: number;
    player_y?: number;
    player_z?: number;
    id?: number;
  }>;
  onGrenadeSelect?: (grenadeId: number | null) => void;
  selectedGrenadeId?: number | null;
  useFavouritesContext?: boolean;
  hideRoundSlider?: boolean;
}

const MapVisualizationKonva: React.FC<MapVisualizationProps> = ({
  mapName,
  grenadePositions = [],
  onGrenadeSelect,
  selectedGrenadeId,
  useFavouritesContext = false,
  hideRoundSlider = false,
}) => {
  const [selectedMarkerIndex, setSelectedMarkerIndex] = useState<number | null>(
    null
  );
  const [zoomLevel, setZoomLevel] = useState(1.0);
  const [stagePosition, setStagePosition] = useState({ x: 0, y: 0 });
  const [image, setImage] = useState<HTMLImageElement | null>(null);

  // Call hooks unconditionally at the top level
  const favouritesContext = useGrenadeLibrary();
  const matchGrenadesContext = useMatchGrenades();

  // Determine which context to use based on the prop
  const context = useFavouritesContext
    ? favouritesContext
    : matchGrenadesContext;
  const { filters, setFilter, filterOptions } = context;

  // Get map metadata for coordinate conversion
  const mapMetadata = getMapMetadata(mapName);

  // Convert in-game coordinates to pixel coordinates
  const convertGameToPixelCoords = useCallback(
    (gameX: number, gameY: number, gameZ?: number) => {
      if (!mapMetadata) {
        console.error(`Map metadata not found for: ${mapName}`);
        return { x: 0, y: 0 };
      }

      // Apply base offset to get coordinates relative to radar origin
      let offsetX = gameX + mapMetadata.offset.x;
      let offsetY = gameY + mapMetadata.offset.y;

      // Apply floor-specific offset if Z coordinate is provided and map has multiple floors
      if (
        gameZ !== undefined &&
        mapMetadata.includesMultipleFloors &&
        mapMetadata.floors
      ) {
        for (const floor of mapMetadata.floors) {
          if (
            gameZ >= floor.heightBounds.min &&
            gameZ <= floor.heightBounds.max
          ) {
            // Apply floor-specific offset as percentage of the base offset
            offsetX += (mapMetadata.offset.x * floor.offset.x) / 100;
            offsetY += (mapMetadata.offset.y * floor.offset.y) / 100;
            break;
          }
        }
      }

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
    const newSelectedIndex = selectedMarkerIndex === index ? null : index;
    setSelectedMarkerIndex(newSelectedIndex);

    // Call the callback with the grenade ID
    if (onGrenadeSelect) {
      const grenadeId =
        newSelectedIndex !== null &&
        filteredGrenadePositions[newSelectedIndex]?.id
          ? filteredGrenadePositions[newSelectedIndex].id
          : null;
      onGrenadeSelect(grenadeId);
    }
  };

  // Handle stage wheel for zoom
  const handleWheel = (e: any) => {
    e.evt.preventDefault();

    const scaleBy = 1.02;
    const stage = e.target.getStage();
    const oldScale = stage.scaleX();
    const pointerPos = stage.getPointerPosition();

    if (!pointerPos) return;

    const mousePointTo = {
      x: pointerPos.x / oldScale - stage.x() / oldScale,
      y: pointerPos.y / oldScale - stage.y() / oldScale,
    };

    const newScale = e.evt.deltaY > 0 ? oldScale * scaleBy : oldScale / scaleBy;
    const clampedScale = Math.max(1, Math.min(5, newScale));

    setZoomLevel(clampedScale);

    const newPos = {
      x: -(mousePointTo.x - pointerPos.x / clampedScale) * clampedScale,
      y: -(mousePointTo.y - pointerPos.y / clampedScale) * clampedScale,
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

  // Filter grenade positions based on selected round
  const filteredGrenadePositions = useMemo(() => {
    if (filters.roundNumber === 'all') {
      return grenadePositions; // Show all rounds
    }
    const selectedRound = parseInt(filters.roundNumber);
    return grenadePositions.filter(pos => pos.round_number === selectedRound);
  }, [grenadePositions, filters.roundNumber]);

  // Update selected marker index when selectedGrenadeId changes from external source
  React.useEffect(() => {
    if (selectedGrenadeId !== null) {
      const index = filteredGrenadePositions.findIndex(
        pos => pos.id === selectedGrenadeId
      );
      if (index !== -1) {
        setSelectedMarkerIndex(index);
      }
    } else {
      setSelectedMarkerIndex(null);
    }
  }, [selectedGrenadeId, filteredGrenadePositions]);

  // Handle round change from slider
  const handleRoundChange = useCallback(
    (round: number | null) => {
      const roundValue = round === null ? 'all' : round.toString();
      setFilter('roundNumber', roundValue);
    },
    [setFilter]
  );

  // Calculate max rounds from available rounds
  const maxRounds = useMemo(() => {
    if (filterOptions.rounds.length === 0) return 30; // Default fallback
    return Math.max(...filterOptions.rounds.map(r => r.number));
  }, [filterOptions.rounds]);

  // Convert round filter to number for slider
  const selectedRoundForSlider = useMemo(() => {
    if (filters.roundNumber === 'all') return null;
    return parseInt(filters.roundNumber);
  }, [filters.roundNumber]);

  return (
    <div className="flex flex-col items-start">
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
                  position.y,
                  position.z
                );
                const isSelected =
                  selectedMarkerIndex === index ||
                  selectedGrenadeId === position.id;
                const opacity = isSelected
                  ? 1.0
                  : selectedMarkerIndex !== null || selectedGrenadeId !== null
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
                                position.player_y,
                                position.player_z
                              ).x,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y,
                                position.player_z
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
                                position.player_y,
                                position.player_z
                              ).x - 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y,
                                position.player_z
                              ).y - 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y,
                                position.player_z
                              ).x + 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y,
                                position.player_z
                              ).y + 6,
                            ]}
                            stroke="rgba(255, 255, 255, 0.8)"
                            strokeWidth={3}
                          />
                          <Line
                            points={[
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y,
                                position.player_z
                              ).x - 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y,
                                position.player_z
                              ).y + 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y,
                                position.player_z
                              ).x + 6,
                              convertGameToPixelCoords(
                                position.player_x,
                                position.player_y,
                                position.player_z
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
      {!hideRoundSlider && (
        <div className="w-full flex justify-start ml-12">
          <RoundSlider
            selectedRound={selectedRoundForSlider}
            onRoundChange={handleRoundChange}
            maxRounds={maxRounds}
            width={512}
          />
        </div>
      )}
    </div>
  );
};

export default MapVisualizationKonva;
