package parser

import (
	"parser-service/internal/types"
	"testing"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

func TestMatchHandler_HandleRoundStart_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil match state should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: &EventProcessor{
						matchState: nil,
					},
					logger: logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil grenade handler should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: &EventProcessor{
						matchState: &types.MatchState{},
						grenadeHandler: &GrenadeHandler{
							movementService: nil,
						},
					},
					logger: logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil movement service should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: &EventProcessor{
						matchState: &types.MatchState{},
						grenadeHandler: &GrenadeHandler{
							movementService: nil,
						},
					},
					logger: logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				matchState := &types.MatchState{
					Players:     make(map[string]*types.Player),
					RoundEvents: make([]types.RoundEvent, 0),
				}
				processor := &EventProcessor{
					matchState: matchState,
					grenadeHandler: &GrenadeHandler{
						movementService: &MovementStateService{},
					},
					playerStates: make(map[uint64]*types.PlayerState),
				}
				return NewMatchHandler(processor, logrus.New())
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()
			event := events.RoundStart{}

			err := handler.HandleRoundStart(event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}

func TestMatchHandler_HandleRoundEnd_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil match state should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: &EventProcessor{
						matchState: nil,
					},
					logger: logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				matchState := &types.MatchState{
					Players:     make(map[string]*types.Player),
					RoundEvents: make([]types.RoundEvent, 0),
				}
				processor := &EventProcessor{
					matchState: matchState,
				}
				return NewMatchHandler(processor, logrus.New())
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()
			event := events.RoundEnd{
				Winner: common.TeamCounterTerrorists,
			}

			err := handler.HandleRoundEnd(event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}

func TestMatchHandler_HandleBombPlanted_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				processor := &EventProcessor{}
				return NewMatchHandler(processor, logrus.New())
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()
			event := events.BombPlanted{}

			err := handler.HandleBombPlanted(event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}

func TestMatchHandler_HandleBombDefused_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				processor := &EventProcessor{}
				return NewMatchHandler(processor, logrus.New())
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()
			event := events.BombDefused{}

			err := handler.HandleBombDefused(event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}

func TestMatchHandler_HandleBombExplode_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				processor := &EventProcessor{}
				return NewMatchHandler(processor, logrus.New())
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()
			event := events.BombExplode{}

			err := handler.HandleBombExplode(event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}

func TestMatchHandler_HandlePlayerConnect_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		event       events.PlayerConnect
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			event: events.PlayerConnect{
				Player: &common.Player{},
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil match state should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: &EventProcessor{
						matchState: nil,
					},
					logger: logrus.New(),
				}
			},
			event: events.PlayerConnect{
				Player: &common.Player{},
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil player should return validation error",
			setup: func() *MatchHandler {
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				processor := &EventProcessor{
					matchState: matchState,
				}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.PlayerConnect{
				Player: nil,
			},
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				processor := &EventProcessor{
					matchState:      matchState,
					playerStates:    make(map[uint64]*types.PlayerState),
					teamAssignments: make(map[string]string),
					logger:          logrus.New(),
				}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.PlayerConnect{
				Player: &common.Player{
					SteamID64: 123,
					Name:      "TestPlayer",
					Team:      common.TeamCounterTerrorists,
				},
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()

			err := handler.HandlePlayerConnect(tt.event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}

func TestMatchHandler_HandlePlayerDisconnected_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		event       events.PlayerDisconnected
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			event: events.PlayerDisconnected{
				Player: &common.Player{},
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil player should return validation error",
			setup: func() *MatchHandler {
				processor := &EventProcessor{}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.PlayerDisconnected{
				Player: nil,
			},
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				processor := &EventProcessor{}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.PlayerDisconnected{
				Player: &common.Player{
					SteamID64: 123,
					Name:      "TestPlayer",
				},
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()

			err := handler.HandlePlayerDisconnected(tt.event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}

func TestMatchHandler_HandlePlayerTeamChange_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		event       events.PlayerTeamChange
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			event: events.PlayerTeamChange{
				Player: &common.Player{},
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil match state should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: &EventProcessor{
						matchState: nil,
					},
					logger: logrus.New(),
				}
			},
			event: events.PlayerTeamChange{
				Player: &common.Player{},
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil player should return validation error",
			setup: func() *MatchHandler {
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				processor := &EventProcessor{
					matchState: matchState,
				}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.PlayerTeamChange{
				Player: nil,
			},
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				processor := &EventProcessor{
					matchState:      matchState,
					playerStates:    make(map[uint64]*types.PlayerState),
					teamAssignments: make(map[string]string),
					logger:          logrus.New(),
				}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.PlayerTeamChange{
				Player: &common.Player{
					SteamID64: 123,
					Name:      "TestPlayer",
					Team:      common.TeamTerrorists,
				},
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()

			err := handler.HandlePlayerTeamChange(tt.event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}

func TestMatchHandler_HandleWeaponFire_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *MatchHandler
		event       events.WeaponFire
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil processor should return event processing error",
			setup: func() *MatchHandler {
				return &MatchHandler{
					processor: nil,
					logger:    logrus.New(),
				}
			},
			event: events.WeaponFire{
				Shooter: &common.Player{},
				Weapon:  &common.Equipment{},
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil shooter should return validation error",
			setup: func() *MatchHandler {
				processor := &EventProcessor{}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.WeaponFire{
				Shooter: nil,
				Weapon:  &common.Equipment{},
			},
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
		{
			name: "nil weapon should return validation error",
			setup: func() *MatchHandler {
				processor := &EventProcessor{}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.WeaponFire{
				Shooter: &common.Player{},
				Weapon:  nil,
			},
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
		{
			name: "valid setup should not return error",
			setup: func() *MatchHandler {
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				processor := &EventProcessor{
					matchState:      matchState,
					playerStates:    make(map[uint64]*types.PlayerState),
					teamAssignments: make(map[string]string),
					logger:          logrus.New(),
				}
				return NewMatchHandler(processor, logrus.New())
			},
			event: events.WeaponFire{
				Shooter: &common.Player{
					SteamID64: 123,
					Name:      "TestPlayer",
				},
				Weapon: &common.Equipment{
					Type: common.EqAK47,
				},
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			handler := tt.setup()

			err := handler.HandleWeaponFire(tt.event)

			if tt.expectError {
				if err == nil {
					t.Error("Expected error but got nil")
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
			} else {
				if err != nil {
					t.Errorf("Expected no error, got %v", err)
				}
			}
		})
	}
}
