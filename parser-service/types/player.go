package types

import (
	"fmt"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
)

type Player struct {
	SteamID   string
	Name      string
	Kills     int
	Deaths    int
	Assists   int
	Headshots int
	Wallbangs int
}

func NewPlayer(steamID, name string) *Player {
	return &Player{
		SteamID:   steamID,
		Name:      name,
		Kills:     0,
		Deaths:    0,
		Assists:   0,
		Headshots: 0,
		Wallbangs: 0,
	}
}

func (p *Player) RegisterKillEvent(kill events.Kill) {
	// Check if this player is the killer
	if p.SteamID == fmt.Sprintf("%d", kill.Killer.SteamID64) {
		p.registerKill(kill)
	} else if p.SteamID == fmt.Sprintf("%d", kill.Victim.SteamID64) {
		p.registerDeath(kill)
	}
}

func (p *Player) registerDeath(kill events.Kill) {
	p.Deaths++
}

func (p *Player) registerKill(kill events.Kill) {
	p.Kills++

	if kill.IsHeadshot {
		p.Headshots++
	}

	if kill.PenetratedObjects > 0 {
		p.Wallbangs++
	}
}

func (p *Player) KDRatio() float64 {
	if p.Deaths == 0 {
		return float64(p.Kills)
	}
	return float64(p.Kills) / float64(p.Deaths)
}

func (p *Player) TotalScore() int {
	return p.Kills + p.Assists
}