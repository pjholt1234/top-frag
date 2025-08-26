import React, { useState } from 'react';
import { Stage, Layer, Image } from 'react-konva';
import { getMapMetadata } from '../config/maps';
import ZoomSlider from './zoom-slider';
import RoundSlider from './round-slider';
import { useGrenadeLibrary } from '../hooks/useGrenadeLibrary';
import { useMatchGrenades } from '../hooks/useMatchGrenades';

interface MapVisualizationSkeletonProps {
  mapName: string;
  onGrenadeSelect?: (grenadeId: number | null) => void;
  selectedGrenadeId?: number | null;
  useFavouritesContext?: boolean;
  hideRoundSlider?: boolean;
}

const MapVisualizationSkeleton: React.FC<MapVisualizationSkeletonProps> = ({
  mapName,
  onGrenadeSelect: _onGrenadeSelect,
  selectedGrenadeId: _selectedGrenadeId,
  useFavouritesContext = false,
  hideRoundSlider = false,
}) => {
  const [zoomLevel, setZoomLevel] = useState(1.0);
  const [stagePosition, setStagePosition] = useState({ x: 0, y: 0 });
  const [image, setImage] = useState<HTMLImageElement | null>(null);
  const [imageLoading, setImageLoading] = useState(false);

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

  // Load map image
  React.useEffect(() => {
    setImageLoading(true);
    const img = new window.Image();
    img.src = mapMetadata?.imagePath || `/images/maps/${mapName}.png`;
    img.onload = () => {
      setImage(img);
      setImageLoading(false);
    };
    img.onerror = () => {
      setImageLoading(false);
      console.error(`Failed to load map image: ${mapName}`);
    };
  }, [mapName, mapMetadata]);

  // Reset position when zoom level changes to 1.0
  React.useEffect(() => {
    if (zoomLevel === 1.0) {
      setStagePosition({ x: 0, y: 0 });
    }
  }, [zoomLevel]);

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

  // Handle round change from slider
  const handleRoundChange = (round: number | null) => {
    const roundValue = round === null ? 'all' : round.toString();
    setFilter('roundNumber', roundValue);
  };

  // Calculate max rounds from available rounds
  const maxRounds = React.useMemo(() => {
    if (filterOptions.rounds.length === 0) return 30; // Default fallback
    return Math.max(...filterOptions.rounds.map(r => r.number));
  }, [filterOptions.rounds]);

  // Convert round filter to number for slider
  const selectedRoundForSlider = React.useMemo(() => {
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
          className="border rounded-lg overflow-hidden relative"
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
            </Layer>
          </Stage>

          {/* Loading Overlay */}
          <div className="absolute inset-0 bg-black/20 flex items-center justify-center">
            <div className="bg-background/90 backdrop-blur-sm rounded-lg px-4 py-2 flex items-center gap-2">
              <div className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
              <span className="text-sm text-foreground">
                {imageLoading ? 'Loading map...' : 'Loading grenades...'}
              </span>
            </div>
          </div>
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

export default MapVisualizationSkeleton;
