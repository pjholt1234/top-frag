import React, {
  createContext,
  useContext,
  useState,
  useCallback,
  ReactNode,
} from 'react';

interface PlayerCardContextType {
  showPlayerCard: (
    steamId: string,
    event?: MouseEvent | React.MouseEvent
  ) => void;
  hidePlayerCard: () => void;
  cancelHide: () => void;
  isVisible: boolean;
  steamId: string | null;
  position: { x: number; y: number } | null;
}

const PlayerCardContext = createContext<PlayerCardContextType | undefined>(
  undefined
);

export const usePlayerCard = () => {
  const context = useContext(PlayerCardContext);
  if (context === undefined) {
    throw new Error('usePlayerCard must be used within a PlayerCardProvider');
  }
  return context;
};

interface PlayerCardProviderProps {
  children: ReactNode;
}

export const PlayerCardProvider: React.FC<PlayerCardProviderProps> = ({
  children,
}) => {
  const [isVisible, setIsVisible] = useState(false);
  const [steamId, setSteamId] = useState<string | null>(null);
  const [position, setPosition] = useState<{ x: number; y: number } | null>(
    null
  );
  const showTimeoutRef = React.useRef<NodeJS.Timeout | null>(null);
  const hideTimeoutRef = React.useRef<NodeJS.Timeout | null>(null);

  const showPlayerCard = useCallback(
    (id: string, event?: MouseEvent | React.MouseEvent) => {
      // Clear any pending hide timeout
      if (hideTimeoutRef.current) {
        clearTimeout(hideTimeoutRef.current);
        hideTimeoutRef.current = null;
      }

      // If already showing the same player, just update position
      if (isVisible && steamId === id && event) {
        const clientX =
          'clientX' in event
            ? event.clientX
            : (event.nativeEvent?.clientX ?? 0);
        const clientY =
          'clientY' in event
            ? event.clientY
            : (event.nativeEvent?.clientY ?? 0);
        setPosition({ x: clientX, y: clientY });
        return;
      }

      // Clear any pending show timeout
      if (showTimeoutRef.current) {
        clearTimeout(showTimeoutRef.current);
      }

      // Calculate position from mouse event
      let calculatedPosition = { x: 0, y: 0 };
      if (event) {
        const clientX =
          'clientX' in event
            ? event.clientX
            : (event.nativeEvent?.clientX ?? 0);
        const clientY =
          'clientY' in event
            ? event.clientY
            : (event.nativeEvent?.clientY ?? 0);
        calculatedPosition = { x: clientX, y: clientY };
      } else {
        // Center on screen if no event
        calculatedPosition = {
          x: window.innerWidth / 2,
          y: window.innerHeight / 2,
        };
      }

      // Small delay before showing to avoid flickering
      showTimeoutRef.current = setTimeout(() => {
        setSteamId(id);
        setIsVisible(true);
        setPosition(calculatedPosition);
      }, 300);
    },
    [isVisible, steamId]
  );

  const hidePlayerCard = useCallback(() => {
    // Clear any pending show timeout
    if (showTimeoutRef.current) {
      clearTimeout(showTimeoutRef.current);
      showTimeoutRef.current = null;
    }

    // Add a small delay before hiding to allow moving mouse to modal
    hideTimeoutRef.current = setTimeout(() => {
      setIsVisible(false);
      setSteamId(null);
      setPosition(null);
      hideTimeoutRef.current = null;
    }, 150);
  }, []);

  const cancelHide = useCallback(() => {
    if (hideTimeoutRef.current) {
      clearTimeout(hideTimeoutRef.current);
      hideTimeoutRef.current = null;
    }
  }, []);

  const value: PlayerCardContextType = {
    showPlayerCard,
    hidePlayerCard,
    cancelHide,
    isVisible,
    steamId,
    position,
  };

  return (
    <PlayerCardContext.Provider value={value}>
      {children}
    </PlayerCardContext.Provider>
  );
};
