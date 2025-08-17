import { useState, useEffect } from 'react';
import MapVisualizationKonva from '../components/map-visualization-konva';
import GrenadeFilters from '../components/grenade-filters';
import { useApi } from '../hooks/useApi';

interface GrenadeData {
  id: number;
  player_x: number;
  player_y: number;
  grenade_final_x: number;
  grenade_final_y: number;
  grenade_type: string;
  round_number: number;
  match_id: number;
  map: string;
  player_name: string;
  player_steam_id: string | null;
}

interface FilterOptions {
  maps: Array<{ name: string; displayName: string }>;
  matches: Array<{ id: string; name: string }>;
  rounds: Array<{ number: number }>;
  grenadeTypes: Array<{ type: string; displayName: string }>;
  players: Array<{ steam_id: string; name: string }>;
  playerSides: Array<{ side: string; displayName: string }>;
}

const GrenadeLibrary = () => {
  const [filters, setFilters] = useState({
    map: '',
    matchId: '',
    roundNumber: 'all', // Default to "All Rounds"
    grenadeType: '',
    playerSteamId: 'all', // Default to "All Players"
    playerSide: 'all', // Default to "All Sides"
  });

  const [grenades, setGrenades] = useState<GrenadeData[]>([]);
  const [filterOptions, setFilterOptions] = useState<FilterOptions>({
    maps: [],
    matches: [],
    rounds: [],
    grenadeTypes: [],
    players: [],
    playerSides: [],
  });
  const [isInitialized, setIsInitialized] = useState(false);

  const { get } = useApi();

  // Load initial filter options and set defaults
  useEffect(() => {
    // Only run this effect once on mount
    if (isInitialized) return;

    const loadInitialOptions = async () => {
      try {
        const response = await get('/grenade-library/filter-options');
        const data = response.data as FilterOptions;
        setFilterOptions(data);

        // Set default selections on initial load
        if (
          data.maps.length > 0 &&
          data.grenadeTypes.length > 0
        ) {
          const firstMap = data.maps[0];
          const firstGrenadeType = data.grenadeTypes[0];

          // Load matches for the first map first, then set all filters at once
          const loadMatchesForFirstMap = async () => {
            try {
              const params = new URLSearchParams();
              params.append('map', firstMap.name);

              const matchResponse = await get(
                `/grenade-library/filter-options?${params.toString()}`
              );
              const matchData = matchResponse.data as FilterOptions;

              setFilterOptions(prev => ({
                ...prev,
                matches: matchData.matches,
              }));

              // Set all filters at once to avoid multiple re-renders
              const initialFilters = {
                map: firstMap.name,
                grenadeType: firstGrenadeType.type,
                matchId: matchData.matches.length > 0 ? matchData.matches[0].id : '',
                roundNumber: 'all',
                playerSteamId: 'all',
                playerSide: 'all',
              };

              console.log('Setting initial filters:', initialFilters);
              setFilters(initialFilters);
              setIsInitialized(true);
            } catch (error) {
              console.error('Failed to load matches for first map:', error);

              // Fallback: set filters without match if loading fails
              setFilters(prev => ({
                ...prev,
                map: firstMap.name,
                grenadeType: firstGrenadeType.type,
              }));
              setIsInitialized(true);
            }
          };

          loadMatchesForFirstMap();
        }
      } catch (error) {
        console.error('Failed to load initial filter options:', error);
      }
    };

    loadInitialOptions();
  }, [get]);

  // Load matches when map changes (now handled in handleFilterChange)
  // This useEffect has been removed to avoid conflicts with the new logic

  // Load rounds and players when match changes
  useEffect(() => {
    const loadMatchDependentOptions = async () => {
      // Don't load match-dependent options until initialized
      if (!isInitialized) return;

      if (!filters.map || !filters.matchId) return;

      try {
        const params = new URLSearchParams();
        params.append('map', filters.map);
        params.append('match_id', filters.matchId);

        const response = await get(
          `/grenade-library/filter-options?${params.toString()}`
        );
        const data = response.data as FilterOptions;

        setFilterOptions(prev => ({
          ...prev,
          rounds: data.rounds,
          players: data.players,
        }));

        // Keep current round/player selections if they're still valid
        const currentRoundValid =
          data.rounds.some(r => r.number.toString() === filters.roundNumber) ||
          filters.roundNumber === 'all';
        const currentPlayerValid =
          data.players.some(p => p.steam_id === filters.playerSteamId) ||
          filters.playerSteamId === 'all';

        if (!currentRoundValid) {
          setFilters(prev => ({ ...prev, roundNumber: 'all' }));
        }
        if (!currentPlayerValid) {
          setFilters(prev => ({ ...prev, playerSteamId: 'all' }));
        }
      } catch (error) {
        console.error('Failed to load match-dependent options:', error);
      }
    };

    loadMatchDependentOptions();
  }, [
    filters.map,
    filters.matchId,
    get,
    isInitialized,
  ]);

  // Load grenade data when filters change
  useEffect(() => {
    const loadGrenades = async () => {
      // Don't load grenades until initialized
      if (!isInitialized) {
        console.log('Skipping grenade load until initialized');
        return;
      }

      // Only load grenades if we have the required filters
      if (!filters.map || !filters.matchId || !filters.grenadeType) return;

      try {
        const params = new URLSearchParams();
        params.append('map', filters.map);
        params.append('match_id', filters.matchId);
        if (filters.roundNumber && filters.roundNumber !== 'all') {
          params.append('round_number', filters.roundNumber);
        }
        params.append('grenade_type', filters.grenadeType);
        if (filters.playerSteamId && filters.playerSteamId !== 'all') {
          params.append('player_steam_id', filters.playerSteamId);
        }
        if (filters.playerSide && filters.playerSide !== 'all') {
          params.append('player_side', filters.playerSide);
        }

        console.log('Loading grenades with params:', params.toString());
        const response = await get(`/grenade-library?${params.toString()}`);
        console.log('Grenades response:', response.data);
        setGrenades((response.data as { grenades: GrenadeData[] }).grenades);
      } catch (error) {
        console.error('Failed to load grenades:', error);
      }
    };

    loadGrenades();
  }, [filters, get, isInitialized]);

  const handleFilterChange = (filterName: string, value: string) => {
    console.log(`Filter change: ${filterName} = ${value}`);

    // Mark that initial loading is complete when user manually changes filters
    if (!isInitialized) {
      setIsInitialized(true);
    }

    // Clear grenades immediately when map changes to prevent showing old grenades on new map
    if (filterName === 'map') {
      setGrenades([]);
    }

    setFilters(prev => {
      const newFilters = { ...prev, [filterName]: value };

      // Implement filter hierarchy dependencies
      if (filterName === 'map') {
        // Map changed: reset match, round, and player filters
        newFilters.matchId = '';
        newFilters.roundNumber = 'all';
        newFilters.playerSteamId = 'all';
        newFilters.playerSide = 'all';
      } else if (filterName === 'matchId') {
        // Match changed: reset round and player filters
        newFilters.roundNumber = 'all';
        newFilters.playerSteamId = 'all';
        newFilters.playerSide = 'all';
      }

      console.log('New filters:', newFilters);
      return newFilters;
    });

    // Handle map change with auto-selection of first match
    if (filterName === 'map') {
      // Load matches for the new map and auto-select the first one
      const loadMatchesForMap = async () => {
        try {
          const params = new URLSearchParams();
          params.append('map', value);

          const response = await get(
            `/grenade-library/filter-options?${params.toString()}`
          );
          const data = response.data as FilterOptions;

          setFilterOptions(prev => ({
            ...prev,
            matches: data.matches,
            rounds: [], // Reset rounds when map changes
            players: [], // Reset players when map changes
          }));

          // Auto-select first match when map changes
          if (data.matches.length > 0) {
            console.log(`Auto-selecting first match: ${data.matches[0].id} for map: ${value}`);
            setFilters(prev => ({
              ...prev,
              matchId: data.matches[0].id,
              roundNumber: 'all', // Reset to "All Rounds"
              playerSteamId: 'all', // Reset to "All Players"
            }));
          } else {
            // No matches for this map, reset match-dependent filters
            console.log(`No matches found for map: ${value}`);
            setFilters(prev => ({
              ...prev,
              matchId: '',
              roundNumber: 'all',
              playerSteamId: 'all',
            }));
          }
        } catch (error) {
          console.error('Failed to load matches for map:', error);
        }
      };

      loadMatchesForMap();
    }
  };

  // Get the current map from filters
  const currentMap = filters.map || 'de_ancient';

  // Convert grenade data to the format expected by MapVisualization
  const grenadePositions = grenades.map(grenade => ({
    x: grenade.grenade_final_x,
    y: grenade.grenade_final_y,
    grenade_type: grenade.grenade_type,
    player_name: grenade.player_name,
    round_number: grenade.round_number,
    player_x: grenade.player_x,
    player_y: grenade.player_y,
  }));

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Grenade Library</h1>
        <p className="text-muted-foreground">
          Discover grenades from your recent matches
        </p>
      </div>

      <GrenadeFilters
        filters={filters}
        onFilterChange={handleFilterChange}
        maps={filterOptions.maps}
        matches={filterOptions.matches}
        rounds={filterOptions.rounds}
        grenadeTypes={filterOptions.grenadeTypes}
        players={filterOptions.players}
        playerSides={filterOptions.playerSides}
      />

      <div className="mt-6">
        <MapVisualizationKonva
          mapName={currentMap}
          grenadePositions={grenadePositions}
        />
      </div>
    </div>
  );
};

export default GrenadeLibrary;
