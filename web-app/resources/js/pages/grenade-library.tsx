import { useState, useEffect } from 'react';
import MapVisualization from '../components/map-visualization';
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
  const [hasInitialized, setHasInitialized] = useState(false);

  const { get } = useApi();

  // Load initial filter options and set defaults
  useEffect(() => {
    const loadInitialOptions = async () => {
      try {
        const response = await get('/grenade-library/filter-options');
        const data = response.data as FilterOptions;
        setFilterOptions(data);

        // Set default selections on initial load
        if (
          !hasInitialized &&
          data.maps.length > 0 &&
          data.grenadeTypes.length > 0
        ) {
          const firstMap = data.maps[0];
          const firstGrenadeType = data.grenadeTypes[0];

          setFilters(prev => ({
            ...prev,
            map: firstMap.name,
            grenadeType: firstGrenadeType.type,
          }));

          setHasInitialized(true);
        }
      } catch (error) {
        console.error('Failed to load initial filter options:', error);
      }
    };

    loadInitialOptions();
  }, [get, hasInitialized]);

  // Load matches when map changes
  useEffect(() => {
    const loadMatches = async () => {
      if (!filters.map) return;

      try {
        const params = new URLSearchParams();
        params.append('map', filters.map);

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
          setFilters(prev => ({
            ...prev,
            matchId: data.matches[0].id,
            roundNumber: 'all', // Reset to "All Rounds"
            playerSteamId: 'all', // Reset to "All Players"
          }));
        } else {
          // No matches for this map, reset match-dependent filters
          setFilters(prev => ({
            ...prev,
            matchId: '',
            roundNumber: 'all',
            playerSteamId: 'all',
          }));
        }
      } catch (error) {
        console.error('Failed to load matches:', error);
      }
    };

    loadMatches();
  }, [filters.map, get]);

  // Load rounds and players when match changes
  useEffect(() => {
    const loadMatchDependentOptions = async () => {
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
    filters.roundNumber,
    filters.playerSteamId,
    get,
  ]);

  // Load grenade data when filters change
  useEffect(() => {
    const loadGrenades = async () => {
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
  }, [filters, get]);

  const handleFilterChange = (filterName: string, value: string) => {
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

      return newFilters;
    });
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
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Grenade Library</h1>

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

      <div className="mb-6">
        <h2 className="text-lg font-semibold mb-4">
          {currentMap.replace('de_', '').toUpperCase()} - {grenades.length}{' '}
          Grenades
        </h2>
        <p className="text-sm text-gray-600 mb-2">
          Current map: {currentMap} | Grenades: {grenadePositions.length}
        </p>
        <MapVisualization
          mapName={currentMap}
          grenadePositions={grenadePositions}
        />
      </div>
    </div>
  );
};

export default GrenadeLibrary;
