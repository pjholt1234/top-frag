import { useState, useEffect, useCallback, useMemo, createContext, useContext, ReactNode } from 'react';
import { useApi } from './useApi';

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
}

export interface FilterOptions {
    maps: Array<{ name: string; displayName: string }>;
    matches: Array<{ id: string; name: string }>;
    rounds: Array<{ number: number }>;
    grenadeTypes: Array<{ type: string; displayName: string }>;
    players: Array<{ steam_id: string; name: string }>;
    playerSides: Array<{ side: string; displayName: string }>;
}

export interface GrenadeFilters {
    map: string;
    matchId: string;
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

export interface UseGrenadeLibraryReturn {
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
    matchId: '',
    roundNumber: 'all',
    grenadeType: '',
    playerSteamId: 'all',
    playerSide: 'all',
};

const DEFAULT_FILTER_OPTIONS: FilterOptions = {
    maps: [],
    matches: [],
    rounds: [],
    grenadeTypes: [],
    players: [],
    playerSides: [],
};

// Context
const GrenadeLibraryContext = createContext<UseGrenadeLibraryReturn | null>(null);

// Provider component
interface GrenadeLibraryProviderProps {
    children: ReactNode;
}

export const GrenadeLibraryProvider: React.FC<GrenadeLibraryProviderProps> = ({ children }) => {
    const { get } = useApi();

    // State
    const [grenades, setGrenades] = useState<GrenadeData[]>([]);
    const [filterOptions, setFilterOptions] = useState<FilterOptions>(DEFAULT_FILTER_OPTIONS);
    const [filters, setFiltersState] = useState<GrenadeFilters>(DEFAULT_FILTERS);
    const [isLoading, setIsLoading] = useState(false);
    const [isInitialized, setIsInitialized] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [grenadeStates, setGrenadeStates] = useState<Map<number, GrenadeState>>(new Map());

    // Computed values
    const hasValidFilters = useMemo(() => {
        return !!(filters.map && filters.matchId && filters.grenadeType);
    }, [filters.map, filters.matchId, filters.grenadeType]);

    const currentMap = useMemo(() => {
        return filters.map || 'de_ancient';
    }, [filters.map]);

    const selectedGrenadeCount = useMemo(() => {
        return Array.from(grenadeStates.values()).filter(state => state.isSelected).length;
    }, [grenadeStates]);

    // Load initial filter options
    const loadFilterOptions = useCallback(async () => {
        try {
            setError(null);
            const response = await get<FilterOptions>('/grenade-library/filter-options');
            const data = response.data;
            setFilterOptions(data);
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to load filter options';
            setError(errorMessage);
            console.error('Failed to load filter options:', err);
        }
    }, [get]);

    // Initialize with default selections
    const initializeWithDefaults = useCallback(async () => {
        if (isInitialized) return;

        try {
            const response = await get<FilterOptions>('/grenade-library/filter-options');
            const data = response.data;

            if (data.maps.length > 0 && data.grenadeTypes.length > 0) {
                const firstMap = data.maps[0];
                const firstGrenadeType = data.grenadeTypes[0];

                // Load matches for the first map
                const matchResponse = await get<FilterOptions>('/grenade-library/filter-options', {
                    params: { map: firstMap.name }
                });
                const matchData = matchResponse.data;

                // Update filter options with matches FIRST
                setFilterOptions(prev => ({
                    ...prev,
                    matches: matchData.matches,
                }));

                // Small delay to ensure state update is processed
                await new Promise(resolve => setTimeout(resolve, 0));

                // Then set initial filters
                const initialFilters: GrenadeFilters = {
                    map: firstMap.name,
                    grenadeType: firstGrenadeType.type,
                    matchId: matchData.matches.length > 0 ? matchData.matches[0].id : '',
                    roundNumber: 'all',
                    playerSteamId: 'all',
                    playerSide: 'all',
                };

                setFiltersState(initialFilters);
                setIsInitialized(true);
            }
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to initialize with defaults';
            setError(errorMessage);
            console.error('Failed to initialize with defaults:', err);
        }
    }, [get, isInitialized]);

    // Load match-dependent options (rounds and players)
    const loadMatchDependentOptions = useCallback(async () => {
        if (!isInitialized || !filters.map || !filters.matchId) return;

        try {
            setError(null);
            const response = await get<FilterOptions>('/grenade-library/filter-options', {
                params: {
                    map: filters.map,
                    match_id: filters.matchId,
                }
            });
            const data = response.data;

            setFilterOptions(prev => ({
                ...prev,
                rounds: data.rounds,
                players: data.players,
            }));
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to load match options';
            setError(errorMessage);
            console.error('Failed to load match-dependent options:', err);
        }
    }, [get, isInitialized, filters.map, filters.matchId]);

    // Load grenade data
    const loadGrenades = useCallback(async () => {
        if (!isInitialized || !hasValidFilters) {
            return;
        }

        try {
            setIsLoading(true);
            setError(null);

            const params: Record<string, string> = {
                map: filters.map,
                match_id: filters.matchId,
                grenade_type: filters.grenadeType,
            };

            if (filters.roundNumber && filters.roundNumber !== 'all') {
                params.round_number = filters.roundNumber;
            }
            if (filters.playerSteamId && filters.playerSteamId !== 'all') {
                params.player_steam_id = filters.playerSteamId;
            }
            if (filters.playerSide && filters.playerSide !== 'all') {
                params.player_side = filters.playerSide;
            }

            const response = await get<{ grenades: GrenadeData[] }>('/grenade-library', { params });
            setGrenades(response.data.grenades);

            // Initialize grenade states for new grenades
            setGrenadeStates(prev => {
                const newStates = new Map(prev);
                response.data.grenades.forEach(grenade => {
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
            const errorMessage = err instanceof Error ? err.message : 'Failed to load grenades';
            setError(errorMessage);
            console.error('Failed to load grenades:', err);
        } finally {
            setIsLoading(false);
        }
    }, [get, isInitialized, hasValidFilters, filters]);

    // Refresh all data
    const refreshData = useCallback(async () => {
        await Promise.all([
            loadFilterOptions(),
            loadMatchDependentOptions(),
            loadGrenades(),
        ]);
    }, [loadFilterOptions, loadMatchDependentOptions, loadGrenades]);

    // Filter management
    const setFilter = useCallback((filterName: keyof GrenadeFilters, value: string) => {
        setFiltersState(prev => {
            const newFilters = { ...prev, [filterName]: value };

            // Handle filter dependencies
            if (filterName === 'map') {
                // Map changed: reset match, round, and player filters
                newFilters.matchId = '';
                newFilters.roundNumber = 'all';
                newFilters.playerSteamId = 'all';
                newFilters.playerSide = 'all';
                // Clear grenades immediately
                setGrenades([]);
            } else if (filterName === 'matchId') {
                // Match changed: reset round and player filters
                newFilters.roundNumber = 'all';
                newFilters.playerSteamId = 'all';
                newFilters.playerSide = 'all';
            }

            return newFilters;
        });

        // Mark as initialized when user manually changes filters
        if (!isInitialized) {
            setIsInitialized(true);
        }
    }, [isInitialized]);

    const setFilters = useCallback((newFilters: Partial<GrenadeFilters>) => {
        setFiltersState(prev => ({ ...prev, ...newFilters }));
    }, []);

    const resetFilters = useCallback(() => {
        setFiltersState(DEFAULT_FILTERS);
        setGrenades([]);
        setGrenadeStates(new Map());
    }, []);

    // Grenade selection management
    const selectGrenade = useCallback((grenadeId: number, selected: boolean = true) => {
        setGrenadeStates(prev => {
            const newStates = new Map(prev);
            const currentState = newStates.get(grenadeId);
            if (currentState) {
                newStates.set(grenadeId, { ...currentState, isSelected: selected });
            }
            return newStates;
        });
    }, []);

    const selectGrenades = useCallback((grenadeIds: number[], selected: boolean = true) => {
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
    }, []);

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

    // Effects
    useEffect(() => {
        loadFilterOptions();
    }, [loadFilterOptions]);

    useEffect(() => {
        initializeWithDefaults();
    }, [initializeWithDefaults]);

    useEffect(() => {
        loadMatchDependentOptions();
    }, [loadMatchDependentOptions]);

    useEffect(() => {
        loadGrenades();
    }, [loadGrenades]);

    // Handle map change with auto-selection of first match
    useEffect(() => {
        if (filters.map && !filters.matchId && isInitialized) {
            const loadMatchesForMap = async () => {
                try {
                    const response = await get<FilterOptions>('/grenade-library/filter-options', {
                        params: { map: filters.map }
                    });
                    const data = response.data;

                    setFilterOptions(prev => ({
                        ...prev,
                        matches: data.matches,
                        rounds: [],
                        players: [],
                    }));

                    if (data.matches.length > 0) {
                        setFiltersState(prev => ({
                            ...prev,
                            matchId: data.matches[0].id,
                            roundNumber: 'all',
                            playerSteamId: 'all',
                        }));
                    }
                } catch (err) {
                    console.error('Failed to load matches for map:', err);
                }
            };

            loadMatchesForMap();
        }
    }, [filters.map, filters.matchId, get, isInitialized]);

    const contextValue: UseGrenadeLibraryReturn = {
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
        <GrenadeLibraryContext.Provider value={contextValue}>
            {children}
        </GrenadeLibraryContext.Provider>
    );
};

// Hook to use the context
export const useGrenadeLibrary = (): UseGrenadeLibraryReturn => {
    const context = useContext(GrenadeLibraryContext);
    if (!context) {
        throw new Error('useGrenadeLibrary must be used within a GrenadeLibraryProvider');
    }
    return context;
};
