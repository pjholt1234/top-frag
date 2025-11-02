import { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { usePlayerCard } from '@/hooks/use-player-card';
import { PlayerSummaryCard } from './dashboard/player-summary-card';
import { api } from '@/lib/api';
import { Skeleton } from './ui/skeleton';
import { Card, CardContent } from './ui/card';

interface PlayerComplexion {
  opener: number;
  closer: number;
  support: number;
  fragger: number;
}

interface PlayerCardData {
  username: string;
  avatar: string | null;
  average_impact: number;
  average_round_swing: number;
  average_kd: number;
  average_adr: number;
  average_kills: number;
  average_deaths: number;
  total_kills: number;
  total_deaths: number;
  total_matches: number;
  win_percentage: number;
  player_complexion: PlayerComplexion;
}

interface AchievementCounts {
  fragger: number;
  support: number;
  opener: number;
  closer: number;
  top_aimer: number;
  impact_player: number;
  difference_maker: number;
}

interface PlayerCardResponse {
  player_card: PlayerCardData;
  achievements: AchievementCounts;
}

export function PlayerCardModal() {
  const { isVisible, steamId, position, hidePlayerCard, cancelHide } =
    usePlayerCard();
  const [data, setData] = useState<PlayerCardResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const modalRef = useRef<HTMLDivElement>(null);

  // Prevent hiding when mouse enters modal
  const handleModalMouseEnter = () => {
    cancelHide();
  };

  const handleModalMouseLeave = () => {
    hidePlayerCard();
  };

  // Fetch player card data when steamId changes
  useEffect(() => {
    if (!isVisible || !steamId) {
      setData(null);
      setError(null);
      return;
    }

    const fetchPlayerCard = async () => {
      setLoading(true);
      setError(null);

      try {
        const response = await api.get<PlayerCardResponse>(
          `/player-card/${steamId}`,
          { requireAuth: true }
        );
        setData(response.data);
      } catch (err: any) {
        console.error('Error fetching player card:', err);
        setError(err.response?.data?.message || 'Failed to load player card');
      } finally {
        setLoading(false);
      }
    };

    fetchPlayerCard();
  }, [isVisible, steamId]);

  // Calculate modal position - ensure it stays within viewport
  useEffect(() => {
    if (!isVisible || !position || !modalRef.current) return;

    // Use requestAnimationFrame to ensure DOM has updated and dimensions are accurate
    requestAnimationFrame(() => {
      if (!modalRef.current) return;

      const modal = modalRef.current;
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;
      const padding = 10; // Padding from viewport edges

      // Measure actual modal dimensions after render
      const modalRect = modal.getBoundingClientRect();
      const modalWidth = modalRect.width || 384; // Fallback to w-96 if not measured
      const modalHeight = modalRect.height || 600; // Fallback estimate

      // Default offset from cursor
      const offset = 15;
      let x = position.x + offset;
      let y = position.y + offset;

      // Adjust if modal would overflow right edge
      if (x + modalWidth > viewportWidth - padding) {
        // Try to position to the left of cursor
        x = position.x - modalWidth - offset;

        // If that would go off left edge, align to right edge
        if (x < padding) {
          x = viewportWidth - modalWidth - padding;
        }
      }

      // Adjust if modal would overflow bottom edge
      if (y + modalHeight > viewportHeight - padding) {
        // Try to position above cursor
        y = position.y - modalHeight - offset;

        // If that would go off top edge, align to bottom edge
        if (y < padding) {
          y = viewportHeight - modalHeight - padding;
        }
      }

      // Final safety check - ensure modal stays within viewport with padding
      // Use Math.max to prevent negative values and Math.min to prevent overflow
      x = Math.max(padding, Math.min(x, viewportWidth - modalWidth - padding));
      y = Math.max(
        padding,
        Math.min(y, viewportHeight - modalHeight - padding)
      );

      // Apply the position
      modal.style.left = `${x}px`;
      modal.style.top = `${y}px`;
    });
  }, [isVisible, position, data, loading]); // Recalculate when content loads/changes

  // Handle click outside and escape key
  useEffect(() => {
    if (!isVisible) return;

    const handleClickOutside = (event: MouseEvent) => {
      if (
        modalRef.current &&
        !modalRef.current.contains(event.target as Node)
      ) {
        hidePlayerCard();
      }
    };

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        hidePlayerCard();
      }
    };

    // Small delay to prevent immediate closing when opening
    const timeout = setTimeout(() => {
      document.addEventListener('mousedown', handleClickOutside);
      document.addEventListener('keydown', handleEscape);
    }, 200);

    return () => {
      clearTimeout(timeout);
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isVisible, hidePlayerCard]);

  if (!isVisible) {
    return null;
  }

  const modalContent = (
    <div
      ref={modalRef}
      className="fixed z-50 pointer-events-auto w-96 max-w-[calc(100vw-20px)] max-h-[calc(100vh-20px)] overflow-auto shadow-2xl"
      style={{
        // Initial position set inline, will be adjusted by useEffect
        left: position?.x ? `${position.x}px` : '0px',
        top: position?.y ? `${position.y}px` : '0px',
      }}
      onMouseEnter={handleModalMouseEnter}
      onMouseLeave={handleModalMouseLeave}
    >
      {loading && (
        <Card className="shadow-2xl border-gray-700">
          <CardContent className="p-6 space-y-4">
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-32 w-full" />
            <Skeleton className="h-48 w-full" />
          </CardContent>
        </Card>
      )}
      {error && (
        <Card className="shadow-2xl border-gray-700">
          <CardContent className="p-6 text-center">
            <p className="text-red-500 text-sm">{error}</p>
          </CardContent>
        </Card>
      )}
      {!loading && !error && data && (
        <PlayerSummaryCard
          playerCard={data.player_card}
          achievements={data.achievements}
        />
      )}
    </div>
  );

  // Render backdrop - transparent so it doesn't interfere with hover
  const backdrop = (
    <div
      className="fixed inset-0 z-40 pointer-events-none"
      onMouseDown={e => {
        // Allow clicks through backdrop, only close on direct click
        if (e.target === e.currentTarget) {
          hidePlayerCard();
        }
      }}
    />
  );

  return createPortal(
    <>
      {backdrop}
      {modalContent}
    </>,
    document.body
  );
}
