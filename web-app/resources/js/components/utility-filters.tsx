import { Card, CardContent } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';

interface Player {
  steam_id: string;
  name: string;
}

interface UtilityFiltersProps {
  players: Player[];
  rounds: number[];
  filters: {
    playerSteamId: string;
    roundNumber: string;
  };
  onFiltersChange: (filters: {
    playerSteamId: string;
    roundNumber: string;
  }) => void;
}

export function UtilityFilters({
  players,
  rounds,
  filters,
  onFiltersChange,
}: UtilityFiltersProps) {
  const handlePlayerChange = (value: string) => {
    onFiltersChange({
      ...filters,
      playerSteamId: value,
    });
  };

  const handleRoundChange = (value: string) => {
    onFiltersChange({
      ...filters,
      roundNumber: value,
    });
  };

  return (
    <Card className="mb-4">
      <CardContent className="flex gap-4">
        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="player-select">Player</Label>
          <Select
            value={filters.playerSteamId}
            onValueChange={handlePlayerChange}
          >
            <SelectTrigger className="h-8 w-full">
              <SelectValue placeholder="Select a player" />
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

        <div className="space-y-1 min-w-[120px]">
          <Label htmlFor="round-select">Round</Label>
          <Select value={filters.roundNumber} onValueChange={handleRoundChange}>
            <SelectTrigger className="h-8 w-full">
              <SelectValue placeholder="All rounds" />
            </SelectTrigger>
            <SelectContent>
              {rounds.map(round => (
                <SelectItem key={round} value={round.toString()}>
                  {round === 'all' ? 'All rounds' : `Round ${round}`}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </CardContent>
    </Card>
  );
}
