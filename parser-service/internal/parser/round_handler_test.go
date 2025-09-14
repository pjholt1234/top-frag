package parser

import (
	"parser-service/internal/types"
	"testing"

	"github.com/sirupsen/logrus"
)

func TestRoundHandler_aggregateGunfightMetrics_ExcludesTeamDamage(t *testing.T) {
	// Create a mock processor with team assignments
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound:   1,
			GunfightEvents: []types.GunfightEvent{},
			DamageEvents: []types.DamageEvent{
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player2", // Enemy
					HealthDamage:    50,
				},
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player3", // Teammate
					HealthDamage:    30,
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
			"player2": "B", // Enemy
			"player3": "A", // Teammate
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGunfightMetrics(event, "player1", 1)

	// Verify that only enemy damage is counted (50, not 80)
	if event.Damage != 50 {
		t.Errorf("Expected damage to be 50 (enemy damage only), got %d", event.Damage)
	}
}

func TestRoundHandler_aggregateGrenadeMetrics_CountsGrenadeTypes(t *testing.T) {
	// Create a mock processor with grenade events
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound: 1,
			GrenadeEvents: []types.GrenadeEvent{
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeFlash,
					DamageDealt:         0,
					EffectivenessRating: 5,
				},
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeHE,
					DamageDealt:         50,
					EffectivenessRating: 8,
				},
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeSmoke,
					DamageDealt:         0,
					EffectivenessRating: 0,
				},
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeMolotov,
					DamageDealt:         25,
					EffectivenessRating: 6,
				},
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeDecoy,
					DamageDealt:         0,
					EffectivenessRating: 0,
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGrenadeMetrics(event, "player1", 1)

	// Verify grenade counts
	if event.FlashesThrown != 1 {
		t.Errorf("Expected flashes thrown to be 1, got %d", event.FlashesThrown)
	}
	if event.HesThrown != 1 {
		t.Errorf("Expected HE grenades thrown to be 1, got %d", event.HesThrown)
	}
	if event.SmokesThrown != 1 {
		t.Errorf("Expected smokes thrown to be 1, got %d", event.SmokesThrown)
	}
	if event.FireGrenadesThrown != 1 {
		t.Errorf("Expected fire grenades thrown to be 1, got %d", event.FireGrenadesThrown)
	}
	if event.DecoysThrown != 1 {
		t.Errorf("Expected decoys thrown to be 1, got %d", event.DecoysThrown)
	}
	if event.DamageDealt != 75 {
		t.Errorf("Expected damage dealt to be 75 (50 HE + 25 Molotov), got %d", event.DamageDealt)
	}
}

func TestRoundHandler_aggregateGunfightMetrics_IncludesEnemyDamage(t *testing.T) {
	// Create a mock processor with team assignments
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound:   1,
			GunfightEvents: []types.GunfightEvent{},
			DamageEvents: []types.DamageEvent{
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player2", // Enemy
					HealthDamage:    75,
				},
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player4", // Another enemy
					HealthDamage:    25,
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
			"player2": "B", // Enemy
			"player4": "B", // Another enemy
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGunfightMetrics(event, "player1", 1)

	// Verify that all enemy damage is counted (75 + 25 = 100)
	if event.Damage != 100 {
		t.Errorf("Expected damage to be 100 (all enemy damage), got %d", event.Damage)
	}
}

func TestRoundHandler_aggregateGunfightMetrics_HandlesMissingTeamAssignment(t *testing.T) {
	// Create a mock processor with incomplete team assignments
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound:   1,
			GunfightEvents: []types.GunfightEvent{},
			DamageEvents: []types.DamageEvent{
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player2", // No team assignment
					HealthDamage:    50,
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
			// player2 not assigned (will default to "A")
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGunfightMetrics(event, "player1", 1)

	// Verify that damage is not counted when teams are the same (both default to "A")
	if event.Damage != 0 {
		t.Errorf("Expected damage to be 0 (same team due to default), got %d", event.Damage)
	}
}

func TestRoundHandler_aggregateGunfightMetrics_NoDoubleCounting(t *testing.T) {
	// Create a mock processor with gunfight and damage events for the same kill
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound: 1,
			GunfightEvents: []types.GunfightEvent{
				{
					RoundNumber:    1,
					Player1SteamID: "player1",
					Player2SteamID: "player2",
					VictorSteamID:  &[]string{"player1"}[0],
					DamageDealt:    100, // This should NOT be counted
					Player2HPStart: 0,   // Victim had 0 HP when killed
				},
			},
			DamageEvents: []types.DamageEvent{
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player2",
					HealthDamage:    75, // This should be counted
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
			"player2": "B", // Enemy
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGunfightMetrics(event, "player1", 1)

	// Verify that only the damage event is counted (75), not the gunfight damage (100)
	if event.Damage != 75 {
		t.Errorf("Expected damage to be 75 (damage events only), got %d", event.Damage)
	}

	// Verify that the kill is still counted
	if event.Kills != 1 {
		t.Errorf("Expected kills to be 1, got %d", event.Kills)
	}
}

func TestRoundHandler_ProcessRoundEnd(t *testing.T) {
	logger := logrus.New()

	tests := []struct {
		name          string
		processor     *EventProcessor
		expectedError bool
		errorType     types.ErrorType
		errorMessage  string
	}{
		{
			name:          "nil processor should return event processing error",
			processor:     nil,
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "processor is nil",
		},
		{
			name: "nil match state should return event processing error",
			processor: &EventProcessor{
				matchState: nil,
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "match state is nil",
		},
		{
			name: "invalid round number should return event processing error",
			processor: &EventProcessor{
				matchState: &types.MatchState{
					CurrentRound: 0,
					Players:      make(map[string]*types.Player),
				},
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "invalid round number",
		},
		{
			name: "negative round number should return event processing error",
			processor: &EventProcessor{
				matchState: &types.MatchState{
					CurrentRound: -1,
					Players:      make(map[string]*types.Player),
				},
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "invalid round number",
		},
		{
			name: "no players in round should return event processing error",
			processor: &EventProcessor{
				matchState: &types.MatchState{
					CurrentRound: 1,
					Players:      make(map[string]*types.Player), // Empty players map
				},
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "no players found in round",
		},
		{
			name: "empty player steam ID should return event processing error",
			processor: &EventProcessor{
				matchState: &types.MatchState{
					CurrentRound: 1,
					Players: map[string]*types.Player{
						"": {}, // Empty steam ID
					},
				},
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "empty player steam ID found",
		},
		{
			name: "valid round should process successfully",
			processor: &EventProcessor{
				matchState: &types.MatchState{
					CurrentRound: 1,
					Players: map[string]*types.Player{
						"player1": {},
						"player2": {},
					},
					PlayerRoundEvents: []types.PlayerRoundEvent{},
				},
			},
			expectedError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			roundHandler := &RoundHandler{
				processor: tt.processor,
				logger:    logger,
			}

			err := roundHandler.ProcessRoundEnd()

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

				// Verify that player round events were created
				if tt.processor != nil && tt.processor.matchState != nil {
					expectedEvents := len(tt.processor.matchState.Players)
					actualEvents := len(tt.processor.matchState.PlayerRoundEvents)
					if actualEvents != expectedEvents {
						t.Errorf("Expected %d player round events, got %d", expectedEvents, actualEvents)
					}
				}
			}
		})
	}
}
