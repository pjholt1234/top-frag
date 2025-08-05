package types

import (
	"testing"
	"github.com/stretchr/testify/assert"
	events "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	common "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
)

func TestNewPlayer(t *testing.T) {
	player := NewPlayer("123456789", "TestPlayer")
	
	assert.NotNil(t, player)
	assert.Equal(t, "123456789", player.SteamID)
	assert.Equal(t, "TestPlayer", player.Name)
	assert.Equal(t, 0, player.Kills)
	assert.Equal(t, 0, player.Deaths)
	assert.Equal(t, 0, player.Assists)
	assert.Equal(t, 0, player.Headshots)
	assert.Equal(t, 0, player.Wallbangs)
}

func TestPlayer_KDRatio_ZeroDeaths(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	player.Kills = 5
	player.Deaths = 0
	
	ratio := player.KDRatio()
	assert.Equal(t, 5.0, ratio)
}

func TestPlayer_KDRatio_WithDeaths(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	player.Kills = 10
	player.Deaths = 5
	
	ratio := player.KDRatio()
	assert.Equal(t, 2.0, ratio)
}

func TestPlayer_KDRatio_ZeroKills(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	player.Kills = 0
	player.Deaths = 5
	
	ratio := player.KDRatio()
	assert.Equal(t, 0.0, ratio)
}

func TestPlayer_TotalScore(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	player.Kills = 10
	player.Assists = 5
	
	score := player.TotalScore()
	assert.Equal(t, 15, score)
}

func TestPlayer_TotalScore_Zero(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	
	score := player.TotalScore()
	assert.Equal(t, 0, score)
}

func createMockPlayer(steamID uint64, name string) *common.Player {
	return &common.Player{
		SteamID64: steamID,
		Name:      name,
	}
}

func TestPlayer_RegisterKillEvent_AsKiller(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "TestPlayer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAK47},
		IsHeadshot: true,
		PenetratedObjects: 0,
	}
	
	player.RegisterKillEvent(kill)
	
	assert.Equal(t, 1, player.Kills)
	assert.Equal(t, 0, player.Deaths)
	assert.Equal(t, 1, player.Headshots)
	assert.Equal(t, 0, player.Wallbangs)
}

func TestPlayer_RegisterKillEvent_AsVictim(t *testing.T) {
	player := NewPlayer("456", "TestPlayer")
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "Killer"),
		Victim: createMockPlayer(456, "TestPlayer"),
		Weapon: &common.Equipment{Type: common.EqAK47},
		IsHeadshot: true,
		PenetratedObjects: 0,
	}
	
	player.RegisterKillEvent(kill)
	
	assert.Equal(t, 0, player.Kills)
	assert.Equal(t, 1, player.Deaths)
	assert.Equal(t, 0, player.Headshots)
	assert.Equal(t, 0, player.Wallbangs)
}

func TestPlayer_RegisterKillEvent_Headshot(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "TestPlayer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAWP},
		IsHeadshot: true,
		PenetratedObjects: 0,
	}
	
	player.RegisterKillEvent(kill)
	
	assert.Equal(t, 1, player.Kills)
	assert.Equal(t, 1, player.Headshots)
}

func TestPlayer_RegisterKillEvent_Wallbang(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "TestPlayer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAWP},
		IsHeadshot: false,
		PenetratedObjects: 2,
	}
	
	player.RegisterKillEvent(kill)
	
	assert.Equal(t, 1, player.Kills)
	assert.Equal(t, 0, player.Headshots)
	assert.Equal(t, 1, player.Wallbangs)
}

func TestPlayer_RegisterKillEvent_HeadshotAndWallbang(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "TestPlayer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAWP},
		IsHeadshot: true,
		PenetratedObjects: 1,
	}
	
	player.RegisterKillEvent(kill)
	
	assert.Equal(t, 1, player.Kills)
	assert.Equal(t, 1, player.Headshots)
	assert.Equal(t, 1, player.Wallbangs)
}

func TestPlayer_RegisterKillEvent_NotInvolved(t *testing.T) {
	player := NewPlayer("999", "TestPlayer")
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "Killer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAK47},
		IsHeadshot: false,
		PenetratedObjects: 0,
	}
	
	player.RegisterKillEvent(kill)
	
	assert.Equal(t, 0, player.Kills)
	assert.Equal(t, 0, player.Deaths)
	assert.Equal(t, 0, player.Headshots)
	assert.Equal(t, 0, player.Wallbangs)
}

func TestPlayer_RegisterKillEvent_MultipleKills(t *testing.T) {
	player := NewPlayer("123", "TestPlayer")
	
	kill1 := events.Kill{
		Killer: createMockPlayer(123, "TestPlayer"),
		Victim: createMockPlayer(456, "Victim1"),
		Weapon: &common.Equipment{Type: common.EqAK47},
		IsHeadshot: true,
		PenetratedObjects: 0,
	}
	
	kill2 := events.Kill{
		Killer: createMockPlayer(123, "TestPlayer"),
		Victim: createMockPlayer(789, "Victim2"),
		Weapon: &common.Equipment{Type: common.EqAWP},
		IsHeadshot: false,
		PenetratedObjects: 1,
	}
	
	player.RegisterKillEvent(kill1)
	player.RegisterKillEvent(kill2)
	
	assert.Equal(t, 2, player.Kills)
	assert.Equal(t, 1, player.Headshots)
	assert.Equal(t, 1, player.Wallbangs)
} 