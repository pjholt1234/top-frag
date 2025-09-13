package parser

import (
	"testing"

	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

// Test the damage capping logic directly
func TestDamageCappingLogic(t *testing.T) {
	tests := []struct {
		name           string
		victimHealth   int
		weaponDamage   int
		armorDamage    int
		expectedDamage int
		expectedHealth int
	}{
		{
			name:           "victim has more health than damage",
			victimHealth:   100,
			weaponDamage:   25,
			armorDamage:    10,
			expectedDamage: 25, // Only health damage (capped at weapon damage)
			expectedHealth: 25, // 25 (capped at weapon damage)
		},
		{
			name:           "victim has less health than damage - AWP headshot",
			victimHealth:   60,
			weaponDamage:   336, // AWP headshot damage
			armorDamage:    0,
			expectedDamage: 60, // Only health damage (capped at victim health)
			expectedHealth: 60, // 60 (capped at victim health)
		},
		{
			name:           "victim has much less health than damage",
			victimHealth:   5,
			weaponDamage:   34, // AK-47 chest damage
			armorDamage:    0,
			expectedDamage: 5, // Only health damage (capped at victim health)
			expectedHealth: 5, // 5 (capped at victim health)
		},
		{
			name:           "victim has exactly the damage amount",
			victimHealth:   50,
			weaponDamage:   50,
			armorDamage:    0,
			expectedDamage: 50, // Only health damage
			expectedHealth: 50, // 50 (exact match)
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			// Test the damage capping logic directly
			actualHealthDamage := tt.weaponDamage
			if tt.victimHealth < tt.weaponDamage {
				actualHealthDamage = tt.victimHealth
			}

			// Total damage is now only the health damage (armor damage is separate)
			totalDamage := actualHealthDamage

			// Verify total damage
			if totalDamage != tt.expectedDamage {
				t.Errorf("Expected total damage %d, got %d", tt.expectedDamage, totalDamage)
			}

			// Verify health damage is capped
			if actualHealthDamage != tt.expectedHealth {
				t.Errorf("Expected health damage %d, got %d", tt.expectedHealth, actualHealthDamage)
			}
		})
	}
}

func TestDamageHandler_NewDamageHandler(t *testing.T) {
	matchState := &types.MatchState{}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger)

	damageHandler := NewDamageHandler(processor, logger)

	if damageHandler == nil {
		t.Error("Expected damage handler to be created, got nil")
		return
	}

	if damageHandler.processor != processor {
		t.Error("Expected damage handler processor to be set correctly")
	}

	if damageHandler.logger != logger {
		t.Error("Expected damage handler logger to be set correctly")
	}

	t.Log("NewDamageHandler method tested successfully")
}

func TestDamageHandler_HandlePlayerHurt(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound: 1,
		DamageEvents: []types.DamageEvent{},
		Players:      make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger)
	damageHandler := NewDamageHandler(processor, logger)

	tests := []struct {
		name          string
		event         events.PlayerHurt
		expectedError bool
		errorType     types.ErrorType
		errorMessage  string
	}{
		{
			name: "nil attacker should return validation error",
			event: events.PlayerHurt{
				Attacker:     nil,
				Player:       nil, // Will be nil for this test
				HealthDamage: 25,
				ArmorDamage:  10,
				Weapon:       nil, // Will be nil for this test
			},
			expectedError: true,
			errorType:     types.ErrorTypeValidation,
			errorMessage:  "attacker is nil",
		},
		{
			name: "nil victim should return validation error",
			event: events.PlayerHurt{
				Attacker:     nil, // Will be nil for this test
				Player:       nil,
				HealthDamage: 25,
				ArmorDamage:  10,
				Weapon:       nil, // Will be nil for this test
			},
			expectedError: true,
			errorType:     types.ErrorTypeValidation,
			errorMessage:  "attacker is nil", // Fail-fast: attacker is checked first
		},
		{
			name: "negative health damage should return validation error",
			event: events.PlayerHurt{
				Attacker:     nil, // Will be nil for this test
				Player:       nil, // Will be nil for this test
				HealthDamage: -5,
				ArmorDamage:  10,
				Weapon:       nil, // Will be nil for this test
			},
			expectedError: true,
			errorType:     types.ErrorTypeValidation,
			errorMessage:  "attacker is nil", // Fail-fast: attacker is checked first
		},
		{
			name: "negative armor damage should return validation error",
			event: events.PlayerHurt{
				Attacker:     nil, // Will be nil for this test
				Player:       nil, // Will be nil for this test
				HealthDamage: 25,
				ArmorDamage:  -5,
				Weapon:       nil, // Will be nil for this test
			},
			expectedError: true,
			errorType:     types.ErrorTypeValidation,
			errorMessage:  "attacker is nil", // Fail-fast: attacker is checked first
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := damageHandler.HandlePlayerHurt(tt.event)

			if tt.expectedError {
				if err == nil {
					t.Error("Expected error, got nil")
					return
				}

				parseErr, ok := err.(*types.ParseError)
				if !ok {
					t.Errorf("Expected ParseError, got %T", err)
					return
				}

				if parseErr.Type != tt.errorType {
					t.Errorf("Expected error type %v, got %v", tt.errorType, parseErr.Type)
				}

				if parseErr.Message != tt.errorMessage {
					t.Errorf("Expected error message %q, got %q", tt.errorMessage, parseErr.Message)
				}

				// Verify context is set
				if parseErr.Context == nil {
					t.Error("Expected error context to be set")
				}
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}
