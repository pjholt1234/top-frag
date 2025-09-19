package parser

import (
	"testing"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"

	"parser-service/internal/types"
)

func TestEventProcessor_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *EventProcessor
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil_match_state_should_return_error",
			setup: func() *EventProcessor {
				logger := logrus.New()
				return &EventProcessor{
					matchState: nil,
					logger:     logger,
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil_logger_should_return_error",
			setup: func() *EventProcessor {
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				return &EventProcessor{
					matchState: matchState,
					logger:     nil,
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil_team_assignments_should_return_error",
			setup: func() *EventProcessor {
				logger := logrus.New()
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				return &EventProcessor{
					matchState:      matchState,
					logger:          logger,
					teamAssignments: nil,
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "valid_setup_should_not_return_error",
			setup: func() *EventProcessor {
				logger := logrus.New()
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				return &EventProcessor{
					matchState:      matchState,
					logger:          logger,
					teamAssignments: make(map[string]string),
					playerStates:    make(map[uint64]*types.PlayerState),
				}
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			processor := tt.setup()
			player := &common.Player{
				SteamID64: 123,
				Name:      "TestPlayer",
				Team:      common.TeamCounterTerrorists,
			}

			err := processor.ensurePlayerTracked(player)

			if tt.expectError {
				assert.Error(t, err)
				if parseErr, ok := err.(*types.ParseError); ok {
					assert.Equal(t, tt.errorType, parseErr.Type)
				}
			} else {
				assert.NoError(t, err)
			}
		})
	}
}

func TestEventProcessor_HandleRoundEnd_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *EventProcessor
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil_match_handler_should_return_error",
			setup: func() *EventProcessor {
				logger := logrus.New()
				matchState := &types.MatchState{
					Players: make(map[string]*types.Player),
				}
				return &EventProcessor{
					matchState:   matchState,
					logger:       logger,
					matchHandler: nil,
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil_round_handler_should_return_error",
			setup: func() *EventProcessor {
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
				matchHandler := NewMatchHandler(processor, logger)
				processor.matchHandler = matchHandler
				return processor
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "valid_setup_should_not_return_error",
			setup: func() *EventProcessor {
				logger := logrus.New()
				matchState := &types.MatchState{
					Players:      make(map[string]*types.Player),
					CurrentRound: 1, // Set a valid round number in match state
				}
				// Add some players to avoid "no players found in round" error
				matchState.Players["123"] = &types.Player{
					SteamID: "123",
					Name:    "TestPlayer",
					Team:    "A",
				}
				processor := &EventProcessor{
					matchState:      matchState,
					logger:          logger,
					playerStates:    make(map[uint64]*types.PlayerState),
					teamAssignments: make(map[string]string),
					currentRound:    1, // Set a valid round number
				}
				matchHandler := NewMatchHandler(processor, logger)
				roundHandler := NewRoundHandler(processor, logger)
				processor.matchHandler = matchHandler
				processor.roundHandler = roundHandler
				return processor
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			processor := tt.setup()
			event := events.RoundEnd{
				Winner: common.TeamCounterTerrorists,
			}

			err := processor.HandleRoundEnd(event)

			if tt.expectError {
				assert.Error(t, err)
				if parseErr, ok := err.(*types.ParseError); ok {
					assert.Equal(t, tt.errorType, parseErr.Type)
				}
			} else {
				assert.NoError(t, err)
			}
		})
	}
}

func TestEventProcessor_AssignTeamBasedOnRound1To12_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *EventProcessor
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nil_logger_should_return_error",
			setup: func() *EventProcessor {
				return &EventProcessor{
					logger: nil,
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "nil_team_assignments_should_return_error",
			setup: func() *EventProcessor {
				logger := logrus.New()
				return &EventProcessor{
					logger:          logger,
					teamAssignments: nil,
				}
			},
			expectError: true,
			errorType:   types.ErrorTypeEventProcessing,
		},
		{
			name: "valid_setup_should_not_return_error",
			setup: func() *EventProcessor {
				logger := logrus.New()
				return &EventProcessor{
					logger:          logger,
					teamAssignments: make(map[string]string),
				}
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			processor := tt.setup()

			err := processor.assignTeamBasedOnRound1To12("123", "CT")

			if tt.expectError {
				assert.Error(t, err)
				if parseErr, ok := err.(*types.ParseError); ok {
					assert.Equal(t, tt.errorType, parseErr.Type)
				}
			} else {
				assert.NoError(t, err)
			}
		})
	}
}
