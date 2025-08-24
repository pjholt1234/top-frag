import { useState, useEffect, useCallback } from 'react';
import { toast } from 'sonner';
import { api } from '../lib/api';
import { GrenadeData } from './useMatchGrenades';

interface FavouriteStatus {
    is_favourited: boolean;
    favourite_id: number | null;
}

export const useGrenadeFavourites = () => {
    const [favouritedGrenades, setFavouritedGrenades] = useState<Set<string>>(new Set());
    const [favouriteIds, setFavouriteIds] = useState<Map<string, number>>(new Map());
    const [loading, setLoading] = useState<Map<string, boolean>>(new Map());

    // Generate a unique key for a grenade
    const getGrenadeKey = useCallback((grenade: GrenadeData): string => {
        return `${grenade.match_id}-${grenade.round_number}-${grenade.tick_timestamp || 0}-${grenade.player_steam_id}`;
    }, []);

    // Check if a grenade is favourited
    const checkFavouriteStatus = useCallback(async (grenade: GrenadeData): Promise<FavouriteStatus> => {
        const key = getGrenadeKey(grenade);

        try {
            const response = await api.get<FavouriteStatus>('/grenade-favourites/check', {
                params: {
                    match_id: grenade.match_id,
                    round_number: grenade.round_number,
                    tick_timestamp: grenade.tick_timestamp || 0,
                    player_steam_id: grenade.player_steam_id,
                },
                requireAuth: true,
            });

            return response.data;
        } catch (error) {
            console.error('Error checking favourite status:', error);
            return { is_favourited: false, favourite_id: null };
        }
    }, [getGrenadeKey]);

    // Add a grenade to favourites
    const addToFavourites = useCallback((grenade: GrenadeData): void => {
        const key = getGrenadeKey(grenade);

        if (loading.get(key)) return false;

        setLoading(prev => new Map(prev).set(key, true));

        return toast.promise(
            (async () => {
                try {
                    // Convert GrenadeData to the format expected by the API
                    const favouriteData = {
                        match_id: grenade.match_id,
                        round_number: grenade.round_number,
                        round_time: grenade.round_time || 0,
                        tick_timestamp: grenade.tick_timestamp || 0,
                        player_steam_id: grenade.player_steam_id || '',
                        player_side: grenade.player_side || 'CT',
                        grenade_type: grenade.grenade_type,
                        player_x: grenade.player_x,
                        player_y: grenade.player_y,
                        player_z: grenade.player_z || 0,
                        player_aim_x: grenade.player_aim_x || 0,
                        player_aim_y: grenade.player_aim_y || 0,
                        player_aim_z: grenade.player_aim_z || 0,
                        grenade_final_x: grenade.grenade_final_x,
                        grenade_final_y: grenade.grenade_final_y,
                        grenade_final_z: grenade.grenade_final_z || 0,
                        damage_dealt: grenade.damage_dealt || 0,
                        flash_duration: grenade.flash_duration,
                        friendly_flash_duration: grenade.friendly_flash_duration,
                        enemy_flash_duration: grenade.enemy_flash_duration,
                        friendly_players_affected: grenade.friendly_players_affected,
                        enemy_players_affected: grenade.enemy_players_affected,
                        throw_type: grenade.throw_type || 'utility',
                        effectiveness_rating: grenade.effectiveness_rating,
                    };

                    const response = await api.post('/grenade-favourites', favouriteData, {
                        requireAuth: true,
                    });

                    setFavouritedGrenades(prev => new Set(prev).add(key));
                    setFavouriteIds(prev => new Map(prev).set(key, response.data.favourite.id));

                    return true;
                } catch (error: any) {
                    console.error('Error adding to favourites:', error);
                    if (error.response?.status === 409) {
                        // Already favourited, update state
                        setFavouritedGrenades(prev => new Set(prev).add(key));
                        return true;
                    }
                    throw error;
                } finally {
                    setLoading(prev => {
                        const newMap = new Map(prev);
                        newMap.delete(key);
                        return newMap;
                    });
                }
            })(),
            {
                loading: 'Adding grenade to favourites...',
                success: 'Grenade added to favourites!',
                error: 'Failed to add grenade to favourites',
            }
        );
    }, [getGrenadeKey, loading]);

    // Remove a grenade from favourites
    const removeFromFavourites = useCallback((grenade: GrenadeData): void => {
        const key = getGrenadeKey(grenade);
        const favouriteId = favouriteIds.get(key);

        if (loading.get(key) || !favouriteId) return false;

        setLoading(prev => new Map(prev).set(key, true));

        return toast.promise(
            (async () => {
                try {
                    await api.delete(`/grenade-favourites/${favouriteId}`, {
                        requireAuth: true,
                    });

                    setFavouritedGrenades(prev => {
                        const newSet = new Set(prev);
                        newSet.delete(key);
                        return newSet;
                    });
                    setFavouriteIds(prev => {
                        const newMap = new Map(prev);
                        newMap.delete(key);
                        return newMap;
                    });

                    return true;
                } catch (error) {
                    console.error('Error removing from favourites:', error);
                    throw error;
                } finally {
                    setLoading(prev => {
                        const newMap = new Map(prev);
                        newMap.delete(key);
                        return newMap;
                    });
                }
            })(),
            {
                loading: 'Removing grenade from favourites...',
                success: 'Grenade removed from favourites!',
                error: 'Failed to remove grenade from favourites',
            }
        );
    }, [getGrenadeKey, favouriteIds, loading]);

    // Toggle favourite status
    const toggleFavourite = useCallback((grenade: GrenadeData): void => {
        const key = getGrenadeKey(grenade);
        const isFavourited = favouritedGrenades.has(key);

        if (isFavourited) {
            removeFromFavourites(grenade);
        } else {
            addToFavourites(grenade);
        }
    }, [getGrenadeKey, favouritedGrenades, addToFavourites, removeFromFavourites]);

    // Check if a grenade is favourited (from state)
    const isFavourited = useCallback((grenade: GrenadeData): boolean => {
        const key = getGrenadeKey(grenade);
        return favouritedGrenades.has(key);
    }, [getGrenadeKey, favouritedGrenades]);

    // Check if a grenade is currently being processed
    const isLoading = useCallback((grenade: GrenadeData): boolean => {
        const key = getGrenadeKey(grenade);
        return loading.get(key) || false;
    }, [getGrenadeKey, loading]);

    // Initialize favourite status for a list of grenades
    const initializeFavouriteStatus = useCallback(async (grenades: GrenadeData[]) => {
        const promises = grenades.map(async (grenade) => {
            const status = await checkFavouriteStatus(grenade);
            const key = getGrenadeKey(grenade);

            if (status.is_favourited) {
                setFavouritedGrenades(prev => new Set(prev).add(key));
                if (status.favourite_id) {
                    setFavouriteIds(prev => new Map(prev).set(key, status.favourite_id));
                }
            }
        });

        await Promise.all(promises);
    }, [checkFavouriteStatus, getGrenadeKey]);

    return {
        isFavourited,
        isLoading,
        toggleFavourite,
        initializeFavouriteStatus,
    };
};
