package parser

import (
	"testing"
	"app/types"
	"github.com/stretchr/testify/assert"
	events "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	common "github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
)

func TestNewMatchAnalyser(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	assert.NotNil(t, analyser)
	assert.NotNil(t, analyser.Players)
	assert.Equal(t, 0, len(analyser.Players))
}

func TestMatchAnalyser_GetPlayers(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	player1 := types.NewPlayer("123", "Player1")
	player2 := types.NewPlayer("456", "Player2")
	
	analyser.Players["123"] = player1
	analyser.Players["456"] = player2
	
	players := analyser.GetPlayers()
	
	assert.Equal(t, 2, len(players))
	assert.Equal(t, player1, players["123"])
	assert.Equal(t, player2, players["456"])
}

func TestMatchAnalyser_GetPlayer(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	player := types.NewPlayer("123", "TestPlayer")
	analyser.Players["123"] = player
	
	retrievedPlayer, exists := analyser.GetPlayer("123")
	assert.True(t, exists)
	assert.Equal(t, player, retrievedPlayer)
	
	_, exists = analyser.GetPlayer("999")
	assert.False(t, exists)
}

func TestMatchAnalyser_GetPlayer_EmptyMap(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	_, exists := analyser.GetPlayer("123")
	assert.False(t, exists)
}


func TestMatchAnalyser_GetTotalKills(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	assert.Equal(t, 0, analyser.GetTotalKills())
	
	player1 := types.NewPlayer("123", "Player1")
	player1.Kills = 5
	analyser.Players["123"] = player1
	
	player2 := types.NewPlayer("456", "Player2")
	player2.Kills = 3
	analyser.Players["456"] = player2
	
	assert.Equal(t, 8, analyser.GetTotalKills())
}

func createMockPlayer(steamID uint64, name string) *common.Player {
	return &common.Player{
		SteamID64: steamID,
		Name:      name,
	}
}

func TestMatchAnalyser_ProcessKillEvent_NilKiller(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	kill := events.Kill{
		Killer: nil,
		Victim: createMockPlayer(456, "Victim"),
	}
	
	analyser.ProcessKillEvent(kill)
	
	assert.Equal(t, 0, len(analyser.Players))
}

func TestMatchAnalyser_ProcessKillEvent_NilVictim(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "Killer"),
		Victim: nil,
	}
	
	analyser.ProcessKillEvent(kill)
	
	assert.Equal(t, 0, len(analyser.Players))
}

func TestMatchAnalyser_ProcessKillEvent_ValidKill(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "Killer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAK47},
		IsHeadshot: true,
		PenetratedObjects: 0,
	}
	
	analyser.ProcessKillEvent(kill)
	
	assert.Equal(t, 2, len(analyser.Players))
	
	killer, exists := analyser.GetPlayer("123")
	assert.True(t, exists)
	assert.Equal(t, 1, killer.Kills)
	assert.Equal(t, 0, killer.Deaths)
	assert.Equal(t, 1, killer.Headshots)
	assert.Equal(t, 0, killer.Wallbangs)
	
	victim, exists := analyser.GetPlayer("456")
	assert.True(t, exists)
	assert.Equal(t, 0, victim.Kills)
	assert.Equal(t, 1, victim.Deaths)
	assert.Equal(t, 0, victim.Headshots)
	assert.Equal(t, 0, victim.Wallbangs)
}

func TestMatchAnalyser_ProcessKillEvent_WithWallbang(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "Killer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAWP},
		IsHeadshot: false,
		PenetratedObjects: 2,
	}
	
	analyser.ProcessKillEvent(kill)
	
	killer, _ := analyser.GetPlayer("123")
	assert.Equal(t, 1, killer.Kills)
	assert.Equal(t, 0, killer.Headshots)
	assert.Equal(t, 1, killer.Wallbangs)
}

func TestMatchAnalyser_ProcessKillEvent_MultipleKills(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	kill1 := events.Kill{
		Killer: createMockPlayer(123, "Killer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAK47},
		IsHeadshot: true,
	}
	
	kill2 := events.Kill{
		Killer: createMockPlayer(123, "Killer"),
		Victim: createMockPlayer(789, "Victim2"),
		Weapon: &common.Equipment{Type: common.EqM4A4},
		IsHeadshot: false,
	}
	
	analyser.ProcessKillEvent(kill1)
	analyser.ProcessKillEvent(kill2)
	
	assert.Equal(t, 3, len(analyser.Players))
	
	killer, _ := analyser.GetPlayer("123")
	assert.Equal(t, 2, killer.Kills)
	assert.Equal(t, 1, killer.Headshots)
	
	victim1, _ := analyser.GetPlayer("456")
	assert.Equal(t, 1, victim1.Deaths)
	
	victim2, _ := analyser.GetPlayer("789")
	assert.Equal(t, 1, victim2.Deaths)
}

func TestMatchAnalyser_ProcessKillEvent_ExistingPlayers(t *testing.T) {
	analyser := NewMatchAnalyser()
	
	existingPlayer := types.NewPlayer("123", "ExistingPlayer")
	existingPlayer.Kills = 5
	analyser.Players["123"] = existingPlayer
	
	kill := events.Kill{
		Killer: createMockPlayer(123, "ExistingPlayer"),
		Victim: createMockPlayer(456, "Victim"),
		Weapon: &common.Equipment{Type: common.EqAK47},
		IsHeadshot: false,
		PenetratedObjects: 0,
	}
	
	analyser.ProcessKillEvent(kill)
	
	assert.Equal(t, 2, len(analyser.Players))
	
	killer, _ := analyser.GetPlayer("123")
	assert.Equal(t, 6, killer.Kills)
	assert.Equal(t, "ExistingPlayer", killer.Name)
} 