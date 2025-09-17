import { useMemo, useState, Suspense, lazy, useCallback } from 'react';
import MapVisualizationSkeleton from '@/components/map-visualization-skeleton';
import GrenadeFilters from '@/components/grenade-filters';
import GrenadeListSkeleton from '@/components/grenade-list-skeleton';
import {
  useMatchGrenades,
  MatchGrenadesProvider,
  GrenadeData,
} from '@/hooks/use-match-grenades';

// Lazy load heavy components for better performance
const MapVisualizationKonva = lazy(
  () => import('@/components/map-visualization-konva')
);
const GrenadeList = lazy(() => import('@/components/grenade-list'));

interface MatchGrenadesViewProps {
  matchId: string;
  hideMapAndMatchFilters?: boolean;
  showHeader?: boolean;
  showFavourites?: boolean;
  className?: string;
  initialFilters?: {
    map?: string;
    roundNumber?: string;
    grenadeType?: string;
    playerSteamId?: string;
    playerSide?: string;
  };
}

const MatchGrenadesViewContent: React.FC<MatchGrenadesViewProps> = ({
  hideMapAndMatchFilters = false,
  showHeader = true,
  showFavourites = false,
  className = '',
}) => {
  const { grenades, isLoading, error, currentMap } = useMatchGrenades();

  const [selectedGrenadeId, setSelectedGrenadeId] = useState<number | null>(
    null
  );

  // Optimize grenade positions transformation with better memoization
  const grenadePositions = useMemo(() => {
    if (!grenades.length) return [];

    const positions = grenades.map(grenade => ({
      x: grenade.grenade_final_x,
      y: grenade.grenade_final_y,
      z: grenade.grenade_final_z,
      grenade_type: grenade.grenade_type,
      player_name: grenade.player_name,
      round_number: grenade.round_number,
      player_x: grenade.player_x,
      player_y: grenade.player_y,
      player_z: grenade.player_z,
      id: grenade.id,
    }));

    return positions;
  }, [grenades]);

  // Memoize event handlers to prevent unnecessary re-renders
  const handleMapGrenadeSelect = useCallback((grenadeId: number | null) => {
    setSelectedGrenadeId(grenadeId);
  }, []);

  const handleListGrenadeClick = useCallback((grenade: GrenadeData) => {
    setSelectedGrenadeId(prev => (prev === grenade.id ? null : grenade.id));
  }, []);

  // Loading fallback components
  const MapFallback = () => (
    <MapVisualizationSkeleton
      mapName={currentMap}
      onGrenadeSelect={handleMapGrenadeSelect}
      selectedGrenadeId={selectedGrenadeId}
      useFavouritesContext={false}
    />
  );

  const ListFallback = () => <GrenadeListSkeleton />;

  return (
    <div className={`space-y-6 ${className}`}>
      {showHeader && (
        <div className="mt-4">
          <h1 className="text-3xl font-bold tracking-tight">Match Grenades</h1>
          <p className="text-muted-foreground">
            Discover grenades from your recent matches
          </p>
        </div>
      )}

      {error && (
        <div className="p-4 border border-red-200 rounded-lg bg-red-50 text-red-700">
          {error}
        </div>
      )}

      <GrenadeFilters
        hideMapAndMatchFilters={hideMapAndMatchFilters}
        useFavouritesContext={false}
      />

      <div className="flex gap-6 items-start justify-center">
        {/* Map - Progressive loading with Suspense */}
        <Suspense fallback={<MapFallback />}>
          {isLoading ? (
            <MapFallback />
          ) : (
            <MapVisualizationKonva
              mapName={currentMap}
              grenadePositions={grenadePositions}
              onGrenadeSelect={handleMapGrenadeSelect}
              selectedGrenadeId={selectedGrenadeId}
              useFavouritesContext={false}
            />
          )}
        </Suspense>

        {/* Grenade List - Progressive loading with Suspense */}
        <Suspense fallback={<ListFallback />}>
          {isLoading ? (
            <ListFallback />
          ) : (
            <GrenadeList
              onGrenadeClick={handleListGrenadeClick}
              selectedGrenadeId={selectedGrenadeId}
              showFavourites={showFavourites}
              useFavouritesContext={false}
            />
          )}
        </Suspense>
      </div>
    </div>
  );
};

const MatchGrenadesView: React.FC<MatchGrenadesViewProps> = props => {
  return (
    <MatchGrenadesProvider
      matchId={props.matchId}
      initialFilters={props.initialFilters}
    >
      <MatchGrenadesViewContent {...props} />
    </MatchGrenadesProvider>
  );
};

export default MatchGrenadesView;
