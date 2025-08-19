import React from 'react';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useGrenadeLibrary } from '../hooks/useGrenadeLibrary';

const GrenadeFilters: React.FC = () => {
  const {
    filters,
    filterOptions,
    setFilter,
  } = useGrenadeLibrary();

  const handleFilterChange = (key: string, value: string) => {
    setFilter(key as keyof typeof filters, value);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4 p-4 border rounded-lg bg-muted/50">
        {/* Map Filter - No "All" option, must be selected */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="map-filter" className="text-xs">
            Map
          </Label>
          <Select
            value={filters.map}
            onValueChange={value => handleFilterChange('map', value)}
          >
            <SelectTrigger id="map-filter" className="h-8 w-full">
              <SelectValue placeholder="Select map" />
            </SelectTrigger>
            <SelectContent>
              {filterOptions.maps.map(map => (
                <SelectItem key={map.name} value={map.name}>
                  {map.displayName}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Match Filter - No "All" option, depends on Map */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="match-filter" className="text-xs">
            Match
          </Label>
          <Select
            value={filters.matchId}
            onValueChange={value => handleFilterChange('matchId', value)}
            disabled={!filters.map || filterOptions.matches.length === 0}
          >
            <SelectTrigger id="match-filter" className="h-8">
              <SelectValue
                placeholder={!filters.map ? 'Select map first' : 'Select match'}
              />
            </SelectTrigger>
            <SelectContent>
              {filterOptions.matches.map(match => (
                <SelectItem key={match.id} value={match.id}>
                  {match.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Grenade Type Filter - No "All" option, hardcoded with Fire Grenades */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="grenade-type-filter" className="text-xs">
            Grenade Type
          </Label>
          <Select
            value={filters.grenadeType}
            onValueChange={value => handleFilterChange('grenadeType', value)}
          >
            <SelectTrigger id="grenade-type-filter" className="h-8">
              <SelectValue placeholder="Select type" />
            </SelectTrigger>
            <SelectContent>
              {filterOptions.grenadeTypes.map(type => (
                <SelectItem key={type.type} value={type.type}>
                  {type.displayName}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Player Filter - ALLOW "All" option, depends on Match */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="player-filter" className="text-xs">
            Player
          </Label>
          <Select
            value={filters.playerSteamId}
            onValueChange={value => handleFilterChange('playerSteamId', value)}
            disabled={!filters.matchId || filterOptions.players.length === 0}
          >
            <SelectTrigger id="player-filter" className="h-8">
              <SelectValue
                placeholder={
                  !filters.matchId ? 'Select match first' : 'Select player'
                }
              />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Players</SelectItem>
              {filterOptions.players.map(player => (
                <SelectItem key={player.steam_id} value={player.steam_id}>
                  {player.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Player Side Filter - ALLOW "All" option */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="player-side-filter" className="text-xs">
            Player Side
          </Label>
          <Select
            value={filters.playerSide}
            onValueChange={value => handleFilterChange('playerSide', value)}
          >
            <SelectTrigger id="player-side-filter" className="h-8">
              <SelectValue placeholder="Select side" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Sides</SelectItem>
              {filterOptions.playerSides.map(side => (
                <SelectItem key={side.side} value={side.side}>
                  {side.displayName}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>
  );
};

export default GrenadeFilters;
