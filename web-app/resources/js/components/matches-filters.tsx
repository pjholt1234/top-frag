import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { IconX } from '@tabler/icons-react';

interface MatchFilters {
  map: string;
  match_type: string;
  player_was_participant: string;
  player_won_match: string;
  date_from: string;
  date_to: string;
}

interface MatchesFiltersProps {
  filters: MatchFilters;
  onFiltersChange: (filters: MatchFilters) => void;
  onClearFilters: () => void;
}

const MATCH_TYPES = [
  { value: 'all', label: 'All types' },
  { value: 'competitive', label: 'Competitive' },
  { value: 'casual', label: 'Casual' },
  { value: 'scrim', label: 'Scrim' },
  { value: 'other', label: 'Other' },
];

const MAPS = [
  { value: 'all', label: 'All maps' },
  { value: 'de_ancient', label: 'Ancient' },
  { value: 'de_anubis', label: 'Anubis' },
  { value: 'de_inferno', label: 'Inferno' },
  { value: 'de_mirage', label: 'Mirage' },
  { value: 'de_nuke', label: 'Nuke' },
  { value: 'de_overpass', label: 'Overpass' },
  { value: 'de_vertigo', label: 'Vertigo' },
  { value: 'de_dust2', label: 'Dust 2' },
  { value: 'de_cache', label: 'Cache' },
  { value: 'de_cobblestone', label: 'Cobblestone' },
  { value: 'de_train', label: 'Train' },
];

export function MatchesFilters({
  filters,
  onFiltersChange,
  onClearFilters,
}: MatchesFiltersProps) {
  const handleFilterChange = (key: keyof MatchFilters, value: string) => {
    onFiltersChange({
      ...filters,
      [key]: value,
    });
  };

  const hasActiveFilters = Object.values(filters).some(value => value !== '');

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4 p-4 border rounded-lg bg-muted/50">
        {/* Map Filter */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="map-filter" className="text-xs">
            Map
          </Label>
          <Select
            value={filters.map || 'all'}
            onValueChange={value =>
              handleFilterChange('map', value === 'all' ? '' : value)
            }
          >
            <SelectTrigger id="map-filter" className="h-8 w-full">
              <SelectValue placeholder="All maps" />
            </SelectTrigger>
            <SelectContent>
              {MAPS.map(map => (
                <SelectItem key={map.value} value={map.value}>
                  {map.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Match Type Filter */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="match-type-filter" className="text-xs">
            Type
          </Label>
          <Select
            value={filters.match_type || 'all'}
            onValueChange={value =>
              handleFilterChange('match_type', value === 'all' ? '' : value)
            }
          >
            <SelectTrigger id="match-type-filter" className="h-8 w-full">
              <SelectValue placeholder="All types" />
            </SelectTrigger>
            <SelectContent>
              {MATCH_TYPES.map(type => (
                <SelectItem key={type.value} value={type.value}>
                  {type.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Participation Filter */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="participation-filter" className="text-xs">
            Participation
          </Label>
          <Select
            value={filters.player_was_participant || 'all'}
            onValueChange={value =>
              handleFilterChange(
                'player_was_participant',
                value === 'all' ? '' : value
              )
            }
          >
            <SelectTrigger id="participation-filter" className="h-8 w-full">
              <SelectValue placeholder="All matches" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All matches</SelectItem>
              <SelectItem value="true">Participated</SelectItem>
              <SelectItem value="false">Did not participate</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Win/Loss Filter */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="result-filter" className="text-xs">
            Result
          </Label>
          <Select
            value={filters.player_won_match || 'all'}
            onValueChange={value =>
              handleFilterChange(
                'player_won_match',
                value === 'all' ? '' : value
              )
            }
          >
            <SelectTrigger id="result-filter" className="h-8 w-full">
              <SelectValue placeholder="All results" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All results</SelectItem>
              <SelectItem value="true">Wins</SelectItem>
              <SelectItem value="false">Losses</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Date From Filter */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="date-from-filter" className="text-xs">
            From Date
          </Label>
          <Input
            id="date-from-filter"
            type="date"
            value={filters.date_from}
            onChange={e => handleFilterChange('date_from', e.target.value)}
            className="h-8"
          />
        </div>

        {/* Date To Filter */}
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="date-to-filter" className="text-xs">
            To Date
          </Label>
          <Input
            id="date-to-filter"
            type="date"
            value={filters.date_to}
            onChange={e => handleFilterChange('date_to', e.target.value)}
            className="h-8"
          />
        </div>

        {/* Clear Button */}
        {hasActiveFilters && (
          <div className="flex items-end">
            <Button
              variant="ghost"
              size="sm"
              onClick={onClearFilters}
              className="h-8 flex items-center gap-2 text-muted-foreground hover:text-foreground"
            >
              <IconX className="h-4 w-4" />
              Clear
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
