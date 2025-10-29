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

interface AimTrackingFiltersProps {
  players: Player[];
  selectedPlayer: string;
  onPlayerChange: (playerSteamId: string) => void;
}

export function AimTrackingFilters({
  players,
  selectedPlayer,
  onPlayerChange,
}: AimTrackingFiltersProps) {
  return (
    <Card>
      <CardContent>
        <div>
          <label className="block text-sm font-medium text-gray-300 mb-2">
            Select Player
          </label>
          <Select value={selectedPlayer} onValueChange={onPlayerChange}>
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
      </CardContent>
    </Card>
  );
}
