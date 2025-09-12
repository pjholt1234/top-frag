import {
  useState,
  useEffect,
  useCallback,
  useMemo,
  createContext,
  useContext,
  ReactNode,
} from 'react';
import { useApi } from './use-api';

// Types
export interface GrenadeData {
  id: number;
  player_x: number;
  player_y: number;
  player_z?: number;
  player_aim_x?: number;
  player_aim_y?: number;
  player_aim_z?: number;
  grenade_final_x: number;
  grenade_final_y: number;
  grenade_final_z?: number;
  grenade_type: string;
  round_number: number;
  round_time?: number;
  tick_timestamp?: number;
  match_id: number;
  map: string;
  player_name: string;
  player_steam_id: string | null;
  player_side?: string;
  throw_type?: string;
  damage_dealt?: number;
  friendly_players_affected?: number;
  enemy_players_affected?: number;
  enemy_flash_duration?: number;
  friendly_flash_duration?: number;
  flash_duration?: number;
  effectiveness_rating?: number;
  created_at: string;
  updated_at: string;
}

export interface FilterOptions {
  maps: Array<{ name: string; displayName: string }>;
  rounds: Array<{ number: number }>;
  grenadeTypes: Array<{ type: string; displayName: string }>;
  players: Array<{ steam_id: string; name: string }>;
  playerSides: Array<{ side: string; displayName: string }>;
}

export interface GrenadeFilters {
  map: string;
  roundNumber: string;
  grenadeType: string;
  playerSteamId: string;
  playerSide: string;
}

export interface GrenadeState {
  id: number;
  isSelected: boolean;
  isHighlighted: boolean;
}

export interface UseMatchGrenadesReturn {
  // Data
  grenades: GrenadeData[];
  filterOptions: FilterOptions;

  // Filters
  filters: GrenadeFilters;
  setFilter: (filterName: keyof GrenadeFilters, value: string) => void;
  setFilters: (filters: Partial<GrenadeFilters>) => void;
  resetFilters: () => void;

  // State
  isLoading: boolean;
  isInitialized: boolean;
  error: string | null;

  // Actions
  refreshData: () => Promise<void>;
  loadFilterOptions: () => Promise<void>;

  // Grenade selection state
  grenadeStates: Map<number, GrenadeState>;
  selectGrenade: (grenadeId: number, selected?: boolean) => void;
  selectGrenades: (grenadeIds: number[], selected?: boolean) => void;
  clearSelection: () => void;
  getSelectedGrenades: () => GrenadeData[];

  // Computed values
  selectedGrenadeCount: number;
  hasValidFilters: boolean;
  currentMap: string;
}

const DEFAULT_FILTERS: GrenadeFilters = {
  map: '',
  roundNumber: 'all',
  grenadeType: '',
  playerSteamId: 'all',
  playerSide: 'all',
};

const DEFAULT_FILTER_OPTIONS: FilterOptions = {
  maps: [],
  rounds: [],
  grenadeTypes: [],
  players: [],
  playerSides: [],
};

// Context
const MatchGrenadesContext = createContext<UseMatchGrenadesReturn | null>(null);

// Provider component
interface MatchGrenadesProviderProps {
  children: ReactNode;
  matchId: string;
  initialFilters?: Partial<GrenadeFilters>;
}

export const MatchGrenadesProvider: React.FC<MatchGrenadesProviderProps> = ({
  children,
  matchId,
  initialFilters = {},
}) => {
  const { get } = useApi();

  // State
  const [grenades, setGrenades] = useState<GrenadeData[]>([]);
  const [filterOptions, setFilterOptions] = useState<FilterOptions>(
    DEFAULT_FILTER_OPTIONS
  );
  const [filters, setFiltersState] = useState<GrenadeFilters>({
    ...DEFAULT_FILTERS,
    ...initialFilters,
  });
  const [isLoading, setIsLoading] = useState(false);
  const [isInitialized, setIsInitialized] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [grenadeStates, setGrenadeStates] = useState<Map<number, GrenadeState>>(
    new Map()
  );

  // Computed values
  const hasValidFilters = useMemo(() => {
    return !!filters.map;
  }, [filters.map]);

  const currentMap = useMemo(() => {
    return filters.map || 'de_ancient';
  }, [filters.map]);

  const selectedGrenadeCount = useMemo(() => {
    return Array.from(grenadeStates.values()).filter(state => state.isSelected)
      .length;
  }, [grenadeStates]);

  // Load initial filter options
  const loadFilterOptions = useCallback(async () => {
    try {
      setError(null);
      const response = await get<FilterOptions>(
        `/matches/${matchId}/grenade-explorer/filter-options`
      );
      const data = response.data;
      setFilterOptions(data);
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to load filter options';
      setError(errorMessage);
      console.error('Failed to load filter options:', err);
    }
  }, [get]);

  // Initialize with default selections
  const initializeWithDefaults = useCallback(async () => {
    if (isInitialized) return;

    try {
      const response = await get<FilterOptions>(
        `/matches/${matchId}/grenade-explorer/filter-options`
      );
      const data = response.data;

      if (data.maps.length > 0 && data.grenadeTypes.length > 0) {
        const firstMap = data.maps[0];
        const firstGrenadeType = data.grenadeTypes[0];

        // Check if we have initial filters that should override defaults
        const currentFilters = { ...DEFAULT_FILTERS, ...initialFilters };
        const hasInitialFilters = currentFilters.map;

        if (hasInitialFilters) {
          // Set grenade type if not already set
          if (!currentFilters.grenadeType) {
            setFiltersState(prev => ({
              ...prev,
              grenadeType: firstGrenadeType.type,
            }));
          }
        } else {
          // No initial filters, use defaults
          const defaultFilters: GrenadeFilters = {
            map: firstMap.name,
            grenadeType: firstGrenadeType.type,
            roundNumber: 'all',
            playerSteamId: 'all',
            playerSide: 'all',
          };

          setFiltersState(defaultFilters);
        }

        setIsInitialized(true);
      }
    } catch (err) {
      const errorMessage =
        err instanceof Error
          ? err.message
          : 'Failed to initialize with defaults';
      setError(errorMessage);
      console.error('Failed to initialize with defaults:', err);
    }
  }, [get, isInitialized, initialFilters]);

  // Load match-dependent options (rounds and players) - optimized with memoization
  const loadMatchDependentOptions = useCallback(async () => {
    if (!isInitialized || !filters.map || !matchId) return;

    try {
      setError(null);
      const response = await get<FilterOptions>(
        `/matches/${matchId}/grenade-explorer/filter-options`,
        {
          params: {
            map: filters.map,
          },
        }
      );
      const data = response.data;

      // Only update if data has actually changed to prevent unnecessary re-renders
      setFilterOptions(prev => {
        const hasChanges =
          JSON.stringify(prev.rounds) !== JSON.stringify(data.rounds) ||
          JSON.stringify(prev.players) !== JSON.stringify(data.players);

        if (!hasChanges) return prev;

        return {
          ...prev,
          rounds: data.rounds,
          players: data.players,
        };
      });
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to load match options';
      setError(errorMessage);
      console.error('Failed to load match-dependent options:', err);
    }
  }, [get, isInitialized, filters.map, matchId]);

  // Load grenade data - optimized with better state management
  const loadGrenades = useCallback(async () => {
    if (!isInitialized || !hasValidFilters) {
      return;
    }

    try {
      setIsLoading(true);
      setError(null);

      const params: Record<string, string> = {
        map: filters.map,
      };

      // Only add grenade_type if it's set
      if (filters.grenadeType) {
        params.grenade_type = filters.grenadeType;
      }

      if (filters.roundNumber && filters.roundNumber !== 'all') {
        params.round_number = filters.roundNumber;
      }
      if (filters.playerSteamId && filters.playerSteamId !== 'all') {
        params.player_steam_id = filters.playerSteamId;
      }
      if (filters.playerSide && filters.playerSide !== 'all') {
        params.player_side = filters.playerSide;
      }

      const response = await get<{ grenades: GrenadeData[] }>(
        `/matches/${matchId}/grenade-explorer`,
        { params }
      );

      const newGrenades = response.data.grenades;
      setGrenades(newGrenades);

      // Optimize grenade states initialization - only add new grenades
      setGrenadeStates(prev => {
        const newStates = new Map(prev);
        const newGrenadeIds = new Set(newGrenades.map(g => g.id));

        // Remove states for grenades that are no longer in the list
        for (const [id] of newStates) {
          if (!newGrenadeIds.has(id)) {
            newStates.delete(id);
          }
        }

        // Add states for new grenades
        newGrenades.forEach(grenade => {
          if (!newStates.has(grenade.id)) {
            newStates.set(grenade.id, {
              id: grenade.id,
              isSelected: false,
              isHighlighted: false,
            });
          }
        });

        return newStates;
      });
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to load grenades';
      setError(errorMessage);
      console.error('Failed to load grenades:', err);
    } finally {
      setIsLoading(false);
    }
  }, [get, isInitialized, hasValidFilters, filters, matchId]);

  // Refresh all data
  const refreshData = useCallback(async () => {
    await Promise.all([
      loadFilterOptions(),
      loadMatchDependentOptions(),
      loadGrenades(),
    ]);
  }, [loadFilterOptions, loadMatchDependentOptions, loadGrenades]);

  // Filter management
  const setFilter = useCallback(
    (filterName: keyof GrenadeFilters, value: string) => {
      setFiltersState(prev => {
        const newFilters = { ...prev, [filterName]: value };

        // Handle filter dependencies
        if (filterName === 'map') {
          // Map changed: reset round and player filters
          newFilters.roundNumber = 'all';
          newFilters.playerSteamId = 'all';
          newFilters.playerSide = 'all';
          // Clear grenades immediately
          setGrenades([]);
        }

        return newFilters;
      });

      // Mark as initialized when user manually changes filters
      if (!isInitialized) {
        setIsInitialized(true);
      }
    },
    [isInitialized]
  );

  const setFilters = useCallback((newFilters: Partial<GrenadeFilters>) => {
    setFiltersState(prev => ({ ...prev, ...newFilters }));
  }, []);

  const resetFilters = useCallback(() => {
    setFiltersState(DEFAULT_FILTERS);
    setGrenades([]);
    setGrenadeStates(new Map());
  }, []);

  // Grenade selection management
  const selectGrenade = useCallback(
    (grenadeId: number, selected: boolean = true) => {
      setGrenadeStates(prev => {
        const newStates = new Map(prev);
        const currentState = newStates.get(grenadeId);
        if (currentState) {
          newStates.set(grenadeId, { ...currentState, isSelected: selected });
        }
        return newStates;
      });
    },
    []
  );

  const selectGrenades = useCallback(
    (grenadeIds: number[], selected: boolean = true) => {
      setGrenadeStates(prev => {
        const newStates = new Map(prev);
        grenadeIds.forEach(id => {
          const currentState = newStates.get(id);
          if (currentState) {
            newStates.set(id, { ...currentState, isSelected: selected });
          }
        });
        return newStates;
      });
    },
    []
  );

  const clearSelection = useCallback(() => {
    setGrenadeStates(prev => {
      const newStates = new Map(prev);
      newStates.forEach((state, id) => {
        newStates.set(id, { ...state, isSelected: false });
      });
      return newStates;
    });
  }, []);

  const getSelectedGrenades = useCallback(() => {
    return grenades.filter(grenade => {
      const state = grenadeStates.get(grenade.id);
      return state?.isSelected;
    });
  }, [grenades, grenadeStates]);

  // Optimized effects - reduce cascading API calls
  useEffect(() => {
    // Only load filter options once on mount
    if (!isInitialized) {
      loadFilterOptions();
    }
  }, [loadFilterOptions, isInitialized]);

  useEffect(() => {
    // Initialize with defaults only once
    if (!isInitialized) {
      initializeWithDefaults();
    }
  }, [initializeWithDefaults, isInitialized]);

  useEffect(() => {
    // Load match-dependent options only when map changes and we're initialized
    if (isInitialized && filters.map) {
      loadMatchDependentOptions();
    }
  }, [loadMatchDependentOptions, isInitialized, filters.map]);

  useEffect(() => {
    // Load grenades only when we have valid filters
    if (isInitialized && hasValidFilters) {
      loadGrenades();
    }
  }, [loadGrenades, isInitialized, hasValidFilters]);

  const contextValue: UseMatchGrenadesReturn = {
    // Data
    grenades,
    filterOptions,

    // Filters
    filters,
    setFilter,
    setFilters,
    resetFilters,

    // State
    isLoading,
    isInitialized,
    error,

    // Actions
    refreshData,
    loadFilterOptions,

    // Grenade selection state
    grenadeStates,
    selectGrenade,
    selectGrenades,
    clearSelection,
    getSelectedGrenades,

    // Computed values
    selectedGrenadeCount,
    hasValidFilters,
    currentMap,
  };

  return (
    <MatchGrenadesContext.Provider value={contextValue}>
      {children}
    </MatchGrenadesContext.Provider>
  );
};

// Hook to use the context
export const useMatchGrenades = (): UseMatchGrenadesReturn => {
  const context = useContext(MatchGrenadesContext);
  if (!context) {
    // Return a default context instead of throwing an error
    return {
      grenades: [],
      filterOptions: {
        maps: [],
        rounds: [],
        grenadeTypes: [],
        players: [],
        playerSides: [],
      },
      filters: {
        map: '',
        roundNumber: 'all',
        grenadeType: '',
        playerSteamId: 'all',
        playerSide: 'all',
      },
      setFilter: () => {},
      setFilters: () => {},
      resetFilters: () => {},
      isLoading: false,
      isInitialized: false,
      error: null,
      refreshData: async () => {},
      loadFilterOptions: async () => {},
      grenadeStates: new Map(),
      selectGrenade: () => {},
      selectGrenades: () => {},
      clearSelection: () => {},
      getSelectedGrenades: () => [],
      selectedGrenadeCount: 0,
      hasValidFilters: false,
      currentMap: '',
    };
  }
  return context;
};
