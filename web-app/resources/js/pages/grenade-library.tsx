import { useMemo, useState } from 'react';
import MapVisualizationKonva from '../components/map-visualization-konva';
import GrenadeFilters from '../components/grenade-filters';
import GrenadeList from '../components/grenade-list';
import { useGrenadeLibrary, GrenadeLibraryProvider, GrenadeData } from '../hooks/useGrenadeLibrary';

const GrenadeLibraryContent = () => {
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
    <div className="space-y-6">
      <div className="mt-4">
        <h1 className="text-3xl font-bold tracking-tight">Grenade Library</h1>
        <p className="text-muted-foreground">
          Discover grenades from your recent matches
        </p>
      </div>

      {error && (
        <div className="p-4 border border-red-200 rounded-lg bg-red-50 text-red-700">
          {error}
        </div>
      )}

      <GrenadeFilters />

      <div>
        {isLoading ? (
          <div className="flex items-center justify-center h-64 border rounded-lg bg-muted/50">
            <div className="text-muted-foreground">Loading grenades...</div>
          </div>
        ) : (
          <div className="flex gap-6 flex items-center justify-center">
            <MapVisualizationKonva
              mapName={currentMap}
              grenadePositions={grenadePositions}
              onGrenadeSelect={handleMapGrenadeSelect}
              selectedGrenadeId={selectedGrenadeId}
            />
            <GrenadeList
              onGrenadeClick={handleListGrenadeClick}
              selectedGrenadeId={selectedGrenadeId}
            />
          </div>
        )}
      </div>
    </div>
  );
};

const GrenadeLibrary = () => {
  return (
    <GrenadeLibraryProvider>
      <GrenadeLibraryContent />
    </GrenadeLibraryProvider>
  );
};

export default GrenadeLibrary;
