import { useMemo, useState } from 'react';
import MapVisualizationKonva from '@/components/map-visualization-konva';
import MapVisualizationSkeleton from '@/components/map-visualization-skeleton';
import GrenadeFilters from '@/components/grenade-filters';
import GrenadeList from '@/components/grenade-list';
import GrenadeListSkeleton from '@/components/grenade-list-skeleton';
import {
  useGrenadeLibrary,
  GrenadeLibraryProvider,
  FavouritedGrenadeData,
} from '@/hooks/use-grenade-library';

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
}) => {
  const { grenades, isLoading, error, currentMap } = useGrenadeLibrary();

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
  const handleListGrenadeClick = (grenade: FavouritedGrenadeData) => {
    setSelectedGrenadeId(selectedGrenadeId === grenade.id ? null : grenade.id);
  };

  return (
    <div className={`space-y-6 ${className}`}>
      {showHeader && (
        <div className="mt-4">
          <h1 className="text-3xl font-bold tracking-tight">Grenade Library</h1>
          <p className="text-muted-foreground">
            Your collection of favourite grenades from all matches
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
        useFavouritesContext={true}
      />

      <div className="flex gap-6 items-start justify-center">
        {/* Map - Always visible, shows skeleton when loading */}
        {isLoading ? (
          <MapVisualizationSkeleton
            mapName={currentMap}
            onGrenadeSelect={handleMapGrenadeSelect}
            selectedGrenadeId={selectedGrenadeId}
            useFavouritesContext={true}
            hideRoundSlider={true}
          />
        ) : (
          <MapVisualizationKonva
            mapName={currentMap}
            grenadePositions={grenadePositions}
            onGrenadeSelect={handleMapGrenadeSelect}
            selectedGrenadeId={selectedGrenadeId}
            useFavouritesContext={true}
            hideRoundSlider={true}
          />
        )}

        {/* Grenade List - Shows skeleton when loading */}
        {isLoading ? (
          <GrenadeListSkeleton />
        ) : (
          <GrenadeList
            onGrenadeClick={handleListGrenadeClick}
            selectedGrenadeId={selectedGrenadeId}
            showFavourites={true} // Show unfavourite buttons for favourited grenades
            useFavouritesContext={true}
            hideRoundNumber={true}
          />
        )}
      </div>
    </div>
  );
};

const GrenadeLibraryView: React.FC<GrenadeLibraryViewProps> = props => {
  return (
    <GrenadeLibraryProvider initialFilters={props.initialFilters}>
      <GrenadeLibraryViewContent {...props} />
    </GrenadeLibraryProvider>
  );
};

export default GrenadeLibraryView;
