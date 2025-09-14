package parser

import (
	"testing"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"

	"parser-service/internal/types"
)

func TestNewGunfightHandler(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players: make(map[string]*types.Player),
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
	}

	handler := NewGunfightHandler(processor, logger)

	assert.NotNil(t, handler)
	assert.Equal(t, processor, handler.processor)
	assert.Equal(t, logger, handler.logger)
}

func TestGunfightHandler_HandlePlayerKilled_NilKiller(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players: make(map[string]*types.Player),
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
	}
	handler := NewGunfightHandler(processor, logger)

	// Create a Kill event with nil killer
	event := events.Kill{
		Killer:            nil,
		Victim:            &common.Player{SteamID64: 67890},
		Assister:          nil,
		IsHeadshot:        false,
		PenetratedObjects: 0,
		Weapon:            &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerKilled(event)

	assert.Error(t, err)
	parseErr, ok := err.(*types.ParseError)
	assert.True(t, ok)
	assert.Equal(t, types.ErrorTypeValidation, parseErr.Type)
	assert.Equal(t, types.ErrorSeverityWarning, parseErr.Severity)
	assert.Contains(t, parseErr.Message, "killer is nil")
}

func TestGunfightHandler_HandlePlayerKilled_NilVictim(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players: make(map[string]*types.Player),
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
	}
	handler := NewGunfightHandler(processor, logger)

	// Create a Kill event with nil victim
	event := events.Kill{
		Killer:            &common.Player{SteamID64: 12345},
		Victim:            nil,
		Assister:          nil,
		IsHeadshot:        false,
		PenetratedObjects: 0,
		Weapon:            &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerKilled(event)

	assert.Error(t, err)
	parseErr, ok := err.(*types.ParseError)
	assert.True(t, ok)
	assert.Equal(t, types.ErrorTypeValidation, parseErr.Type)
	assert.Equal(t, types.ErrorSeverityWarning, parseErr.Severity)
	assert.Contains(t, parseErr.Message, "victim is nil")
}

func TestGunfightHandler_HandlePlayerKilled_NegativePenetratedObjects(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players: make(map[string]*types.Player),
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
	}
	handler := NewGunfightHandler(processor, logger)

	// Create a Kill event with negative penetrated objects
	event := events.Kill{
		Killer:            &common.Player{SteamID64: 12345},
		Victim:            &common.Player{SteamID64: 67890},
		Assister:          nil,
		IsHeadshot:        false,
		PenetratedObjects: -1,
		Weapon:            &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerKilled(event)

	assert.Error(t, err)
	parseErr, ok := err.(*types.ParseError)
	assert.True(t, ok)
	assert.Equal(t, types.ErrorTypeValidation, parseErr.Type)
	assert.Equal(t, types.ErrorSeverityWarning, parseErr.Severity)
	assert.Contains(t, parseErr.Message, "penetrated objects cannot be negative")
}

func TestGunfightHandler_HandlePlayerKilled_ValidKill(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players:        make(map[string]*types.Player),
		GunfightEvents: []types.GunfightEvent{},
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
		currentRound:    1,
	}
	handler := NewGunfightHandler(processor, logger)

	// Create a valid Kill event
	event := events.Kill{
		Killer:            &common.Player{SteamID64: 12345},
		Victim:            &common.Player{SteamID64: 67890},
		Assister:          nil,
		IsHeadshot:        true,
		PenetratedObjects: 0,
		Weapon:            &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerKilled(event)

	assert.NoError(t, err)
	assert.Len(t, handler.processor.matchState.GunfightEvents, 1)

	gunfightEvent := handler.processor.matchState.GunfightEvents[0]
	assert.Equal(t, 1, gunfightEvent.RoundNumber)
	assert.Equal(t, int64(1000), gunfightEvent.TickTimestamp)
	assert.Equal(t, "12345", gunfightEvent.Player1SteamID)
	assert.Equal(t, "67890", gunfightEvent.Player2SteamID)
	assert.True(t, gunfightEvent.Headshot)
	assert.False(t, gunfightEvent.Wallbang)
	assert.Equal(t, 0, gunfightEvent.PenetratedObjects)
}

func TestGunfightHandler_HandlePlayerKilled_WallbangKill(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players:        make(map[string]*types.Player),
		GunfightEvents: []types.GunfightEvent{},
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
		currentRound:    1,
	}
	handler := NewGunfightHandler(processor, logger)

	// Create a wallbang Kill event
	event := events.Kill{
		Killer:            &common.Player{SteamID64: 12345},
		Victim:            &common.Player{SteamID64: 67890},
		Assister:          nil,
		IsHeadshot:        false,
		PenetratedObjects: 2,
		Weapon:            &common.Equipment{Type: common.EqAWP},
	}

	err := handler.HandlePlayerKilled(event)

	assert.NoError(t, err)
	assert.Len(t, handler.processor.matchState.GunfightEvents, 1)

	gunfightEvent := handler.processor.matchState.GunfightEvents[0]
	assert.False(t, gunfightEvent.Headshot)
	assert.True(t, gunfightEvent.Wallbang)
	assert.Equal(t, 2, gunfightEvent.PenetratedObjects)
}

func TestGunfightHandler_HandlePlayerKilled_PlayerStateUpdates(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players:        make(map[string]*types.Player),
		GunfightEvents: []types.GunfightEvent{},
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
		currentRound:    1,
	}
	handler := NewGunfightHandler(processor, logger)

	// Pre-populate player states
	processor.playerStates[12345] = &types.PlayerState{
		Kills: 5,
	}
	processor.playerStates[67890] = &types.PlayerState{
		Deaths: 3,
	}

	// Create a Kill event
	event := events.Kill{
		Killer:            &common.Player{SteamID64: 12345},
		Victim:            &common.Player{SteamID64: 67890},
		Assister:          nil,
		IsHeadshot:        true,
		PenetratedObjects: 0,
		Weapon:            &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerKilled(event)

	assert.NoError(t, err)
	assert.Len(t, handler.processor.matchState.GunfightEvents, 1)

	// Verify player state updates
	killerState := processor.playerStates[12345]
	victimState := processor.playerStates[67890]

	assert.Equal(t, 6, killerState.Kills)  // 5 + 1
	assert.Equal(t, 4, victimState.Deaths) // 3 + 1
}

func TestGunfightHandler_HandlePlayerKilled_PlayerStateNotExists(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players:        make(map[string]*types.Player),
		GunfightEvents: []types.GunfightEvent{},
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
		currentRound:    1,
	}
	handler := NewGunfightHandler(processor, logger)

	// Don't pre-populate player states
	event := events.Kill{
		Killer:            &common.Player{SteamID64: 12345},
		Victim:            &common.Player{SteamID64: 67890},
		Assister:          nil,
		IsHeadshot:        false,
		PenetratedObjects: 0,
		Weapon:            &common.Equipment{Type: common.EqM4A4},
	}

	err := handler.HandlePlayerKilled(event)

	assert.NoError(t, err)
	assert.Len(t, handler.processor.matchState.GunfightEvents, 1)

	// Should not crash when player states don't exist
	gunfightEvent := handler.processor.matchState.GunfightEvents[0]
	assert.Equal(t, "12345", gunfightEvent.Player1SteamID)
	assert.Equal(t, "67890", gunfightEvent.Player2SteamID)
}

func TestGunfightHandler_HandlePlayerKilled_WeaponTypes(t *testing.T) {
	weaponTests := []struct {
		weaponType   common.EquipmentType
		expectedName string
	}{
		{common.EqAK47, "AK-47"},
		{common.EqM4A4, "M4A4"},
		{common.EqAWP, "AWP"},
		{common.EqMolotov, "molotov"},
		{common.EqIncendiary, "incendiary"},
		{common.EqKnife, "Knife"},
		{common.EqUSP, "USP-S"},
		{common.EqGlock, "Glock-18"},
	}

	for _, wt := range weaponTests {
		t.Run(wt.expectedName, func(t *testing.T) {
			logger := logrus.New()
			matchState := &types.MatchState{
				Players:        make(map[string]*types.Player),
				GunfightEvents: []types.GunfightEvent{},
			}
			processor := &EventProcessor{
				matchState:      matchState,
				logger:          logger,
				playerStates:    make(map[uint64]*types.PlayerState),
				teamAssignments: make(map[string]string),
				currentTick:     1000,
				currentRound:    1,
			}
			handler := NewGunfightHandler(processor, logger)

			event := events.Kill{
				Killer:            &common.Player{SteamID64: 12345},
				Victim:            &common.Player{SteamID64: 67890},
				Assister:          nil,
				IsHeadshot:        false,
				PenetratedObjects: 0,
				Weapon:            &common.Equipment{Type: wt.weaponType},
			}

			err := handler.HandlePlayerKilled(event)
			assert.NoError(t, err)
			assert.Len(t, handler.processor.matchState.GunfightEvents, 1)
			// Weapon is not stored in GunfightEvent, it's in Player1Weapon/Player2Weapon
			assert.Equal(t, wt.expectedName, handler.processor.matchState.GunfightEvents[0].Player1Weapon)
		})
	}
}

func TestGunfightHandler_HandlePlayerKilled_EdgeCases(t *testing.T) {
	t.Run("zero_penetrated_objects", func(t *testing.T) {
		logger := logrus.New()
		matchState := &types.MatchState{
			Players:        make(map[string]*types.Player),
			GunfightEvents: []types.GunfightEvent{},
		}
		processor := &EventProcessor{
			matchState:      matchState,
			logger:          logger,
			playerStates:    make(map[uint64]*types.PlayerState),
			teamAssignments: make(map[string]string),
			currentTick:     1000,
			currentRound:    1,
		}
		handler := NewGunfightHandler(processor, logger)

		event := events.Kill{
			Killer:            &common.Player{SteamID64: 12345},
			Victim:            &common.Player{SteamID64: 67890},
			Assister:          nil,
			IsHeadshot:        false,
			PenetratedObjects: 0,
			Weapon:            &common.Equipment{Type: common.EqAK47},
		}

		err := handler.HandlePlayerKilled(event)
		assert.NoError(t, err)
		assert.Len(t, handler.processor.matchState.GunfightEvents, 1)
		assert.Equal(t, 0, handler.processor.matchState.GunfightEvents[0].PenetratedObjects)
	})

	t.Run("high_penetrated_objects", func(t *testing.T) {
		logger := logrus.New()
		matchState := &types.MatchState{
			Players:        make(map[string]*types.Player),
			GunfightEvents: []types.GunfightEvent{},
		}
		processor := &EventProcessor{
			matchState:      matchState,
			logger:          logger,
			playerStates:    make(map[uint64]*types.PlayerState),
			teamAssignments: make(map[string]string),
			currentTick:     1000,
			currentRound:    1,
		}
		handler := NewGunfightHandler(processor, logger)

		event := events.Kill{
			Killer:            &common.Player{SteamID64: 12345},
			Victim:            &common.Player{SteamID64: 67890},
			Assister:          nil,
			IsHeadshot:        false,
			PenetratedObjects: 5,
			Weapon:            &common.Equipment{Type: common.EqAWP},
		}

		err := handler.HandlePlayerKilled(event)
		assert.NoError(t, err)
		assert.Len(t, handler.processor.matchState.GunfightEvents, 1)
		assert.Equal(t, 5, handler.processor.matchState.GunfightEvents[0].PenetratedObjects)
		assert.True(t, handler.processor.matchState.GunfightEvents[0].Wallbang)
	})
}
