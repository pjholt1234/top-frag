package types

import (
	"testing"
)

func TestCalculateDistance(t *testing.T) {
	tests := []struct {
		name     string
		pos1     Position
		pos2     Position
		expected float64
	}{
		{
			name: "zero distance",
			pos1: Position{X: 0, Y: 0, Z: 0},
			pos2: Position{X: 0, Y: 0, Z: 0},
			expected: 0,
		},
		{
			name: "unit distance",
			pos1: Position{X: 0, Y: 0, Z: 0},
			pos2: Position{X: 1, Y: 0, Z: 0},
			expected: 1,
		},
		{
			name: "3D distance",
			pos1: Position{X: 0, Y: 0, Z: 0},
			pos2: Position{X: 3, Y: 4, Z: 0},
			expected: 5,
		},
		{
			name: "negative coordinates",
			pos1: Position{X: -1, Y: -1, Z: -1},
			pos2: Position{X: 1, Y: 1, Z: 1},
			expected: 3.4641016151377544,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := CalculateDistance(tt.pos1, tt.pos2)
			if result != tt.expected {
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
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := NormalizeVector(tt.vector)
			if result.X != tt.expected.X || result.Y != tt.expected.Y || result.Z != tt.expected.Z {
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
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if tt.isValid {
				if tt.event.Player1SteamID == "" || tt.event.Player2SteamID == "" {
					t.Errorf("Expected valid event but got invalid")
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
				RoundNumber:   1,
				RoundTime:     30,
				TickTimestamp: 12345,
				PlayerSteamID: "steam_123",
				GrenadeType:   GrenadeTypeFlash,
				PlayerPosition: Position{X: 100, Y: 200, Z: 50},
				PlayerAim:     Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:     ThrowTypeUtility,
			},
			isValid: true,
		},
		{
			name: "missing player steam ID",
			event: GrenadeEvent{
				RoundNumber:   1,
				RoundTime:     30,
				TickTimestamp: 12345,
				GrenadeType:   GrenadeTypeFlash,
				PlayerPosition: Position{X: 100, Y: 200, Z: 50},
				PlayerAim:     Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:     ThrowTypeUtility,
			},
			isValid: false,
		},
		{
			name: "invalid grenade type",
			event: GrenadeEvent{
				RoundNumber:   1,
				RoundTime:     30,
				TickTimestamp: 12345,
				PlayerSteamID: "steam_123",
				GrenadeType:   "invalid_type",
				PlayerPosition: Position{X: 100, Y: 200, Z: 50},
				PlayerAim:     Vector{X: 0.8, Y: 0.2, Z: 0.1},
				ThrowType:     ThrowTypeUtility,
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
			}
		})
	}
} 