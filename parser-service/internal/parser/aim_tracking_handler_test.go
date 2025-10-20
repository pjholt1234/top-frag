package parser

import (
	"strings"
	"testing"

	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

func TestNewAimTrackingHandler(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound: 1,
		Players:      make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)

	handler := NewAimTrackingHandler(processor, logger)

	if handler == nil {
		t.Fatal("Expected handler to be created")
	}

	if handler.processor != processor {
		t.Error("Expected processor to be set")
	}

	if handler.logger != logger {
		t.Error("Expected logger to be set")
	}

	if len(handler.shootingData) != 0 {
		t.Error("Expected shooting data to be empty initially")
	}
}

func TestAimTrackingHandler_HandleWeaponFire(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound: 1,
		Players:      make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)
	handler := NewAimTrackingHandler(processor, logger)

	tests := []struct {
		name          string
		event         events.WeaponFire
		expectedError bool
		errorMessage  string
	}{
		{
			name: "nil shooter should return validation error",
			event: events.WeaponFire{
				Shooter: nil,
				Weapon:  nil,
			},
			expectedError: true,
			errorMessage:  "shooter is nil",
		},
		{
			name: "nil weapon should return validation error",
			event: events.WeaponFire{
				Shooter: &common.Player{},
				Weapon:  nil,
			},
			expectedError: true,
			errorMessage:  "weapon is nil",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := handler.HandleWeaponFire(tt.event)

			if tt.expectedError {
				if err == nil {
					t.Error("Expected error but got nil")
					return
				}

				if !strings.Contains(err.Error(), tt.errorMessage) {
					t.Errorf("Expected error message to contain '%s', got '%s'", tt.errorMessage, err.Error())
				}
			} else {
				if err != nil {
					t.Errorf("Expected no error but got: %v", err)
				}
			}
		})
	}
}

func TestAimTrackingHandler_GetShootingData(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound: 1,
		Players:      make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)
	handler := NewAimTrackingHandler(processor, logger)

	// Initially should be empty
	data := handler.GetShootingData()
	if len(data) != 0 {
		t.Error("Expected empty shooting data initially")
	}
}

func TestAimTrackingHandler_ClearShootingData(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound: 1,
		Players:      make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)
	handler := NewAimTrackingHandler(processor, logger)

	// Add some dummy data
	handler.shootingData = append(handler.shootingData, types.PlayerShootingData{
		PlayerID: "test",
	})

	// Clear data
	handler.ClearShootingData()

	// Should be empty now
	if len(handler.shootingData) != 0 {
		t.Error("Expected shooting data to be cleared")
	}
}
