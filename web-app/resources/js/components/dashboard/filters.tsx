import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { DashboardFilters as DashboardFiltersType } from '@/pages/dashboard';

interface DashboardFiltersProps {
  filters: DashboardFiltersType;
  onFiltersChange: (filters: DashboardFiltersType) => void;
  disableGameType?: boolean;
  disableMap?: boolean;
}

const MATCH_COUNTS = [
  { value: 5, label: 'Last 5 matches' },
  { value: 10, label: 'Last 10 matches' },
  { value: 15, label: 'Last 15 matches' },
  { value: 30, label: 'Last 30 matches' },
];

const GAME_TYPES = [
  { value: 'all', label: 'All game types' },
  { value: 'competitive', label: 'Competitive' },
  { value: 'wingman', label: 'Wingman' },
  { value: 'premier', label: 'Premier' },
];

const MAPS = [
  { value: 'all', label: 'All maps' },
  { value: 'de_dust2', label: 'Dust 2' },
  { value: 'de_mirage', label: 'Mirage' },
  { value: 'de_inferno', label: 'Inferno' },
  { value: 'de_nuke', label: 'Nuke' },
  { value: 'de_overpass', label: 'Overpass' },
  { value: 'de_vertigo', label: 'Vertigo' },
  { value: 'de_ancient', label: 'Ancient' },
  { value: 'de_anubis', label: 'Anubis' },
];

export const DashboardFilters = ({
  filters,
  onFiltersChange,
  disableGameType = false,
  disableMap = false,
}: DashboardFiltersProps) => {
  const handleFilterChange = (key: keyof DashboardFiltersType, value: any) => {
    onFiltersChange({
      ...filters,
      [key]: value,
    });
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4 p-4 border rounded-lg bg-muted/50">
        <div className="space-y-1 min-w-[140px]">
          <Label htmlFor="date-from" className="text-xs">
            Date From
          </Label>
          <Input
            id="date-from"
            type="date"
            value={filters.date_from}
            onChange={e => handleFilterChange('date_from', e.target.value)}
            className="h-8"
          />
        </div>

        <div className="space-y-1 min-w-[140px]">
          <Label htmlFor="date-to" className="text-xs">
            Date To
          </Label>
          <Input
            id="date-to"
            type="date"
            value={filters.date_to}
            onChange={e => handleFilterChange('date_to', e.target.value)}
            className="h-8"
          />
        </div>

        {!disableGameType && (
          <div className="space-y-1 min-w-[140px]">
            <Label htmlFor="game-type" className="text-xs">
              Game Type
            </Label>
            <Select
              value={filters.game_type}
              onValueChange={value => handleFilterChange('game_type', value)}
            >
              <SelectTrigger id="game-type" className="h-8 w-full">
                <SelectValue placeholder="Select game type" />
              </SelectTrigger>
              <SelectContent>
                {GAME_TYPES.map(type => (
                  <SelectItem key={type.value} value={type.value}>
                    {type.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}

        {!disableMap && (
          <div className="space-y-1 min-w-[140px]">
            <Label htmlFor="map" className="text-xs">
              Map
            </Label>
            <Select
              value={filters.map}
              onValueChange={value => handleFilterChange('map', value)}
            >
              <SelectTrigger id="map" className="h-8 w-full">
                <SelectValue placeholder="Select map" />
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
        )}

        <div className="space-y-1 min-w-[140px]">
          <Label htmlFor="match-count" className="text-xs">
            Match Count
          </Label>
          <Select
            value={filters.past_match_count.toString()}
            onValueChange={value =>
              handleFilterChange('past_match_count', parseInt(value))
            }
          >
            <SelectTrigger id="match-count" className="h-8 w-full">
              <SelectValue placeholder="Select count" />
            </SelectTrigger>
            <SelectContent>
              {MATCH_COUNTS.map(count => (
                <SelectItem key={count.value} value={count.value.toString()}>
                  {count.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>
  );
};
