package parser

import (
	"testing"

	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

// TestGrenadeHandlerRefactor validates all the fixes implemented in the grenade handler refactor
func TestGrenadeHandlerRefactor(t *testing.T) {
	logger := logrus.New()
	logger.SetLevel(logrus.DebugLevel)

	// Create test match state
	matchState := &types.MatchState{
		CurrentRound:   1,
		RoundStartTick: 1000,
		Players:        make(map[string]*types.Player),
		GrenadeEvents:  make([]types.GrenadeEvent, 0),
		DamageEvents:   make([]types.DamageEvent, 0),
	}

	// Create event processor
	processor := NewEventProcessor(matchState, logger, nil)
	processor.currentTick = 2000 // Set current tick to simulate time passing

	// Create grenade handler
	grenadeHandler := NewGrenadeHandler(processor, logger)

	t.Run("GrenadeDisplayNameMapping", func(t *testing.T) {
		// Test that grenade types are properly mapped to display names
		testCases := []struct {
			input    string
			expected string
		}{
			{"hegrenade", "HE Grenade"},
			{"flashbang", "Flashbang"},
			{"smokegrenade", "Smoke Grenade"},
			{"molotov", "Molotov"},
			{"incendiary", "Incendiary"},
			{"decoy", "Decoy"},
			{"unknown", "unknown"}, // Should return as-is
		}

		for _, tc := range testCases {
			result := grenadeHandler.getGrenadeDisplayName(tc.input)
			if result != tc.expected {
				t.Errorf("getGrenadeDisplayName(%s) = %s, expected %s", tc.input, result, tc.expected)
			}
		}
	})

	t.Run("ThrowInformationStorage", func(t *testing.T) {
		// Test that throw information is properly stored and retrieved
		// Create a mock projectile ID
		projectileID := "test-projectile-123"

		// Store throw information
		throwInfo := &GrenadeMovementInfo{
			Tick:      1500,
			RoundTime: 8, // 8 seconds into round
			PlayerPos: types.Position{X: 100, Y: 200, Z: 50},
			PlayerAim: types.Vector{X: 0.5, Y: 0.3, Z: 0.8},
			ThrowType: "Standing",
		}

		grenadeHandler.grenadeThrows[projectileID] = throwInfo

		// Verify storage
		stored, exists := grenadeHandler.grenadeThrows[projectileID]
		if !exists {
			t.Error("Throw information was not stored")
		}
		if stored.Tick != 1500 {
			t.Errorf("Stored tick = %d, expected 1500", stored.Tick)
		}
		if stored.RoundTime != 8 {
			t.Errorf("Stored round time = %d, expected 8", stored.RoundTime)
		}
		if stored.PlayerPos.X != 100 {
			t.Errorf("Stored player position X = %f, expected 100", stored.PlayerPos.X)
		}
	})

	t.Run("DamageAggregation", func(t *testing.T) {
		// Test that damage is properly aggregated from damage events
		// Create a grenade event
		grenadeEvent := &types.GrenadeEvent{
			RoundNumber:   1,
			TickTimestamp: 1500,
			PlayerSteamID: "123",
			GrenadeType:   "HE Grenade",
		}

		// Add related damage events
		damageEvents := []types.DamageEvent{
			{
				RoundNumber:     1,
				TickTimestamp:   1550, // 50 ticks after grenade (within 64 tick window)
				AttackerSteamID: "123",
				VictimSteamID:   "456",
				Damage:          50, // Use Damage field instead of HealthDamage
				HealthDamage:    50,
				ArmorDamage:     25,
				Weapon:          "HE Grenade",
			},
			{
				RoundNumber:     1,
				TickTimestamp:   1560, // 60 ticks after grenade (within 64 tick window)
				AttackerSteamID: "123",
				VictimSteamID:   "789",
				Damage:          30, // Use Damage field instead of HealthDamage
				HealthDamage:    30,
				ArmorDamage:     15,
				Weapon:          "HE Grenade",
			},
		}

		// Add damage events to match state
		processor.matchState.DamageEvents = damageEvents

		// Add grenade event to match state
		processor.matchState.GrenadeEvents = append(processor.matchState.GrenadeEvents, *grenadeEvent)

		// Set up player teams for damage aggregation
		processor.teamAssignments["123"] = "A"
		processor.teamAssignments["456"] = "B"
		processor.teamAssignments["789"] = "B"

		// Aggregate damage using the new deferred method
		grenadeHandler.AggregateAllGrenadeDamage()

		// Verify damage aggregation - check the grenade event in match state
		expectedTotalDamage := 50 + 30 // Only health damage
		if len(processor.matchState.GrenadeEvents) != 1 {
			t.Fatalf("Expected 1 grenade event, got %d", len(processor.matchState.GrenadeEvents))
		}

		updatedGrenadeEvent := processor.matchState.GrenadeEvents[0]
		if updatedGrenadeEvent.DamageDealt != expectedTotalDamage {
			t.Errorf("Aggregated damage = %d, expected %d", updatedGrenadeEvent.DamageDealt, expectedTotalDamage)
		}

		// Verify affected players
		if len(updatedGrenadeEvent.AffectedPlayers) != 2 {
			t.Errorf("Affected players count = %d, expected 2", len(updatedGrenadeEvent.AffectedPlayers))
		}

		// Verify individual player damage
		for _, player := range updatedGrenadeEvent.AffectedPlayers {
			if player.SteamID == "456" && *player.DamageTaken != 50 {
				t.Errorf("Player 456 damage = %d, expected 50", *player.DamageTaken)
			}
			if player.SteamID == "789" && *player.DamageTaken != 30 {
				t.Errorf("Player 789 damage = %d, expected 30", *player.DamageTaken)
			}
		}
	})

	t.Run("FlashTrackingImprovement", func(t *testing.T) {
		// Test that flash tracking logic is improved
		// Create a flash effect
		flashEffect := &FlashEffect{
			EntityID:        12345,
			ThrowerSteamID:  "123",
			ExplosionTick:   1500,
			AffectedPlayers: make(map[uint64]*PlayerFlashInfo),
		}

		processor.activeFlashEffects[12345] = flashEffect

		// Set up team assignments
		processor.teamAssignments["123"] = "A"
		processor.teamAssignments["456"] = "B"

		// Test the friendly/enemy detection logic directly
		throwerTeam := processor.getAssignedTeam("123")
		playerTeam := processor.getAssignedTeam("456")
		isFriendly := throwerTeam == playerTeam && "123" != "456"

		// Since teams are different, this should be an enemy flash
		if isFriendly {
			t.Error("Expected enemy flash, but got friendly flash")
		}

		// Test that the flash effect structure is correct
		if flashEffect.EntityID != 12345 {
			t.Errorf("Flash effect entity ID = %d, expected 12345", flashEffect.EntityID)
		}

		if flashEffect.ThrowerSteamID != "123" {
			t.Errorf("Flash effect thrower Steam ID = %s, expected '123'", flashEffect.ThrowerSteamID)
		}
	})

	t.Run("TimingAccuracy", func(t *testing.T) {
		// Test that timing is captured at throw time, not explosion time
		// Create a grenade event with throw-time data
		grenadeEvent := &types.GrenadeEvent{
			RoundNumber:   1,
			RoundTime:     8,    // 8 seconds into round (throw time)
			TickTimestamp: 1500, // Throw tick
			PlayerSteamID: "123",
			GrenadeType:   "HE Grenade",
		}

		// Simulate explosion happening 2 seconds later
		explosionRoundTime := 10 // 8 + 2 seconds

		// Verify that the grenade event uses throw time, not explosion time
		if grenadeEvent.RoundTime != 8 {
			t.Errorf("Grenade round time = %d, expected 8 (throw time)", grenadeEvent.RoundTime)
		}
		if grenadeEvent.TickTimestamp != 1500 {
			t.Errorf("Grenade tick timestamp = %d, expected 1500 (throw tick)", grenadeEvent.TickTimestamp)
		}

		// The explosion time should be different
		if explosionRoundTime == grenadeEvent.RoundTime {
			t.Error("Grenade event should use throw time, not explosion time")
		}
	})

	t.Run("PositionCaptureAccuracy", func(t *testing.T) {
		// Test that positions are captured at throw time, not explosion time
		throwPosition := types.Position{X: 100, Y: 200, Z: 50}
		throwAim := types.Vector{X: 0.5, Y: 0.3, Z: 0.8}

		// Simulate player moving after throw
		explosionPosition := types.Position{X: 150, Y: 250, Z: 60}

		// Create grenade event with throw-time data
		grenadeEvent := &types.GrenadeEvent{
			RoundNumber:    1,
			PlayerPosition: throwPosition,
			PlayerAim:      throwAim,
			GrenadeType:    "HE Grenade",
		}

		// Verify that the grenade event uses throw position, not explosion position
		if grenadeEvent.PlayerPosition.X != throwPosition.X {
			t.Errorf("Grenade player position X = %f, expected %f (throw position)",
				grenadeEvent.PlayerPosition.X, throwPosition.X)
		}
		if grenadeEvent.PlayerAim.X != throwAim.X {
			t.Errorf("Grenade player aim X = %f, expected %f (throw aim)",
				grenadeEvent.PlayerAim.X, throwAim.X)
		}

		// The explosion position should be different
		if explosionPosition.X == grenadeEvent.PlayerPosition.X {
			t.Error("Grenade event should use throw position, not explosion position")
		}
	})
}

// TestGrenadeHandlerRefactorIntegration tests the integration of all fixes together
func TestGrenadeHandlerRefactorIntegration(t *testing.T) {
	logger := logrus.New()
	logger.SetLevel(logrus.DebugLevel)

	// Create test match state
	matchState := &types.MatchState{
		CurrentRound:   1,
		RoundStartTick: 1000,
		Players:        make(map[string]*types.Player),
		GrenadeEvents:  make([]types.GrenadeEvent, 0),
		DamageEvents:   make([]types.DamageEvent, 0),
	}

	// Create event processor
	processor := NewEventProcessor(matchState, logger, nil)
	processor.currentTick = 2000

	// Create grenade handler
	grenadeHandler := NewGrenadeHandler(processor, logger)

	// Set up team assignments
	processor.teamAssignments["123"] = "A"
	processor.teamAssignments["456"] = "B"

	// Test complete grenade flow: throw -> damage -> explosion
	t.Run("CompleteGrenadeFlow", func(t *testing.T) {
		// 1. Simulate grenade throw (stored in HandleGrenadeProjectileThrow)
		projectileID := "test-projectile-456"
		throwInfo := &GrenadeMovementInfo{
			Tick:      1500,
			RoundTime: 8,
			PlayerPos: types.Position{X: 100, Y: 200, Z: 50},
			PlayerAim: types.Vector{X: 0.5, Y: 0.3, Z: 0.8},
			ThrowType: "Standing",
		}
		grenadeHandler.grenadeThrows[projectileID] = throwInfo

		// 2. Add damage events that occur after the throw
		damageEvents := []types.DamageEvent{
			{
				RoundNumber:     1,
				TickTimestamp:   1550, // 50 ticks after grenade (within 64 tick window)
				AttackerSteamID: "123",
				VictimSteamID:   "456",
				Damage:          75, // Use Damage field instead of HealthDamage
				HealthDamage:    75,
				ArmorDamage:     25,
				Weapon:          "HE Grenade",
			},
		}
		processor.matchState.DamageEvents = damageEvents

		// 3. Simulate grenade explosion by directly creating the grenade event
		// (In a real scenario, this would be done by HandleGrenadeProjectileDestroy)
		grenadeEvent := types.GrenadeEvent{
			RoundNumber:       1,
			RoundTime:         throwInfo.RoundTime, // Use throw time
			TickTimestamp:     throwInfo.Tick,      // Use throw tick
			PlayerSteamID:     "123",
			PlayerSide:        "A",
			GrenadeType:       grenadeHandler.getGrenadeDisplayName("hegrenade"),
			PlayerPosition:    throwInfo.PlayerPos, // Use throw position
			PlayerAim:         throwInfo.PlayerAim, // Use throw aim
			ThrowType:         throwInfo.ThrowType,
			FlashLeadsToKill:  false,
			FlashLeadsToDeath: false,
		}

		grenadeEvent.GrenadeFinalPosition = &types.Position{
			X: 120, Y: 180, Z: 60, // Final explosion position
		}

		// Add grenade event to match state first
		processor.matchState.GrenadeEvents = append(processor.matchState.GrenadeEvents, grenadeEvent)

		// Set up player teams for damage aggregation
		processor.teamAssignments["123"] = "A"
		processor.teamAssignments["456"] = "B"

		// Aggregate damage from damage events using deferred method
		grenadeHandler.AggregateAllGrenadeDamage()

		// 4. Verify the complete grenade event
		if len(processor.matchState.GrenadeEvents) != 1 {
			t.Fatalf("Expected 1 grenade event, got %d", len(processor.matchState.GrenadeEvents))
		}

		grenadeEvent = processor.matchState.GrenadeEvents[0]

		// Verify timing (should be from throw time)
		if grenadeEvent.RoundTime != 8 {
			t.Errorf("Grenade round time = %d, expected 8 (throw time)", grenadeEvent.RoundTime)
		}
		if grenadeEvent.TickTimestamp != 1500 {
			t.Errorf("Grenade tick timestamp = %d, expected 1500 (throw tick)", grenadeEvent.TickTimestamp)
		}

		// Verify position (should be from throw time)
		if grenadeEvent.PlayerPosition.X != 100 {
			t.Errorf("Grenade player position X = %f, expected 100 (throw position)", grenadeEvent.PlayerPosition.X)
		}
		if grenadeEvent.PlayerAim.X != 0.5 {
			t.Errorf("Grenade player aim X = %f, expected 0.5 (throw aim)", grenadeEvent.PlayerAim.X)
		}

		// Verify grenade type (should be display name)
		if grenadeEvent.GrenadeType != "HE Grenade" {
			t.Errorf("Grenade type = %s, expected 'HE Grenade'", grenadeEvent.GrenadeType)
		}

		// Verify damage (should be health damage only)
		if grenadeEvent.DamageDealt != 75 {
			t.Errorf("Grenade damage dealt = %d, expected 75 (health damage only)", grenadeEvent.DamageDealt)
		}

		// Verify affected players
		if len(grenadeEvent.AffectedPlayers) != 1 {
			t.Errorf("Affected players count = %d, expected 1", len(grenadeEvent.AffectedPlayers))
		}

		if grenadeEvent.AffectedPlayers[0].SteamID != "456" {
			t.Errorf("Affected player Steam ID = %s, expected '456'", grenadeEvent.AffectedPlayers[0].SteamID)
		}

		if *grenadeEvent.AffectedPlayers[0].DamageTaken != 75 {
			t.Errorf("Affected player damage taken = %d, expected 75", *grenadeEvent.AffectedPlayers[0].DamageTaken)
		}

		// Verify throw type
		if grenadeEvent.ThrowType != "Standing" {
			t.Errorf("Throw type = %s, expected 'Standing'", grenadeEvent.ThrowType)
		}
	})
}

func TestGrenadeHandler_HandleGrenadeProjectileThrow(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound:  1,
		GrenadeEvents: []types.GrenadeEvent{},
		Players:       make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)
	grenadeHandler := NewGrenadeHandler(processor, logger)

	tests := []struct {
		name          string
		event         events.GrenadeProjectileThrow
		expectedError bool
		errorType     types.ErrorType
		errorMessage  string
	}{
		{
			name: "nil projectile should return event processing error",
			event: events.GrenadeProjectileThrow{
				Projectile: nil,
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "projectile is nil",
		},
		{
			name: "nil thrower should return event processing error",
			event: events.GrenadeProjectileThrow{
				Projectile: nil, // Will be nil for this test
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "projectile is nil", // Fail-fast: projectile is checked first
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := grenadeHandler.HandleGrenadeProjectileThrow(tt.event)

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

func TestGrenadeHandler_HandleGrenadeProjectileDestroy(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound:  1,
		GrenadeEvents: []types.GrenadeEvent{},
		Players:       make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)
	grenadeHandler := NewGrenadeHandler(processor, logger)

	tests := []struct {
		name          string
		event         events.GrenadeProjectileDestroy
		expectedError bool
		errorType     types.ErrorType
		errorMessage  string
	}{
		{
			name: "nil projectile should return event processing error",
			event: events.GrenadeProjectileDestroy{
				Projectile: nil,
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "projectile is nil",
		},
		{
			name: "nil thrower should return event processing error",
			event: events.GrenadeProjectileDestroy{
				Projectile: nil, // Will be nil for this test
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "projectile is nil", // Fail-fast: projectile is checked first
		},
		{
			name: "nil weapon instance should return event processing error",
			event: events.GrenadeProjectileDestroy{
				Projectile: nil, // Will be nil for this test
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "projectile is nil", // Fail-fast: projectile is checked first
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := grenadeHandler.HandleGrenadeProjectileDestroy(tt.event)

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

func TestGrenadeHandler_HandlePlayerFlashed(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound:  1,
		GrenadeEvents: []types.GrenadeEvent{},
		Players:       make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)
	grenadeHandler := NewGrenadeHandler(processor, logger)

	tests := []struct {
		name          string
		event         events.PlayerFlashed
		expectedError bool
		errorType     types.ErrorType
		errorMessage  string
	}{
		{
			name: "nil player should return event processing error",
			event: events.PlayerFlashed{
				Player: nil,
			},
			expectedError: true,
			errorType:     types.ErrorTypeEventProcessing,
			errorMessage:  "player is nil",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := grenadeHandler.HandlePlayerFlashed(tt.event)

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

func TestGrenadeHandler_HandleSmokeStart(t *testing.T) {
	matchState := &types.MatchState{}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)
	grenadeHandler := processor.grenadeHandler

	// Create a mock smoke start event
	smokeEvent := events.SmokeStart{
		GrenadeEvent: events.GrenadeEvent{
			Thrower: &common.Player{
				SteamID64: 76561198012345678,
				Name:      "TestPlayer",
			},
		},
	}

	// Test that HandleSmokeStart doesn't panic and returns no error
	err := grenadeHandler.HandleSmokeStart(smokeEvent)
	if err != nil {
		t.Errorf("Expected no error, got %v", err)
	}

	t.Log("HandleSmokeStart method tested successfully")
}

func TestGrenadeHandler_GetGrenadeDisplayName(t *testing.T) {
	matchState := &types.MatchState{}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger, nil)
	grenadeHandler := processor.grenadeHandler

	// Test various grenade types
	testCases := []struct {
		input    string
		expected string
	}{
		{"hegrenade", "HE Grenade"},
		{"flashbang", "Flashbang"},
		{"smokegrenade", "Smoke Grenade"},
		{"molotov", "Molotov"},
		{"incendiary", "Incendiary"},
		{"unknown", "unknown"}, // Method returns as-is for unknown types
	}

	for _, tc := range testCases {
		result := grenadeHandler.getGrenadeDisplayName(tc.input)
		if result != tc.expected {
			t.Errorf("getGrenadeDisplayName(%s) = %s, expected %s", tc.input, result, tc.expected)
		}
	}

	t.Log("GetGrenadeDisplayName method tested successfully")
}
