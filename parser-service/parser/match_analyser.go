package parser

import (
	"log"
	"fmt"
	"app/types"
	events "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
)

type MatchAnalyser struct {
	Players map[string]*types.Player
}

func NewMatchAnalyser() *MatchAnalyser {
	return &MatchAnalyser{
		Players: make(map[string]*types.Player),
	}
}

func (m *MatchAnalyser) ProcessKillEvent(kill events.Kill) {
	if kill.Killer == nil {
		log.Printf("Kill event with nil killer: %v", kill)
		return
	}
	
	if kill.Victim == nil {
		log.Printf("Kill event with nil victim: %v", kill)
		return
	}
	
	killerID := fmt.Sprintf("%d", kill.Killer.SteamID64)
	victimID := fmt.Sprintf("%d", kill.Victim.SteamID64)
	
	killer, ok := m.Players[killerID]
	if !ok {
		killer = types.NewPlayer(killerID, kill.Killer.Name)
		m.Players[killerID] = killer
	}
	
	victim, ok := m.Players[victimID]
	if !ok {
		victim = types.NewPlayer(victimID, kill.Victim.Name)
		m.Players[victimID] = victim
	}
	
	killer.RegisterKillEvent(kill)
	victim.RegisterKillEvent(kill)
}

func (m *MatchAnalyser) GetPlayers() map[string]*types.Player {
	return m.Players
}

func (m *MatchAnalyser) GetPlayer(steamID string) (*types.Player, bool) {
	player, exists := m.Players[steamID]
	return player, exists
}

func (m *MatchAnalyser) GetTotalKills() int {
	total := 0
	for _, player := range m.Players {
		total += player.Kills
	}
	return total
} 