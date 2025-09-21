import { useState, useCallback } from 'react';

interface SteamSharecodeData {
  has_sharecode: boolean;
  has_complete_setup: boolean;
  steam_sharecode_added_at: string | null;
}

interface UseSteamSharecodeReturn {
  hasSharecode: boolean;
  hasCompleteSetup: boolean;
  sharecodeAddedAt: string | null;
  loading: boolean;
  error: string | null;
  saveSharecode: (sharecode: string, gameAuthCode: string) => Promise<void>;
  removeSharecode: () => Promise<void>;
  toggleProcessing: () => Promise<boolean>;
  checkSharecodeStatus: () => Promise<void>;
}

export function useSteamSharecode(): UseSteamSharecodeReturn {
  const [hasSharecode, setHasSharecode] = useState(false);
  const [hasCompleteSetup, setHasCompleteSetup] = useState(false);
  const [sharecodeAddedAt, setSharecodeAddedAt] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const getAuthHeaders = () => {
    const token = localStorage.getItem('auth_token');
    return {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    };
  };

  const saveSharecode = useCallback(async (sharecode: string, gameAuthCode: string) => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('/api/steam-sharecode', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          steam_sharecode: sharecode,
          steam_game_auth_code: gameAuthCode
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to save sharecode');
      }

      const data = await response.json();
      setHasSharecode(true);
      setHasCompleteSetup(true);
      setSharecodeAddedAt(data.user.steam_sharecode_added_at);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save sharecode');
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  const removeSharecode = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('/api/steam-sharecode', {
        method: 'DELETE',
        headers: getAuthHeaders(),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to remove sharecode');
      }

      setHasSharecode(false);
      setHasCompleteSetup(false);
      setSharecodeAddedAt(null);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'Failed to remove sharecode'
      );
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  const toggleProcessing = useCallback(async (): Promise<boolean> => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('/api/steam-sharecode/toggle-processing', {
        method: 'POST',
        headers: getAuthHeaders(),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to toggle processing');
      }

      const data = await response.json();
      return data.steam_match_processing_enabled;
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'Failed to toggle processing'
      );
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  const checkSharecodeStatus = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('/api/steam-sharecode/has-sharecode', {
        method: 'GET',
        headers: getAuthHeaders(),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(
          errorData.message || 'Failed to check sharecode status'
        );
      }

      const data: SteamSharecodeData = await response.json();
      setHasSharecode(data.has_sharecode);
      setHasCompleteSetup(data.has_complete_setup);
      setSharecodeAddedAt(data.steam_sharecode_added_at);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'Failed to check sharecode status'
      );
    } finally {
      setLoading(false);
    }
  }, []);

  return {
    hasSharecode,
    hasCompleteSetup,
    sharecodeAddedAt,
    loading,
    error,
    saveSharecode,
    removeSharecode,
    toggleProcessing,
    checkSharecodeStatus,
  };
}
