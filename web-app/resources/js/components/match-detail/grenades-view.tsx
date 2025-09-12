import { useMemo, useState } from 'react';
import MapVisualizationKonva from '@/components/map-visualization-konva';
import MapVisualizationSkeleton from '@/components/map-visualization-skeleton';
import GrenadeFilters from '@/components/grenade-filters';
import GrenadeList from '@/components/grenade-list';
import GrenadeListSkeleton from '@/components/grenade-list-skeleton';
import {
  useMatchGrenades,
  MatchGrenadesProvider,
  GrenadeData,
} from '@/hooks/use-match-grenades';

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

  // Convert grenade data to the format expected by MapVisualization
  const grenadePositions = useMemo(() => {
    return grenades.map(grenade => ({
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
  }, [grenades]);

  // Handle grenade selection from map
  const handleMapGrenadeSelect = (grenadeId: number | null) => {
    setSelectedGrenadeId(grenadeId);
  };

  // Handle grenade selection from list
  const handleListGrenadeClick = (grenade: GrenadeData) => {
    setSelectedGrenadeId(selectedGrenadeId === grenade.id ? null : grenade.id);
  };

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
        {/* Map - Always visible, shows skeleton when loading */}
        {isLoading ? (
          <MapVisualizationSkeleton
            mapName={currentMap}
            onGrenadeSelect={handleMapGrenadeSelect}
            selectedGrenadeId={selectedGrenadeId}
            useFavouritesContext={false}
          />
        ) : (
          <MapVisualizationKonva
            mapName={currentMap}
            grenadePositions={grenadePositions}
            onGrenadeSelect={handleMapGrenadeSelect}
            selectedGrenadeId={selectedGrenadeId}
            useFavouritesContext={false}
          />
        )}

        {/* Grenade List - Shows skeleton when loading */}
        {isLoading ? (
          <GrenadeListSkeleton />
        ) : (
          <GrenadeList
            onGrenadeClick={handleListGrenadeClick}
            selectedGrenadeId={selectedGrenadeId}
            showFavourites={showFavourites}
            useFavouritesContext={false}
          />
        )}
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
