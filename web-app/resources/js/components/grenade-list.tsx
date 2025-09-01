import React, { useEffect, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { Copy, Star } from 'lucide-react';
import {
  useGrenadeLibrary,
  FavouritedGrenadeData,
} from '../hooks/useGrenadeLibrary';
import { useMatchGrenades, GrenadeData } from '../hooks/useMatchGrenades';
import { useGrenadeFavourites } from '../hooks/useGrenadeFavourites';

interface GrenadeListProps {
  onGrenadeClick: (grenade: GrenadeData | FavouritedGrenadeData) => void;
  selectedGrenadeId?: number | null;
  showFavourites?: boolean;
  useFavouritesContext?: boolean;
  hideRoundNumber?: boolean;
}

// Component for favourites context
const GrenadeListWithFavourites: React.FC<
  Omit<GrenadeListProps, 'useFavouritesContext'>
> = props => {
  const { grenades, removeFavourite } = useGrenadeLibrary();
  const {
    isFavourited,
    isLoading,
    toggleFavourite,
    initializeFavouriteStatus,
  } = useGrenadeFavourites();

  return (
    <GrenadeListContent
      {...props}
      grenades={grenades}
      removeFavourite={removeFavourite}
      isFavourited={isFavourited}
      isLoading={isLoading}
      toggleFavourite={toggleFavourite}
      initializeFavouriteStatus={initializeFavouriteStatus}
      useFavouritesContext={true}
    />
  );
};

// Component for match grenades context
const GrenadeListWithMatchGrenades: React.FC<
  Omit<GrenadeListProps, 'useFavouritesContext'>
> = props => {
  const { grenades } = useMatchGrenades();
  const {
    isFavourited,
    isLoading,
    toggleFavourite,
    initializeFavouriteStatus,
  } = useGrenadeFavourites();

  return (
    <GrenadeListContent
      {...props}
      grenades={grenades}
      removeFavourite={null}
      isFavourited={isFavourited}
      isLoading={isLoading}
      toggleFavourite={toggleFavourite}
      initializeFavouriteStatus={initializeFavouriteStatus}
      useFavouritesContext={false}
    />
  );
};

// Shared content component
interface GrenadeListContentProps
  extends Omit<GrenadeListProps, 'useFavouritesContext'> {
  grenades: (GrenadeData | FavouritedGrenadeData)[];
  removeFavourite: ((favouriteId: number) => void) | null;
  isFavourited: (grenade: GrenadeData) => boolean;
  isLoading: (grenade: GrenadeData) => boolean;
  toggleFavourite: (grenade: GrenadeData) => void;
  initializeFavouriteStatus: (grenades: GrenadeData[]) => void;
  useFavouritesContext: boolean;
}

const GrenadeListContent: React.FC<GrenadeListContentProps> = ({
  onGrenadeClick,
  selectedGrenadeId,
  showFavourites = false,
  hideRoundNumber = false,
  grenades,
  removeFavourite,
  isFavourited,
  isLoading,
  toggleFavourite,
  initializeFavouriteStatus,
  useFavouritesContext,
}) => {
  // Memoize grenades to prevent unnecessary re-renders
  const memoizedGrenades = useMemo(() => grenades, [grenades]);

  // Initialize favourite status when grenades change (only for regular grenade library)
  useEffect(() => {
    if (showFavourites && memoizedGrenades.length > 0) {
      initializeFavouriteStatus(memoizedGrenades as GrenadeData[]);
    }
  }, [memoizedGrenades, showFavourites, initializeFavouriteStatus]);

  const generatePositionString = (
    grenade: GrenadeData | FavouritedGrenadeData
  ): string => {
    const playerZ = grenade.player_z ?? 0.0;
    const aimX = grenade.player_aim_x ?? 0.0;
    const aimY = grenade.player_aim_y ?? 0.0;

    // Format: setpos {player_x} {player_y} {player_z};setang {player_aim_y} {player_aim_x} 0.000000
    return `setpos ${grenade.player_x} ${grenade.player_y} ${playerZ};setang ${aimY} ${aimX} 0.000000`;
  };

  const copyPositionToClipboard = async (
    grenade: GrenadeData | FavouritedGrenadeData
  ) => {
    try {
      const positionString = generatePositionString(grenade);
      await navigator.clipboard.writeText(positionString);
      console.log('Position copied to clipboard:', positionString);
    } catch (err) {
      console.error('Failed to copy position to clipboard:', err);
    }
  };

  // Get throw type display name
  const getThrowTypeDisplay = (_type: string): string => {
    return _type;
  };

  // Get player side display name
  const getPlayerSideDisplay = (side: string): string => {
    return side === 'T' ? 'Terrorist' : 'Counter-Terrorist';
  };

  if (memoizedGrenades.length === 0) {
    return (
      <div className="w-100 h-[575px] flex flex-col">
        <div className="p-4 pt-0 border-b flex items-center justify-between">
          <h3 className="font-semibold">Grenade List</h3>
          <p className="text-sm text-muted-foreground">0 grenades found</p>
        </div>
      </div>
    );
  }

  const getSideBadge = (grenade: GrenadeData | FavouritedGrenadeData) => {
    let sideColour = 'border-blue';

    if (grenade.player_side === 'T') {
      sideColour = 'border-orange';
    }

    return (
      <Badge
        className={`ml-auto text-xs bg-transparent text-white border-2 ${sideColour}`}
      >
        {getPlayerSideDisplay(grenade.player_side || 'unknown')}
      </Badge>
    );
  };

  return (
    <TooltipProvider>
      <div className="w-100 h-[575px] flex flex-col">
        <div className="p-4 pt-0 border-b flex items-center justify-between">
          <h3 className="font-semibold">Grenade List</h3>
          <p className="text-sm text-muted-foreground">
            {memoizedGrenades.length} grenade
            {memoizedGrenades.length !== 1 ? 's' : ''}
          </p>
        </div>

        <div className="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar">
          {memoizedGrenades.map(grenade => {
            const isSelected = selectedGrenadeId === grenade.id;

            return (
              <Card
                key={grenade.id}
                className={`cursor-pointer transition-all hover:shadow-md py-2 ${isSelected ? 'ring-2 ring-orange-500' : ''
                  }`}
                onClick={() => onGrenadeClick(grenade)}
              >
                <CardContent className="px-3">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <div className="flex items-center justify-between text-sm">
                        <span className="font-medium">
                          {grenade.player_name}
                        </span>
                        {!hideRoundNumber && (
                          <span className="ml-2 text-xs text-muted-foreground">
                            Round {grenade.round_number}
                          </span>
                        )}
                      </div>
                      {getSideBadge(grenade)}
                    </div>
                    <div className="flex items-start justify-between mb-1">
                      <span className="text-xs text-muted-foreground">
                        {getThrowTypeDisplay(grenade.throw_type || 'Run throw')}
                      </span>
                      <div className="flex items-center gap-1">
                        {showFavourites && !useFavouritesContext && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                variant="ghost"
                                size="sm"
                                className="h-6 w-6 p-0"
                                onClick={e => {
                                  e.stopPropagation();
                                  toggleFavourite(grenade as GrenadeData);
                                }}
                                disabled={isLoading(grenade as GrenadeData)}
                              >
                                <Star
                                  className={`h-3 w-3 ${isFavourited(grenade as GrenadeData) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-400'}`}
                                />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent className="border-custom-orange border-2 bg-background">
                              <p className="font-semibold text-white">
                                {isFavourited(grenade as GrenadeData)
                                  ? 'Remove from favourites'
                                  : 'Add to favourites'}
                              </p>
                            </TooltipContent>
                          </Tooltip>
                        )}
                        {useFavouritesContext && removeFavourite && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                variant="ghost"
                                size="sm"
                                className="h-6 w-6 p-0"
                                onClick={e => {
                                  e.stopPropagation();
                                  removeFavourite(grenade.id);
                                }}
                              >
                                <Star className="h-3 w-3 fill-yellow-400 text-yellow-400" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent className="border-custom-orange border-2 bg-background">
                              <p className="font-semibold text-white">
                                Remove from favourites
                              </p>
                            </TooltipContent>
                          </Tooltip>
                        )}
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-6 w-6 p-0"
                              onClick={e => {
                                e.stopPropagation();
                                copyPositionToClipboard(grenade);
                              }}
                            >
                              <Copy className="h-3 w-3" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent className="border-custom-orange border-2 bg-background">
                            <p className="font-semibold text-white">
                              Copy grenade throw location to clipboard
                            </p>
                          </TooltipContent>
                        </Tooltip>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      </div>
    </TooltipProvider>
  );
};

// Main component that chooses which implementation to use
const GrenadeList: React.FC<GrenadeListProps> = ({
  useFavouritesContext = false,
  ...props
}) => {
  if (useFavouritesContext) {
    return <GrenadeListWithFavourites {...props} />;
  }

  return <GrenadeListWithMatchGrenades {...props} />;
};

export default GrenadeList;
