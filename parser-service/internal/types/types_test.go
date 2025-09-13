package types

import (
	"encoding/json"
	"math"
	"testing"
	"time"
)

const floatTolerance = 1e-15

func TestCalculateDistance(t *testing.T) {
	tests := []struct {
		name     string
		pos1     Position
		pos2     Position
		expected float64
	}{
		{
			name:     "zero distance",
			pos1:     Position{X: 0, Y: 0, Z: 0},
			pos2:     Position{X: 0, Y: 0, Z: 0},
			expected: 0,
		},
		{
			name:     "unit distance",
			pos1:     Position{X: 0, Y: 0, Z: 0},
			pos2:     Position{X: 1, Y: 0, Z: 0},
			expected: 1,
		},
		{
			name:     "3D distance",
			pos1:     Position{X: 0, Y: 0, Z: 0},
			pos2:     Position{X: 3, Y: 4, Z: 0},
			expected: 5,
		},
		{
			name:     "negative coordinates",
			pos1:     Position{X: -1, Y: -1, Z: -1},
			pos2:     Position{X: 1, Y: 1, Z: 1},
			expected: 3.4641016151377544,
		},
		{
			name:     "large coordinates",
			pos1:     Position{X: 1000, Y: 2000, Z: 3000},
			pos2:     Position{X: 1001, Y: 2001, Z: 3001},
			expected: 1.7320508075688772,
		},
		{
			name:     "floating point precision",
			pos1:     Position{X: 0.1, Y: 0.2, Z: 0.3},
			pos2:     Position{X: 0.4, Y: 0.5, Z: 0.6},
			expected: 0.5196152422706632,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := CalculateDistance(tt.pos1, tt.pos2)
			if math.Abs(result-tt.expected) > floatTolerance {
				t.Errorf("CalculateDistance() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestNormalizeVector(t *testing.T) {
	tests := []struct {
		name     string
		vector   Vector
		expected Vector
	}{
		{
			name:     "zero vector",
			vector:   Vector{X: 0, Y: 0, Z: 0},
			expected: Vector{X: 0, Y: 0, Z: 0},
		},
		{
			name:     "unit vector",
			vector:   Vector{X: 1, Y: 0, Z: 0},
			expected: Vector{X: 1, Y: 0, Z: 0},
		},
		{
			name:     "normalize 2D vector",
			vector:   Vector{X: 3, Y: 4, Z: 0},
			expected: Vector{X: 0.6, Y: 0.8, Z: 0},
		},
		{
			name:     "normalize 3D vector",
			vector:   Vector{X: 1, Y: 1, Z: 1},
			expected: Vector{X: 0.5773502691896258, Y: 0.5773502691896258, Z: 0.5773502691896258},
		},
		{
			name:     "negative vector",
			vector:   Vector{X: -3, Y: -4, Z: 0},
			expected: Vector{X: -0.6, Y: -0.8, Z: 0},
		},
		{
			name:     "small vector",
			vector:   Vector{X: 0.1, Y: 0.2, Z: 0.3},
			expected: Vector{X: 0.2672612419124244, Y: 0.5345224838248488, Z: 0.8017837257372732},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := NormalizeVector(tt.vector)
			if math.Abs(result.X-tt.expected.X) > floatTolerance ||
				math.Abs(result.Y-tt.expected.Y) > floatTolerance ||
				math.Abs(result.Z-tt.expected.Z) > floatTolerance {
				t.Errorf("NormalizeVector() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestSteamIDToString(t *testing.T) {
	tests := []struct {
		name     string
		steamID  uint64
		expected string
	}{
		{
			name:     "zero steam ID",
			steamID:  0,
			expected: "steam_0",
		},
		{
			name:     "positive steam ID",
			steamID:  123456789,
			expected: "steam_123456789",
		},
		{
			name:     "large steam ID",
			steamID:  76561198000000000,
			expected: "steam_76561198000000000",
		},
		{
			name:     "maximum uint64",
			steamID:  18446744073709551615,
			expected: "steam_18446744073709551615",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := SteamIDToString(tt.steamID)
			if result != tt.expected {
				t.Errorf("SteamIDToString() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestGunfightEvent_Validate(t *testing.T) {
	tests := []struct {
		name    string
		event   GunfightEvent
		isValid bool
	}{
		{
			name: "valid event",
			event: GunfightEvent{
				RoundNumber:    1,
				RoundTime:      30,
				TickTimestamp:  12345,
				Player1SteamID: "steam_123",
				Player2SteamID: "steam_456",
				Player1HPStart: 100,
				Player2HPStart: 85,
				Player1Weapon:  "ak47",
				Player2Weapon:  "m4a1",
				Distance:       50.0,
				IsFirstKill:    false,
			},
			isValid: true,
		},
		{
			name: "missing player steam IDs",
			event: GunfightEvent{
				RoundNumber:    1,
				RoundTime:      30,
				TickTimestamp:  12345,
				Player1HPStart: 100,
				Player2HPStart: 85,
				Player1Weapon:  "ak47",
				Player2Weapon:  "m4a1",
				Distance:       50.0,
				IsFirstKill:    false,
			},
			isValid: false,
		},
		{
			name: "negative round number",
			event: GunfightEvent{
				RoundNumber:    -1,
				RoundTime:      30,
				TickTimestamp:  12345,
				Player1SteamID: "steam_123",
				Player2SteamID: "steam_456",
				Player1HPStart: 100,
				Player2HPStart: 85,
				Player1Weapon:  "ak47",
				Player2Weapon:  "m4a1",
				Distance:       50.0,
				IsFirstKill:    false,
			},
			isValid: false,
		},
		{
			name: "negative round time",
			event: GunfightEvent{
				RoundNumber:    1,
				RoundTime:      -30,
				TickTimestamp:  12345,
				Player1SteamID: "steam_123",
				Player2SteamID: "steam_456",
				Player1HPStart: 100,
				Player2HPStart: 85,
				Player1Weapon:  "ak47",
				Player2Weapon:  "m4a1",
				Distance:       50.0,
				IsFirstKill:    false,
			},
			isValid: false,
		},
		{
			name: "negative health values",
			event: GunfightEvent{
				RoundNumber:    1,
				RoundTime:      30,
				TickTimestamp:  12345,
				Player1SteamID: "steam_123",
				Player2SteamID: "steam_456",
				Player1HPStart: -10,
				Player2HPStart: 85,
				Player1Weapon:  "ak47",
				Player2Weapon:  "m4a1",
				Distance:       50.0,
				IsFirstKill:    false,
			},
			isValid: false,
		},
		{
			name: "negative distance",
			event: GunfightEvent{
				RoundNumber:    1,
				RoundTime:      30,
				TickTimestamp:  12345,
				Player1SteamID: "steam_123",
				Player2SteamID: "steam_456",
				Player1HPStart: 100,
				Player2HPStart: 85,
				Player1Weapon:  "ak47",
				Player2Weapon:  "m4a1",
				Distance:       -50.0,
				IsFirstKill:    false,
			},
			isValid: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.isValid {
				if tt.event.Player1SteamID == "" || tt.event.Player2SteamID == "" {
					t.Errorf("Expected valid event but got invalid")
				}
				if tt.event.RoundNumber <= 0 {
					t.Errorf("Expected valid round number but got %d", tt.event.RoundNumber)
				}
				if tt.event.RoundTime < 0 {
					t.Errorf("Expected valid round time but got %d", tt.event.RoundTime)
				}
				if tt.event.Player1HPStart < 0 || tt.event.Player2HPStart < 0 {
					t.Errorf("Expected valid health values but got %d, %d", tt.event.Player1HPStart, tt.event.Player2HPStart)
				}
				if tt.event.Distance < 0 {
					t.Errorf("Expected valid distance but got %f", tt.event.Distance)
				}
			}
		})
	}
}

func TestGrenadeEvent_Validate(t *testing.T) {
	tests := []struct {
		name    string
		event   GrenadeEvent
		isValid bool
	}{
		{
			name: "valid grenade event",
			event: GrenadeEvent{
				RoundNumber:       1,
				RoundTime:         30,
				TickTimestamp:     12345,
				PlayerSteamID:     "steam_123",
				GrenadeType:       GrenadeTypeFlash,
				PlayerPosition:    Position{X: 100, Y: 200, Z: 50},
				PlayerAim:         Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:         ThrowTypeUtility,
				FlashLeadsToKill:  false,
				FlashLeadsToDeath: false,
			},
			isValid: true,
		},
		{
			name: "missing player steam ID",
			event: GrenadeEvent{
				RoundNumber:       1,
				RoundTime:         30,
				TickTimestamp:     12345,
				GrenadeType:       GrenadeTypeFlash,
				PlayerPosition:    Position{X: 100, Y: 200, Z: 50},
				PlayerAim:         Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:         ThrowTypeUtility,
				FlashLeadsToKill:  false,
				FlashLeadsToDeath: false,
			},
			isValid: false,
		},
		{
			name: "invalid grenade type",
			event: GrenadeEvent{
				RoundNumber:       1,
				RoundTime:         30,
				TickTimestamp:     12345,
				PlayerSteamID:     "steam_123",
				GrenadeType:       "invalid_type",
				PlayerPosition:    Position{X: 100, Y: 200, Z: 50},
				PlayerAim:         Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:         ThrowTypeUtility,
				FlashLeadsToKill:  false,
				FlashLeadsToDeath: false,
			},
			isValid: false,
		},
		{
			name: "negative round number",
			event: GrenadeEvent{
				RoundNumber:       -1,
				RoundTime:         30,
				TickTimestamp:     12345,
				PlayerSteamID:     "steam_123",
				GrenadeType:       GrenadeTypeFlash,
				PlayerPosition:    Position{X: 100, Y: 200, Z: 50},
				PlayerAim:         Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:         ThrowTypeUtility,
				FlashLeadsToKill:  false,
				FlashLeadsToDeath: false,
			},
			isValid: false,
		},
		{
			name: "negative damage dealt",
			event: GrenadeEvent{
				RoundNumber:       1,
				RoundTime:         30,
				TickTimestamp:     12345,
				PlayerSteamID:     "steam_123",
				GrenadeType:       GrenadeTypeHE,
				PlayerPosition:    Position{X: 100, Y: 200, Z: 50},
				PlayerAim:         Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:         ThrowTypeUtility,
				DamageDealt:       -10,
				FlashLeadsToKill:  false,
				FlashLeadsToDeath: false,
			},
			isValid: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.isValid {
				if tt.event.PlayerSteamID == "" {
					t.Errorf("Expected valid event but got invalid")
				}
				validTypes := []string{GrenadeTypeHE, GrenadeTypeFlash, GrenadeTypeSmoke, GrenadeTypeMolotov, GrenadeTypeIncendiary, GrenadeTypeDecoy}
				isValidType := false
				for _, validType := range validTypes {
					if tt.event.GrenadeType == validType {
						isValidType = true
						break
					}
				}
				if !isValidType {
					t.Errorf("Expected valid grenade type but got invalid")
				}
				if tt.event.RoundNumber <= 0 {
					t.Errorf("Expected valid round number but got %d", tt.event.RoundNumber)
				}
				if tt.event.DamageDealt < 0 {
					t.Errorf("Expected valid damage dealt but got %d", tt.event.DamageDealt)
				}
			}
		})
	}
}

func TestDamageEvent_Validate(t *testing.T) {
	tests := []struct {
		name    string
		event   DamageEvent
		isValid bool
	}{
		{
			name: "valid damage event",
			event: DamageEvent{
				RoundNumber:     1,
				RoundTime:       30,
				TickTimestamp:   12345,
				AttackerSteamID: "steam_123",
				VictimSteamID:   "steam_456",
				Damage:          25,
				ArmorDamage:     10,
				HealthDamage:    15,
				Headshot:        false,
				Weapon:          "ak47",
			},
			isValid: true,
		},
		{
			name: "missing attacker steam ID",
			event: DamageEvent{
				RoundNumber:   1,
				RoundTime:     30,
				TickTimestamp: 12345,
				VictimSteamID: "steam_456",
				Damage:        25,
				ArmorDamage:   10,
				HealthDamage:  15,
				Headshot:      false,
				Weapon:        "ak47",
			},
			isValid: false,
		},
		{
			name: "missing victim steam ID",
			event: DamageEvent{
				RoundNumber:     1,
				RoundTime:       30,
				TickTimestamp:   12345,
				AttackerSteamID: "steam_123",
				Damage:          25,
				ArmorDamage:     10,
				HealthDamage:    15,
				Headshot:        false,
				Weapon:          "ak47",
			},
			isValid: false,
		},
		{
			name: "negative damage",
			event: DamageEvent{
				RoundNumber:     1,
				RoundTime:       30,
				TickTimestamp:   12345,
				AttackerSteamID: "steam_123",
				VictimSteamID:   "steam_456",
				Damage:          -25,
				ArmorDamage:     10,
				HealthDamage:    15,
				Headshot:        false,
				Weapon:          "ak47",
			},
			isValid: false,
		},
		{
			name: "negative armor damage",
			event: DamageEvent{
				RoundNumber:     1,
				RoundTime:       30,
				TickTimestamp:   12345,
				AttackerSteamID: "steam_123",
				VictimSteamID:   "steam_456",
				Damage:          25,
				ArmorDamage:     -10,
				HealthDamage:    15,
				Headshot:        false,
				Weapon:          "ak47",
			},
			isValid: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.isValid {
				if tt.event.AttackerSteamID == "" {
					t.Errorf("Expected valid attacker steam ID but got empty")
				}
				if tt.event.VictimSteamID == "" {
					t.Errorf("Expected valid victim steam ID but got empty")
				}
				if tt.event.Damage < 0 {
					t.Errorf("Expected valid damage but got %d", tt.event.Damage)
				}
				if tt.event.ArmorDamage < 0 {
					t.Errorf("Expected valid armor damage but got %d", tt.event.ArmorDamage)
				}
				if tt.event.HealthDamage < 0 {
					t.Errorf("Expected valid health damage but got %d", tt.event.HealthDamage)
				}
			}
		})
	}
}

func TestRoundEvent_Validate(t *testing.T) {
	tests := []struct {
		name    string
		event   RoundEvent
		isValid bool
	}{
		{
			name: "valid round event",
			event: RoundEvent{
				RoundNumber:   1,
				TickTimestamp: 12345,
				EventType:     "round_start",
				Winner:        nil,
				Duration:      nil,
			},
			isValid: true,
		},
		{
			name: "round end with winner",
			event: RoundEvent{
				RoundNumber:   1,
				TickTimestamp: 12345,
				EventType:     "round_end",
				Winner:        stringPtr("T"),
				Duration:      intPtr(120),
			},
			isValid: true,
		},
		{
			name: "negative round number",
			event: RoundEvent{
				RoundNumber:   -1,
				TickTimestamp: 12345,
				EventType:     "round_start",
				Winner:        nil,
				Duration:      nil,
			},
			isValid: false,
		},
		{
			name: "empty event type",
			event: RoundEvent{
				RoundNumber:   1,
				TickTimestamp: 12345,
				EventType:     "",
				Winner:        nil,
				Duration:      nil,
			},
			isValid: false,
		},
		{
			name: "negative duration",
			event: RoundEvent{
				RoundNumber:   1,
				TickTimestamp: 12345,
				EventType:     "round_end",
				Winner:        stringPtr("T"),
				Duration:      intPtr(-120),
			},
			isValid: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.isValid {
				if tt.event.RoundNumber <= 0 {
					t.Errorf("Expected valid round number but got %d", tt.event.RoundNumber)
				}
				if tt.event.EventType == "" {
					t.Errorf("Expected valid event type but got empty")
				}
				if tt.event.Duration != nil && *tt.event.Duration < 0 {
					t.Errorf("Expected valid duration but got %d", *tt.event.Duration)
				}
			}
		})
	}
}

func TestPlayer_Validate(t *testing.T) {
	tests := []struct {
		name    string
		player  Player
		isValid bool
	}{
		{
			name: "valid player",
			player: Player{
				SteamID: "steam_123",
				Name:    "Player1",
				Team:    "T",
			},
			isValid: true,
		},
		{
			name: "missing steam ID",
			player: Player{
				Name: "Player1",
				Team: "T",
			},
			isValid: false,
		},
		{
			name: "empty name",
			player: Player{
				SteamID: "steam_123",
				Name:    "",
				Team:    "T",
			},
			isValid: false,
		},
		{
			name: "invalid team",
			player: Player{
				SteamID: "steam_123",
				Name:    "Player1",
				Team:    "Invalid",
			},
			isValid: false,
		},
		{
			name: "valid CT team",
			player: Player{
				SteamID: "steam_123",
				Name:    "Player1",
				Team:    "CT",
			},
			isValid: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.isValid {
				if tt.player.SteamID == "" {
					t.Errorf("Expected valid steam ID but got empty")
				}
				if tt.player.Name == "" {
					t.Errorf("Expected valid name but got empty")
				}
				validTeams := []string{"T", "CT"}
				isValidTeam := false
				for _, validTeam := range validTeams {
					if tt.player.Team == validTeam {
						isValidTeam = true
						break
					}
				}
				if !isValidTeam {
					t.Errorf("Expected valid team but got %s", tt.player.Team)
				}
			}
		})
	}
}

func TestMatch_Validate(t *testing.T) {
	now := time.Now()
	tests := []struct {
		name    string
		match   Match
		isValid bool
	}{
		{
			name: "valid match",
			match: Match{
				Map:              "de_dust2",
				WinningTeamScore: 16,
				LosingTeamScore:  14,
				MatchType:        "competitive",
				StartTimestamp:   &now,
				EndTimestamp:     &now,
				TotalRounds:      30,
			},
			isValid: true,
		},
		{
			name: "missing map",
			match: Match{
				WinningTeamScore: 16,
				LosingTeamScore:  14,
				MatchType:        "competitive",
				StartTimestamp:   &now,
				EndTimestamp:     &now,
				TotalRounds:      30,
			},
			isValid: false,
		},
		{
			name: "negative scores",
			match: Match{
				Map:              "de_dust2",
				WinningTeamScore: -16,
				LosingTeamScore:  14,
				MatchType:        "competitive",
				StartTimestamp:   &now,
				EndTimestamp:     &now,
				TotalRounds:      30,
			},
			isValid: false,
		},
		{
			name: "negative total rounds",
			match: Match{
				Map:              "de_dust2",
				WinningTeamScore: 16,
				LosingTeamScore:  14,
				MatchType:        "competitive",
				StartTimestamp:   &now,
				EndTimestamp:     &now,
				TotalRounds:      -30,
			},
			isValid: false,
		},
		{
			name: "end before start",
			match: Match{
				Map:              "de_dust2",
				WinningTeamScore: 16,
				LosingTeamScore:  14,
				MatchType:        "competitive",
				StartTimestamp:   &now,
				EndTimestamp:     timePtr(now.Add(-time.Hour)),
				TotalRounds:      30,
			},
			isValid: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.isValid {
				if tt.match.Map == "" {
					t.Errorf("Expected valid map but got empty")
				}
				if tt.match.WinningTeamScore < 0 {
					t.Errorf("Expected valid winning team score but got %d", tt.match.WinningTeamScore)
				}
				if tt.match.LosingTeamScore < 0 {
					t.Errorf("Expected valid losing team score but got %d", tt.match.LosingTeamScore)
				}
				if tt.match.TotalRounds <= 0 {
					t.Errorf("Expected valid total rounds but got %d", tt.match.TotalRounds)
				}
				if tt.match.StartTimestamp != nil && tt.match.EndTimestamp != nil {
					if tt.match.EndTimestamp.Before(*tt.match.StartTimestamp) {
						t.Errorf("Expected end timestamp after start timestamp")
					}
				}
			}
		})
	}
}

func TestAffectedPlayer_Validate(t *testing.T) {
	flashDuration := 2.5
	damageTaken := 25
	tests := []struct {
		name    string
		player  AffectedPlayer
		isValid bool
	}{
		{
			name: "valid affected player",
			player: AffectedPlayer{
				SteamID:       "steam_123",
				FlashDuration: &flashDuration,
				DamageTaken:   &damageTaken,
			},
			isValid: true,
		},
		{
			name: "missing steam ID",
			player: AffectedPlayer{
				FlashDuration: &flashDuration,
				DamageTaken:   &damageTaken,
			},
			isValid: false,
		},
		{
			name: "negative flash duration",
			player: AffectedPlayer{
				SteamID:       "steam_123",
				FlashDuration: float64Ptr(-2.5),
				DamageTaken:   &damageTaken,
			},
			isValid: false,
		},
		{
			name: "negative damage taken",
			player: AffectedPlayer{
				SteamID:       "steam_123",
				FlashDuration: &flashDuration,
				DamageTaken:   intPtr(-25),
			},
			isValid: false,
		},
		{
			name: "only steam ID",
			player: AffectedPlayer{
				SteamID: "steam_123",
			},
			isValid: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.isValid {
				if tt.player.SteamID == "" {
					t.Errorf("Expected valid steam ID but got empty")
				}
				if tt.player.FlashDuration != nil && *tt.player.FlashDuration < 0 {
					t.Errorf("Expected valid flash duration but got %f", *tt.player.FlashDuration)
				}
				if tt.player.DamageTaken != nil && *tt.player.DamageTaken < 0 {
					t.Errorf("Expected valid damage taken but got %d", *tt.player.DamageTaken)
				}
			}
		})
	}
}

// Helper functions for creating pointers
func stringPtr(s string) *string {
	return &s
}

func intPtr(i int) *int {
	return &i
}

func float64Ptr(f float64) *float64 {
	return &f
}

func timePtr(t time.Time) *time.Time {
	return &t
}

// Enhanced Progress Tracking Tests

func TestNewStepManager(t *testing.T) {
	tests := []struct {
		name          string
		totalRounds   int
		expectedSteps int
	}{
		{
			name:          "zero rounds",
			totalRounds:   0,
			expectedSteps: 18, // Just base steps
		},
		{
			name:          "16 rounds",
			totalRounds:   16,
			expectedSteps: 34, // 18 base + 16 rounds
		},
		{
			name:          "30 rounds",
			totalRounds:   30,
			expectedSteps: 48, // 18 base + 30 rounds
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			sm := NewStepManager(tt.totalRounds)

			if sm.TotalSteps != tt.expectedSteps {
				t.Errorf("Expected TotalSteps to be %d, got %d", tt.expectedSteps, sm.TotalSteps)
			}
			if sm.CurrentStepNum != 1 {
				t.Errorf("Expected CurrentStepNum to be 1, got %d", sm.CurrentStepNum)
			}
			if sm.StepProgress != 0 {
				t.Errorf("Expected StepProgress to be 0, got %d", sm.StepProgress)
			}
			if sm.Context == nil {
				t.Error("Expected Context to be initialized")
			}
			if sm.StartTime.IsZero() {
				t.Error("Expected StartTime to be set")
			}
			if sm.LastUpdateTime.IsZero() {
				t.Error("Expected LastUpdateTime to be set")
			}
		})
	}
}

func TestStepManager_UpdateStep(t *testing.T) {
	sm := NewStepManager(16)

	// Test step update
	sm.UpdateStep(5, "Processing grenade events")

	if sm.CurrentStepNum != 5 {
		t.Errorf("Expected CurrentStepNum to be 5, got %d", sm.CurrentStepNum)
	}
	if sm.StepProgress != 0 {
		t.Errorf("Expected StepProgress to be reset to 0, got %d", sm.StepProgress)
	}
	if sm.Context["current_step_name"] != "Processing grenade events" {
		t.Errorf("Expected current_step_name to be 'Processing grenade events', got %v", sm.Context["current_step_name"])
	}

	// Verify LastUpdateTime was updated
	if sm.LastUpdateTime.IsZero() {
		t.Error("Expected LastUpdateTime to be updated")
	}
}

func TestStepManager_UpdateStepProgress(t *testing.T) {
	sm := NewStepManager(16)

	// Test step progress update
	context := map[string]interface{}{
		"round":            5,
		"events_processed": 100,
	}
	sm.UpdateStepProgress(75, context)

	if sm.StepProgress != 75 {
		t.Errorf("Expected StepProgress to be 75, got %d", sm.StepProgress)
	}
	if sm.Context["round"] != 5 {
		t.Errorf("Expected context round to be 5, got %v", sm.Context["round"])
	}
	if sm.Context["events_processed"] != 100 {
		t.Errorf("Expected context events_processed to be 100, got %v", sm.Context["events_processed"])
	}

	// Verify LastUpdateTime was updated
	if sm.LastUpdateTime.IsZero() {
		t.Error("Expected LastUpdateTime to be updated")
	}
}

func TestStepManager_GetOverallProgress(t *testing.T) {
	tests := []struct {
		name         string
		totalRounds  int
		currentStep  int
		stepProgress int
		expectedMin  int
		expectedMax  int
	}{
		{
			name:         "first step, no progress",
			totalRounds:  16,
			currentStep:  1,
			stepProgress: 0,
			expectedMin:  0,
			expectedMax:  5,
		},
		{
			name:         "first step, half progress",
			totalRounds:  16,
			currentStep:  1,
			stepProgress: 50,
			expectedMin:  1,
			expectedMax:  3,
		},
		{
			name:         "middle step, full progress",
			totalRounds:  16,
			currentStep:  10,
			stepProgress: 100,
			expectedMin:  25,
			expectedMax:  30,
		},
		{
			name:         "last step, full progress",
			totalRounds:  16,
			currentStep:  34, // 18 + 16
			stepProgress: 100,
			expectedMin:  95,
			expectedMax:  100,
		},
		{
			name:         "zero rounds, first step",
			totalRounds:  0,
			currentStep:  1,
			stepProgress: 50,
			expectedMin:  2,
			expectedMax:  7,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			sm := NewStepManager(tt.totalRounds)
			sm.UpdateStep(tt.currentStep, "Test step")
			sm.UpdateStepProgress(tt.stepProgress, nil)

			overallProgress := sm.GetOverallProgress()

			if overallProgress < tt.expectedMin || overallProgress > tt.expectedMax {
				t.Errorf("Expected overall progress to be between %d-%d, got %d",
					tt.expectedMin, tt.expectedMax, overallProgress)
			}
		})
	}
}

func TestStepManager_ContextMerging(t *testing.T) {
	sm := NewStepManager(16)

	// Test initial context
	sm.UpdateStep(1, "Initial step")
	if sm.Context["current_step_name"] != "Initial step" {
		t.Errorf("Expected current_step_name to be 'Initial step', got %v", sm.Context["current_step_name"])
	}

	// Test context merging
	context1 := map[string]interface{}{
		"round":            5,
		"events_processed": 100,
	}
	sm.UpdateStepProgress(50, context1)

	if sm.Context["round"] != 5 {
		t.Errorf("Expected context round to be 5, got %v", sm.Context["round"])
	}
	if sm.Context["events_processed"] != 100 {
		t.Errorf("Expected context events_processed to be 100, got %v", sm.Context["events_processed"])
	}

	// Test context overwriting
	context2 := map[string]interface{}{
		"round":     6, // Should overwrite previous round
		"new_field": "new_value",
	}
	sm.UpdateStepProgress(75, context2)

	if sm.Context["round"] != 6 {
		t.Errorf("Expected context round to be updated to 6, got %v", sm.Context["round"])
	}
	if sm.Context["new_field"] != "new_value" {
		t.Errorf("Expected context new_field to be 'new_value', got %v", sm.Context["new_field"])
	}
	if sm.Context["events_processed"] != 100 {
		t.Errorf("Expected context events_processed to remain 100, got %v", sm.Context["events_processed"])
	}
}

func TestStepManager_EdgeCases(t *testing.T) {
	// Test with zero rounds
	sm := NewStepManager(0)
	expectedTotalSteps := 18
	if sm.TotalSteps != expectedTotalSteps {
		t.Errorf("Expected TotalSteps to be %d for zero rounds, got %d", expectedTotalSteps, sm.TotalSteps)
	}

	// Test step progress boundaries
	sm.UpdateStepProgress(0, nil)
	if sm.StepProgress != 0 {
		t.Errorf("Expected StepProgress to be 0, got %d", sm.StepProgress)
	}

	sm.UpdateStepProgress(100, nil)
	if sm.StepProgress != 100 {
		t.Errorf("Expected StepProgress to be 100, got %d", sm.StepProgress)
	}

	// Test step number boundaries
	sm.UpdateStep(1, "First step")
	if sm.CurrentStepNum != 1 {
		t.Errorf("Expected CurrentStepNum to be 1, got %d", sm.CurrentStepNum)
	}

	sm.UpdateStep(sm.TotalSteps, "Last step")
	if sm.CurrentStepNum != sm.TotalSteps {
		t.Errorf("Expected CurrentStepNum to be %d, got %d", sm.TotalSteps, sm.CurrentStepNum)
	}
}

func TestProgressUpdate_JSONSerialization(t *testing.T) {
	// Test ProgressUpdate JSON serialization
	startTime := time.Date(2024, 1, 1, 10, 0, 0, 0, time.UTC)
	lastUpdateTime := time.Date(2024, 1, 1, 10, 5, 0, 0, time.UTC)
	errorMessage := "Test error"
	errorCode := "TEST_ERROR"

	original := ProgressUpdate{
		JobID:          "test-job-123",
		Status:         StatusParsing,
		Progress:       25,
		CurrentStep:    "Processing grenade events",
		ErrorMessage:   &errorMessage,
		StepProgress:   75,
		TotalSteps:     20,
		CurrentStepNum: 6,
		StartTime:      startTime,
		LastUpdateTime: lastUpdateTime,
		ErrorCode:      &errorCode,
		Context: map[string]interface{}{
			"step":         "grenade_events_processing",
			"round":        3,
			"total_rounds": 16,
		},
		IsFinal: false,
	}

	// Serialize to JSON
	jsonData, err := json.Marshal(original)
	if err != nil {
		t.Fatalf("Failed to marshal ProgressUpdate to JSON: %v", err)
	}

	// Deserialize from JSON
	var deserialized ProgressUpdate
	err = json.Unmarshal(jsonData, &deserialized)
	if err != nil {
		t.Fatalf("Failed to unmarshal ProgressUpdate from JSON: %v", err)
	}

	// Verify all fields are preserved
	if deserialized.JobID != original.JobID {
		t.Errorf("JobID mismatch: expected %s, got %s", original.JobID, deserialized.JobID)
	}
	if deserialized.Status != original.Status {
		t.Errorf("Status mismatch: expected %s, got %s", original.Status, deserialized.Status)
	}
	if deserialized.Progress != original.Progress {
		t.Errorf("Progress mismatch: expected %d, got %d", original.Progress, deserialized.Progress)
	}
	if deserialized.CurrentStep != original.CurrentStep {
		t.Errorf("CurrentStep mismatch: expected %s, got %s", original.CurrentStep, deserialized.CurrentStep)
	}
	if deserialized.StepProgress != original.StepProgress {
		t.Errorf("StepProgress mismatch: expected %d, got %d", original.StepProgress, deserialized.StepProgress)
	}
	if deserialized.TotalSteps != original.TotalSteps {
		t.Errorf("TotalSteps mismatch: expected %d, got %d", original.TotalSteps, deserialized.TotalSteps)
	}
	if deserialized.CurrentStepNum != original.CurrentStepNum {
		t.Errorf("CurrentStepNum mismatch: expected %d, got %d", original.CurrentStepNum, deserialized.CurrentStepNum)
	}
	if deserialized.StartTime != original.StartTime {
		t.Errorf("StartTime mismatch: expected %v, got %v", original.StartTime, deserialized.StartTime)
	}
	if deserialized.LastUpdateTime != original.LastUpdateTime {
		t.Errorf("LastUpdateTime mismatch: expected %v, got %v", original.LastUpdateTime, deserialized.LastUpdateTime)
	}
	if deserialized.IsFinal != original.IsFinal {
		t.Errorf("IsFinal mismatch: expected %v, got %v", original.IsFinal, deserialized.IsFinal)
	}

	// Verify error fields
	if deserialized.ErrorMessage == nil {
		t.Error("Expected ErrorMessage to be preserved")
	} else if *deserialized.ErrorMessage != *original.ErrorMessage {
		t.Errorf("ErrorMessage mismatch: expected %s, got %s", *original.ErrorMessage, *deserialized.ErrorMessage)
	}

	if deserialized.ErrorCode == nil {
		t.Error("Expected ErrorCode to be preserved")
	} else if *deserialized.ErrorCode != *original.ErrorCode {
		t.Errorf("ErrorCode mismatch: expected %s, got %s", *original.ErrorCode, *deserialized.ErrorCode)
	}

	// Verify context
	if deserialized.Context == nil {
		t.Error("Expected Context to be preserved")
	} else {
		if deserialized.Context["step"] != original.Context["step"] {
			t.Errorf("Context step mismatch: expected %v, got %v", original.Context["step"], deserialized.Context["step"])
		}
		// JSON unmarshaling converts numbers to float64, so we need to compare appropriately
		if deserialized.Context["round"] != float64(original.Context["round"].(int)) {
			t.Errorf("Context round mismatch: expected %v, got %v", original.Context["round"], deserialized.Context["round"])
		}
	}
}

func TestProgressUpdate_JSONSerializationWithNilFields(t *testing.T) {
	// Test ProgressUpdate JSON serialization with nil fields
	startTime := time.Date(2024, 1, 1, 10, 0, 0, 0, time.UTC)
	lastUpdateTime := time.Date(2024, 1, 1, 10, 5, 0, 0, time.UTC)

	original := ProgressUpdate{
		JobID:          "test-job-123",
		Status:         StatusParsing,
		Progress:       25,
		CurrentStep:    "Processing grenade events",
		ErrorMessage:   nil, // nil field
		StepProgress:   75,
		TotalSteps:     20,
		CurrentStepNum: 6,
		StartTime:      startTime,
		LastUpdateTime: lastUpdateTime,
		ErrorCode:      nil, // nil field
		Context: map[string]interface{}{
			"step": "grenade_events_processing",
		},
		IsFinal: false,
	}

	// Serialize to JSON
	jsonData, err := json.Marshal(original)
	if err != nil {
		t.Fatalf("Failed to marshal ProgressUpdate with nil fields to JSON: %v", err)
	}

	// Deserialize from JSON
	var deserialized ProgressUpdate
	err = json.Unmarshal(jsonData, &deserialized)
	if err != nil {
		t.Fatalf("Failed to unmarshal ProgressUpdate with nil fields from JSON: %v", err)
	}

	// Verify nil fields remain nil
	if deserialized.ErrorMessage != nil {
		t.Errorf("Expected ErrorMessage to remain nil, got %v", *deserialized.ErrorMessage)
	}
	if deserialized.ErrorCode != nil {
		t.Errorf("Expected ErrorCode to remain nil, got %v", *deserialized.ErrorCode)
	}
}

func TestProcessingJob_EnhancedFields(t *testing.T) {
	// Test ProcessingJob with enhanced fields
	startTime := time.Date(2024, 1, 1, 10, 0, 0, 0, time.UTC)
	lastUpdateTime := time.Date(2024, 1, 1, 10, 5, 0, 0, time.UTC)

	job := ProcessingJob{
		JobID:                 "test-job-123",
		TempFilePath:          "/test/path.dem",
		ProgressCallbackURL:   "http://localhost:8080/callback",
		CompletionCallbackURL: "http://localhost:8080/completion",
		Status:                StatusParsing,
		Progress:              25,
		CurrentStep:           "Processing grenade events",
		ErrorMessage:          "",
		StartTime:             startTime,
		MatchData:             nil,
		ErrorCode:             "TEST_ERROR",
		LastUpdateTime:        lastUpdateTime,
		StepProgress:          75,
		TotalSteps:            20,
		CurrentStepNum:        6,
		Context: map[string]interface{}{
			"step": "grenade_events_processing",
		},
		IsFinal: false,
	}

	// Verify enhanced fields
	if job.StepProgress != 75 {
		t.Errorf("Expected StepProgress to be 75, got %d", job.StepProgress)
	}
	if job.TotalSteps != 20 {
		t.Errorf("Expected TotalSteps to be 20, got %d", job.TotalSteps)
	}
	if job.CurrentStepNum != 6 {
		t.Errorf("Expected CurrentStepNum to be 6, got %d", job.CurrentStepNum)
	}
	if job.ErrorCode != "TEST_ERROR" {
		t.Errorf("Expected ErrorCode to be 'TEST_ERROR', got %s", job.ErrorCode)
	}
	if job.LastUpdateTime != lastUpdateTime {
		t.Errorf("Expected LastUpdateTime to match, got %v", job.LastUpdateTime)
	}
	if job.Context == nil {
		t.Error("Expected Context to be initialized")
	}
	if job.IsFinal {
		t.Error("Expected IsFinal to be false")
	}
}
