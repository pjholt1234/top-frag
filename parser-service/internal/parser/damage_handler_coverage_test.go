package parser

import (
	"testing"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"

	"parser-service/internal/types"
)

func TestNewDamageHandler(t *testing.T) {
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

	handler := NewDamageHandler(processor, logger)

	assert.NotNil(t, handler)
	assert.Equal(t, processor, handler.processor)
	assert.Equal(t, logger, handler.logger)
}

func TestDamageHandler_HandlePlayerHurt_NilAttacker(t *testing.T) {
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
	handler := NewDamageHandler(processor, logger)

	// Create a PlayerHurt event with nil attacker
	event := events.PlayerHurt{
		Attacker:     nil,
		Player:       &common.Player{SteamID64: 67890},
		HealthDamage: 25,
		ArmorDamage:  10,
		Weapon:       &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerHurt(event)

	assert.Error(t, err)
	parseErr, ok := err.(*types.ParseError)
	assert.True(t, ok)
	assert.Equal(t, types.ErrorTypeValidation, parseErr.Type)
	assert.Equal(t, types.ErrorSeverityWarning, parseErr.Severity)
	assert.Contains(t, parseErr.Message, "attacker is nil")
}

func TestDamageHandler_HandlePlayerHurt_NilVictim(t *testing.T) {
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
	handler := NewDamageHandler(processor, logger)

	// Create a PlayerHurt event with nil victim
	event := events.PlayerHurt{
		Attacker:     &common.Player{SteamID64: 12345},
		Player:       nil,
		HealthDamage: 25,
		ArmorDamage:  10,
		Weapon:       &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerHurt(event)

	assert.Error(t, err)
	parseErr, ok := err.(*types.ParseError)
	assert.True(t, ok)
	assert.Equal(t, types.ErrorTypeValidation, parseErr.Type)
	assert.Equal(t, types.ErrorSeverityWarning, parseErr.Severity)
	assert.Contains(t, parseErr.Message, "victim player is nil")
}

func TestDamageHandler_HandlePlayerHurt_NegativeHealthDamage(t *testing.T) {
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
	handler := NewDamageHandler(processor, logger)

	// Create a PlayerHurt event with negative health damage
	event := events.PlayerHurt{
		Attacker:     &common.Player{SteamID64: 12345},
		Player:       &common.Player{SteamID64: 67890},
		HealthDamage: -10,
		ArmorDamage:  10,
		Weapon:       &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerHurt(event)

	assert.Error(t, err)
	parseErr, ok := err.(*types.ParseError)
	assert.True(t, ok)
	assert.Equal(t, types.ErrorTypeValidation, parseErr.Type)
	assert.Equal(t, types.ErrorSeverityWarning, parseErr.Severity)
	assert.Contains(t, parseErr.Message, "health damage cannot be negative")
}

func TestDamageHandler_HandlePlayerHurt_NegativeArmorDamage(t *testing.T) {
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
	handler := NewDamageHandler(processor, logger)

	// Create a PlayerHurt event with negative armor damage
	event := events.PlayerHurt{
		Attacker:     &common.Player{SteamID64: 12345},
		Player:       &common.Player{SteamID64: 67890},
		HealthDamage: 25,
		ArmorDamage:  -5,
		Weapon:       &common.Equipment{Type: common.EqAK47},
	}

	err := handler.HandlePlayerHurt(event)

	assert.Error(t, err)
	parseErr, ok := err.(*types.ParseError)
	assert.True(t, ok)
	assert.Equal(t, types.ErrorTypeValidation, parseErr.Type)
	assert.Equal(t, types.ErrorSeverityWarning, parseErr.Severity)
	assert.Contains(t, parseErr.Message, "armor damage cannot be negative")
}

func TestDamageHandler_HandlePlayerHurt_ZeroDamage(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players:      make(map[string]*types.Player),
		DamageEvents: []types.DamageEvent{},
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
		currentRound:    1,
	}
	handler := NewDamageHandler(processor, logger)

	// Create a PlayerHurt event with zero damage
	event := events.PlayerHurt{
		Attacker:     &common.Player{SteamID64: 12345},
		Player:       &common.Player{SteamID64: 67890},
		HealthDamage: 0,
		ArmorDamage:  0,
		Weapon:       &common.Equipment{Type: common.EqKnife},
	}

	err := handler.HandlePlayerHurt(event)

	assert.NoError(t, err)
	assert.Len(t, handler.processor.matchState.DamageEvents, 1)

	damageEvent := handler.processor.matchState.DamageEvents[0]
	assert.Equal(t, 0, damageEvent.Damage)
	assert.Equal(t, 0, damageEvent.HealthDamage)
	assert.Equal(t, 0, damageEvent.ArmorDamage)
	assert.Equal(t, "Knife", damageEvent.Weapon)
}

func TestDamageHandler_HandlePlayerHurt_PlayerStateUpdates(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players:      make(map[string]*types.Player),
		DamageEvents: []types.DamageEvent{},
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
		currentRound:    1,
	}
	handler := NewDamageHandler(processor, logger)

	// Pre-populate player states
	processor.playerStates[12345] = &types.PlayerState{
		TotalDamage: 50,
	}
	processor.playerStates[67890] = &types.PlayerState{
		DamageTaken: 30,
	}

	// Create a PlayerHurt event
	event := events.PlayerHurt{
		Attacker:     &common.Player{SteamID64: 12345},
		Player:       &common.Player{SteamID64: 67890},
		HealthDamage: 25,
		ArmorDamage:  10,
		Weapon:       &common.Equipment{Type: common.EqM4A4},
	}

	err := handler.HandlePlayerHurt(event)

	assert.NoError(t, err)
	assert.Len(t, handler.processor.matchState.DamageEvents, 1)

	// Verify player state updates
	attackerState := processor.playerStates[12345]
	victimState := processor.playerStates[67890]

	assert.Equal(t, 75, attackerState.TotalDamage) // 50 + 25
	assert.Equal(t, 55, victimState.DamageTaken)   // 30 + 25
}

func TestDamageHandler_HandlePlayerHurt_PlayerStateNotExists(t *testing.T) {
	logger := logrus.New()
	matchState := &types.MatchState{
		Players:      make(map[string]*types.Player),
		DamageEvents: []types.DamageEvent{},
	}
	processor := &EventProcessor{
		matchState:      matchState,
		logger:          logger,
		playerStates:    make(map[uint64]*types.PlayerState),
		teamAssignments: make(map[string]string),
		currentTick:     1000,
		currentRound:    1,
	}
	handler := NewDamageHandler(processor, logger)

	// Don't pre-populate player states
	event := events.PlayerHurt{
		Attacker:     &common.Player{SteamID64: 12345},
		Player:       &common.Player{SteamID64: 67890},
		HealthDamage: 25,
		ArmorDamage:  10,
		Weapon:       &common.Equipment{Type: common.EqM4A4},
	}

	err := handler.HandlePlayerHurt(event)

	assert.NoError(t, err)
	assert.Len(t, handler.processor.matchState.DamageEvents, 1)

	// Should not crash when player states don't exist
	damageEvent := handler.processor.matchState.DamageEvents[0]
	assert.Equal(t, 25, damageEvent.Damage)
	assert.Equal(t, 25, damageEvent.HealthDamage)
	assert.Equal(t, 10, damageEvent.ArmorDamage)
}

func TestDamageHandler_HandlePlayerHurt_WeaponTypes(t *testing.T) {
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
				Players:      make(map[string]*types.Player),
				DamageEvents: []types.DamageEvent{},
			}
			processor := &EventProcessor{
				matchState:      matchState,
				logger:          logger,
				playerStates:    make(map[uint64]*types.PlayerState),
				teamAssignments: make(map[string]string),
				currentTick:     1000,
				currentRound:    1,
			}
			handler := NewDamageHandler(processor, logger)

			event := events.PlayerHurt{
				Attacker:     &common.Player{SteamID64: 12345},
				Player:       &common.Player{SteamID64: 67890},
				HealthDamage: 25,
				ArmorDamage:  10,
				Weapon:       &common.Equipment{Type: wt.weaponType},
			}

			err := handler.HandlePlayerHurt(event)
			assert.NoError(t, err)
			assert.Len(t, handler.processor.matchState.DamageEvents, 1)
			assert.Equal(t, wt.expectedName, handler.processor.matchState.DamageEvents[0].Weapon)
		})
	}
}
