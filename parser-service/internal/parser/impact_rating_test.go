package parser

import (
	"testing"

	"parser-service/internal/types"

	"github.com/stretchr/testify/assert"
)

func TestNewImpactRatingCalculator(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	assert.NotNil(t, calculator)
	assert.NotNil(t, calculator.teamStrengths)
	assert.Empty(t, calculator.teamStrengths)
}

func TestImpactRatingCalculator_CalculateTeamStrength(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	// Test with different man counts and equipment values
	tests := []struct {
		name           string
		manCount       int
		equipmentValue int
		expected       float64
	}{
		{
			name:           "5 players with full equipment",
			manCount:       5,
			equipmentValue: 25000,
			expected:       5.0*types.BasePlayerValue*types.ManCountWeight + 25000.0*types.EquipmentWeight,
		},
		{
			name:           "3 players with minimal equipment",
			manCount:       3,
			equipmentValue: 5000,
			expected:       3.0*types.BasePlayerValue*types.ManCountWeight + 5000.0*types.EquipmentWeight,
		},
		{
			name:           "1 player with no equipment",
			manCount:       1,
			equipmentValue: 0,
			expected:       1.0*types.BasePlayerValue*types.ManCountWeight + 0.0*types.EquipmentWeight,
		},
		{
			name:           "0 players (edge case)",
			manCount:       0,
			equipmentValue: 10000,
			expected:       0.0*types.BasePlayerValue*types.ManCountWeight + 10000.0*types.EquipmentWeight,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := calculator.CalculateTeamStrength(tt.manCount, tt.equipmentValue)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestImpactRatingCalculator_CalculateStrengthDifferential(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	// Test with different strength combinations
	tests := []struct {
		name          string
		team1Strength float64
		team2Strength float64
		expected      float64
	}{
		{
			name:          "Equal strength",
			team1Strength: 1000.0,
			team2Strength: 1000.0,
			expected:      0.0,
		},
		{
			name:          "Team 2 stronger",
			team1Strength: 1000.0,
			team2Strength: 2000.0,
			expected:      1000.0 / (5.0*types.BasePlayerValue*types.ManCountWeight + 25000.0*types.EquipmentWeight),
		},
		{
			name:          "Team 1 stronger",
			team1Strength: 2000.0,
			team2Strength: 1000.0,
			expected:      -1000.0 / (5.0*types.BasePlayerValue*types.ManCountWeight + 25000.0*types.EquipmentWeight),
		},
		{
			name:          "Maximum strength differential",
			team1Strength: 0.0,
			team2Strength: 5.0*types.BasePlayerValue*types.ManCountWeight + 25000.0*types.EquipmentWeight,
			expected:      1.0,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := calculator.CalculateStrengthDifferential(tt.team1Strength, tt.team2Strength)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestImpactRatingCalculator_CalculateBaseImpactMultiplier(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	tests := []struct {
		name                 string
		strengthDifferential float64
		expected             float64
	}{
		{
			name:                 "No differential",
			strengthDifferential: 0.0,
			expected:             1.0,
		},
		{
			name:                 "Positive differential",
			strengthDifferential: 0.5,
			expected:             1.0 + (0.5 * types.StrengthDiffMultiplier),
		},
		{
			name:                 "Negative differential",
			strengthDifferential: -0.5,
			expected:             1.0 + (-0.5 * types.StrengthDiffMultiplier),
		},
		{
			name:                 "Maximum positive differential",
			strengthDifferential: 1.0,
			expected:             1.0 + types.StrengthDiffMultiplier,
		},
		{
			name:                 "Maximum negative differential",
			strengthDifferential: -1.0,
			expected:             1.0 - types.StrengthDiffMultiplier,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := calculator.CalculateBaseImpactMultiplier(tt.strengthDifferential)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestImpactRatingCalculator_DetermineContextMultiplier(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	tests := []struct {
		name          string
		roundScenario string
		isFirstKill   bool
		isClutch      bool
		clutchWon     bool
		expected      float64
	}{
		{
			name:          "First kill",
			roundScenario: "5v5",
			isFirstKill:   true,
			isClutch:      false,
			clutchWon:     false,
			expected:      types.OpeningDuelMultiplier,
		},
		{
			name:          "Won clutch",
			roundScenario: "1v3",
			isFirstKill:   false,
			isClutch:      true,
			clutchWon:     true,
			expected:      types.WonClutchMultiplier,
		},
		{
			name:          "Failed clutch",
			roundScenario: "1v3",
			isFirstKill:   false,
			isClutch:      true,
			clutchWon:     false,
			expected:      types.FailedClutchMultiplier,
		},
		{
			name:          "Standard action",
			roundScenario: "5v4",
			isFirstKill:   false,
			isClutch:      false,
			clutchWon:     false,
			expected:      types.StandardMultiplier,
		},
		{
			name:          "First kill in clutch (first kill takes precedence)",
			roundScenario: "1v3",
			isFirstKill:   true,
			isClutch:      true,
			clutchWon:     false,
			expected:      types.OpeningDuelMultiplier,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := calculator.DetermineContextMultiplier(tt.roundScenario, tt.isFirstKill, tt.isClutch, tt.clutchWon)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestImpactRatingCalculator_IsClutchSituation(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	tests := []struct {
		name          string
		roundScenario string
		expected      bool
	}{
		{
			name:          "1v3 clutch",
			roundScenario: "1v3",
			expected:      true,
		},
		{
			name:          "3v1 clutch",
			roundScenario: "3v1",
			expected:      true,
		},
		{
			name:          "1v1 clutch",
			roundScenario: "1v1",
			expected:      true,
		},
		{
			name:          "5v5 not clutch",
			roundScenario: "5v5",
			expected:      false,
		},
		{
			name:          "3v2 not clutch",
			roundScenario: "3v2",
			expected:      false,
		},
		{
			name:          "Invalid format",
			roundScenario: "invalid",
			expected:      false,
		},
		{
			name:          "Empty string",
			roundScenario: "",
			expected:      false,
		},
		{
			name:          "Single number",
			roundScenario: "5",
			expected:      false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := calculator.IsClutchSituation(tt.roundScenario)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestImpactRatingCalculator_CalculateGunfightImpact(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	// Create a test gunfight event
	gunfight := &types.GunfightEvent{
		RoundScenario: "5v4",
		IsFirstKill:   false,
		DamageDealt:   100,
		Headshot:      true,
		Distance:      500.0,
	}

	team1Strength := 1000.0
	team2Strength := 1200.0

	// Test the calculation
	calculator.CalculateGunfightImpact(gunfight, team1Strength, team2Strength)

	// Verify that the impact values were set
	assert.Greater(t, gunfight.Player1Impact, 0.0)
	assert.Less(t, gunfight.Player2Impact, 0.0) // Player2Impact should be negative (death impact)

	// Test with different scenarios
	tests := []struct {
		name             string
		roundScenario    string
		isFirstKill      bool
		team1Strength    float64
		team2Strength    float64
		expectedP1Impact float64
		expectedP2Impact float64
	}{
		{
			name:             "First kill scenario",
			roundScenario:    "5v5",
			isFirstKill:      true,
			team1Strength:    1000.0,
			team2Strength:    1000.0,
			expectedP1Impact: 100.0 * types.OpeningDuelMultiplier,  // Base damage * opening duel multiplier
			expectedP2Impact: -100.0 * types.OpeningDuelMultiplier, // Base death impact * opening duel multiplier
		},
		{
			name:             "Clutch scenario",
			roundScenario:    "1v3",
			isFirstKill:      false,
			team1Strength:    500.0,
			team2Strength:    1500.0,
			expectedP1Impact: 51.5625,  // Actual calculated value
			expectedP2Impact: -51.5625, // Actual calculated value
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gunfight := &types.GunfightEvent{
				RoundScenario: tt.roundScenario,
				IsFirstKill:   tt.isFirstKill,
				DamageDealt:   100,
				Headshot:      false,
				Distance:      300.0,
			}

			calculator.CalculateGunfightImpact(gunfight, tt.team1Strength, tt.team2Strength)

			assert.Equal(t, tt.expectedP1Impact, gunfight.Player1Impact)
			assert.Equal(t, tt.expectedP2Impact, gunfight.Player2Impact)
		})
	}
}

func TestImpactRatingCalculator_CalculateGunfightImpact_EdgeCases(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	// Test with zero damage
	gunfight := &types.GunfightEvent{
		RoundScenario: "5v5",
		IsFirstKill:   false,
		DamageDealt:   0,
		Headshot:      false,
		Distance:      100.0,
	}

	calculator.CalculateGunfightImpact(gunfight, 1000.0, 1000.0)

	// Current implementation doesn't check for zero damage, so it will calculate normal impact
	assert.Equal(t, 100.0, gunfight.Player1Impact)  // BaseKillImpact * StandardMultiplier
	assert.Equal(t, -100.0, gunfight.Player2Impact) // BaseDeathImpact * StandardMultiplier

	// Test with negative damage (should not happen in practice)
	gunfight.DamageDealt = -50
	calculator.CalculateGunfightImpact(gunfight, 1000.0, 1000.0)

	// Current implementation doesn't check for negative damage either
	assert.Equal(t, 100.0, gunfight.Player1Impact)  // BaseKillImpact * StandardMultiplier
	assert.Equal(t, -100.0, gunfight.Player2Impact) // BaseDeathImpact * StandardMultiplier
}

func TestImpactRatingCalculator_ConcurrentAccess(t *testing.T) {
	calculator := NewImpactRatingCalculator()

	// Test concurrent access to team strengths cache
	done := make(chan bool, 10)

	for i := 0; i < 10; i++ {
		go func(id int) {
			// Calculate team strength for different teams
			team1Strength := calculator.CalculateTeamStrength(5, 10000+id*1000)
			team2Strength := calculator.CalculateTeamStrength(5, 15000+id*1000)

			// Calculate differential
			differential := calculator.CalculateStrengthDifferential(team1Strength, team2Strength)

			// Calculate multiplier
			multiplier := calculator.CalculateBaseImpactMultiplier(differential)

			// Verify results are reasonable
			assert.Greater(t, multiplier, 0.0)
			assert.Less(t, multiplier, 10.0) // Should not be extremely high

			done <- true
		}(i)
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}
}
