import { Card, CardContent } from '@/components/ui/card';
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
}: DashboardFiltersProps) => {
    const handleFilterChange = (key: keyof DashboardFiltersType, value: any) => {
        onFiltersChange({
            ...filters,
            [key]: value,
        });
    };

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div className="space-y-2">
                <Label htmlFor="date-from">Date From</Label>
                <Input
                    id="date-from"
                    type="date"
                    value={filters.date_from}
                    onChange={(e) => handleFilterChange('date_from', e.target.value)}
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor="date-to">Date To</Label>
                <Input
                    id="date-to"
                    type="date"
                    value={filters.date_to}
                    onChange={(e) => handleFilterChange('date_to', e.target.value)}
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor="game-type">Game Type</Label>
                <Select
                    value={filters.game_type}
                    onValueChange={(value) => handleFilterChange('game_type', value)}
                >
                    <SelectTrigger id="game-type" className="w-full">
                        <SelectValue placeholder="Select game type" />
                    </SelectTrigger>
                    <SelectContent>
                        {GAME_TYPES.map((type) => (
                            <SelectItem key={type.value} value={type.value}>
                                {type.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-2">
                <Label htmlFor="map">Map</Label>
                <Select
                    value={filters.map}
                    onValueChange={(value) => handleFilterChange('map', value)}
                >
                    <SelectTrigger id="map" className="w-full">
                        <SelectValue placeholder="Select map" />
                    </SelectTrigger>
                    <SelectContent>
                        {MAPS.map((map) => (
                            <SelectItem key={map.value} value={map.value}>
                                {map.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-2">
                <Label htmlFor="match-count">Match Count</Label>
                <Select
                    value={filters.past_match_count.toString()}
                    onValueChange={(value) =>
                        handleFilterChange('past_match_count', parseInt(value))
                    }
                >
                    <SelectTrigger id="match-count" className="w-full">
                        <SelectValue placeholder="Select count" />
                    </SelectTrigger>
                    <SelectContent>
                        {MATCH_COUNTS.map((count) => (
                            <SelectItem key={count.value} value={count.value.toString()}>
                                {count.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
};

