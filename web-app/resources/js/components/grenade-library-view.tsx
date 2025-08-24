import { useMemo, useState } from 'react';
import MapVisualizationKonva from './map-visualization-konva';
import MapVisualizationSkeleton from './map-visualization-skeleton';
import GrenadeFilters from './grenade-filters';
import GrenadeList from './grenade-list';
import GrenadeListSkeleton from './grenade-list-skeleton';
import { useGrenadeLibrary, GrenadeLibraryProvider, GrenadeData } from '../hooks/useGrenadeLibrary';

interface GrenadeLibraryViewProps {
  hideMapAndMatchFilters?: boolean;
  showHeader?: boolean;
  className?: string;
  initialFilters?: {
    map?: string;
    matchId?: string;
    roundNumber?: string;
    grenadeType?: string;
    playerSteamId?: string;
    playerSide?: string;
  };
}



const GrenadeLibraryViewContent: React.FC<GrenadeLibraryViewProps> = ({
  hideMapAndMatchFilters = false,
  showHeader = true,
  className = '',
  initialFilters = {},
}) => {
  const {
    grenades,
    isLoading,
    error,
    currentMap,
  } = useGrenadeLibrary();

  const [selectedGrenadeId, setSelectedGrenadeId] = useState<number | null>(null);

  // Convert grenade data to the format expected by MapVisualization
  const grenadePositions = useMemo(() => {
    return grenades.map(grenade => ({
      x: grenade.grenade_final_x,
      y: grenade.grenade_final_y,
      grenade_type: grenade.grenade_type,
      player_name: grenade.player_name,
      round_number: grenade.round_number,
      player_x: grenade.player_x,
      player_y: grenade.player_y,
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
          <h1 className="text-3xl font-bold tracking-tight">Grenade Library</h1>
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

      <GrenadeFilters hideMapAndMatchFilters={hideMapAndMatchFilters} />

      <div className="flex gap-6 items-start justify-center">
        {/* Map - Always visible, shows skeleton when loading */}
        {isLoading ? (
          <MapVisualizationSkeleton
            mapName={currentMap}
            onGrenadeSelect={handleMapGrenadeSelect}
            selectedGrenadeId={selectedGrenadeId}
          />
        ) : (
          <MapVisualizationKonva
            mapName={currentMap}
            grenadePositions={grenadePositions}
            onGrenadeSelect={handleMapGrenadeSelect}
            selectedGrenadeId={selectedGrenadeId}
          />
        )}

        {/* Grenade List - Shows skeleton when loading */}
        {isLoading ? (
          <GrenadeListSkeleton />
        ) : (
          <GrenadeList
            onGrenadeClick={handleListGrenadeClick}
            selectedGrenadeId={selectedGrenadeId}
          />
        )}
      </div>
    </div>
  );
};

const GrenadeLibraryView: React.FC<GrenadeLibraryViewProps> = (props) => {
  return (
    <GrenadeLibraryProvider initialFilters={props.initialFilters}>
      <GrenadeLibraryViewContent {...props} />
    </GrenadeLibraryProvider>
  );
};

export default GrenadeLibraryView;
