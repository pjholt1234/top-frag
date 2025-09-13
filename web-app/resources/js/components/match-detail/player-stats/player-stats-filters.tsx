import { Card, CardContent } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

interface Player {
  steam_id: string;
  name: string;
}

interface PlayerStatsFiltersProps {
  players: Player[];
  filters: {
    playerSteamId: string;
  };
  onFiltersChange: (filters: { playerSteamId: string }) => void;
}

export function PlayerStatsFilters({
  players,
  filters,
  onFiltersChange,
}: PlayerStatsFiltersProps) {
  const handlePlayerChange = (playerSteamId: string) => {
    onFiltersChange({
      playerSteamId,
    });
  };

  return (
    <Card>
      <CardContent>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">
              Select Player
            </label>
            <Select
              value={filters.playerSteamId}
              onValueChange={handlePlayerChange}
            >
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Choose a player..." />
              </SelectTrigger>
              <SelectContent>
                {players.map(player => (
                  <SelectItem key={player.steam_id} value={player.steam_id}>
                    {player.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
