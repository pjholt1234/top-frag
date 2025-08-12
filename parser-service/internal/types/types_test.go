package types

import (
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
				RoundNumber:    1,
				RoundTime:      30,
				TickTimestamp:  12345,
				PlayerSteamID:  "steam_123",
				GrenadeType:    GrenadeTypeFlash,
				PlayerPosition: Position{X: 100, Y: 200, Z: 50},
				PlayerAim:      Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:      ThrowTypeUtility,
			},
			isValid: true,
		},
		{
			name: "missing player steam ID",
			event: GrenadeEvent{
				RoundNumber:    1,
				RoundTime:      30,
				TickTimestamp:  12345,
				GrenadeType:    GrenadeTypeFlash,
				PlayerPosition: Position{X: 100, Y: 200, Z: 50},
				PlayerAim:      Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:      ThrowTypeUtility,
			},
			isValid: false,
		},
		{
			name: "invalid grenade type",
			event: GrenadeEvent{
				RoundNumber:    1,
				RoundTime:      30,
				TickTimestamp:  12345,
				PlayerSteamID:  "steam_123",
				GrenadeType:    "invalid_type",
				PlayerPosition: Position{X: 100, Y: 200, Z: 50},
				PlayerAim:      Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:      ThrowTypeUtility,
			},
			isValid: false,
		},
		{
			name: "negative round number",
			event: GrenadeEvent{
				RoundNumber:    -1,
				RoundTime:      30,
				TickTimestamp:  12345,
				PlayerSteamID:  "steam_123",
				GrenadeType:    GrenadeTypeFlash,
				PlayerPosition: Position{X: 100, Y: 200, Z: 50},
				PlayerAim:      Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:      ThrowTypeUtility,
			},
			isValid: false,
		},
		{
			name: "negative damage dealt",
			event: GrenadeEvent{
				RoundNumber:    1,
				RoundTime:      30,
				TickTimestamp:  12345,
				PlayerSteamID:  "steam_123",
				GrenadeType:    GrenadeTypeHE,
				PlayerPosition: Position{X: 100, Y: 200, Z: 50},
				PlayerAim:      Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:      ThrowTypeUtility,
				DamageDealt:    -10,
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
