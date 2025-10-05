package utils

import (
	"testing"

	"parser-service/internal/types"
)

func TestNewAimUtilityService(t *testing.T) {

	tests := []struct {
		name     string
		mapName  string
		expected bool
	}{
		{
			name:     "valid map name",
			mapName:  "de_ancient",
			expected: true,
		},
		{
			name:     "empty map name",
			mapName:  "",
			expected: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			service, err := NewAimUtilityService(tt.mapName)

			if tt.expected {
				if err != nil {
					t.Errorf("Expected no error but got: %v", err)
				}
				if service == nil {
					t.Error("Expected service to be created")
				}
			} else {
				if err == nil {
					t.Error("Expected error but got nil")
				}
			}
		})
	}
}

func TestAimUtilityService_ProcessAimTrackingForRound(t *testing.T) {
	service, err := NewAimUtilityService("de_ancient")
	if err != nil {
		t.Fatalf("Failed to create aim utility service: %v", err)
	}

	// Test data
	shootingData := []types.PlayerShootingData{
		{
			PlayerID:    "player1",
			RoundNumber: 1,
			Tick:        100,
			WeaponName:  "ak47",
			IsSpraying:  true,
		},
		{
			PlayerID:    "player1",
			RoundNumber: 1,
			Tick:        110,
			WeaponName:  "ak47",
			IsSpraying:  true,
		},
	}

	damageEvents := []types.DamageEvent{
		{
			RoundNumber:     1,
			TickTimestamp:   105,
			AttackerSteamID: "player1",
			VictimSteamID:   "player2",
			Damage:          50,
			HitGroup:        types.HitGroupHead,
		},
	}

	playerTickData := []types.PlayerTickData{
		{
			PlayerID: "player1",
			Tick:     100,
		},
	}

	aimResults, weaponResults, err := service.ProcessAimTrackingForRound(
		shootingData,
		damageEvents,
		playerTickData,
		1,
	)

	if err != nil {
		t.Errorf("Expected no error but got: %v", err)
	}

	if len(aimResults) == 0 {
		t.Error("Expected aim results to be generated")
	}

	if len(weaponResults) == 0 {
		t.Error("Expected weapon results to be generated")
	}

	// Check first aim result
	if len(aimResults) > 0 {
		result := aimResults[0]
		if result.PlayerSteamID != "player1" {
			t.Errorf("Expected player ID 'player1', got '%s'", result.PlayerSteamID)
		}
		if result.RoundNumber != 1 {
			t.Errorf("Expected round number 1, got %d", result.RoundNumber)
		}
		if result.ShotsFired != 2 {
			t.Errorf("Expected 2 shots fired, got %d", result.ShotsFired)
		}
	}
}

func TestAimUtilityService_calculateAverage(t *testing.T) {
	service, err := NewAimUtilityService("de_ancient")
	if err != nil {
		t.Fatalf("Failed to create aim utility service: %v", err)
	}

	tests := []struct {
		name     string
		values   []float64
		expected float64
	}{
		{
			name:     "empty slice",
			values:   []float64{},
			expected: 0.0,
		},
		{
			name:     "single value",
			values:   []float64{5.0},
			expected: 5.0,
		},
		{
			name:     "multiple values",
			values:   []float64{1.0, 2.0, 3.0, 4.0, 5.0},
			expected: 3.0,
		},
		{
			name:     "negative values",
			values:   []float64{-1.0, 0.0, 1.0},
			expected: 0.0,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := service.calculateAverage(tt.values)
			if result != tt.expected {
				t.Errorf("Expected %f, got %f", tt.expected, result)
			}
		})
	}
}

func TestAimUtilityService_groupShootingDataByPlayer(t *testing.T) {
	service, err := NewAimUtilityService("de_ancient")
	if err != nil {
		t.Fatalf("Failed to create aim utility service: %v", err)
	}

	shootingData := []types.PlayerShootingData{
		{
			PlayerID:    "player1",
			RoundNumber: 1,
		},
		{
			PlayerID:    "player2",
			RoundNumber: 1,
		},
		{
			PlayerID:    "player1",
			RoundNumber: 2,
		},
	}

	result := service.groupShootingDataByPlayer(shootingData, 1)

	if len(result) != 2 {
		t.Errorf("Expected 2 players, got %d", len(result))
	}

	if len(result["player1"]) != 1 {
		t.Errorf("Expected player1 to have 1 shot, got %d", len(result["player1"]))
	}

	if len(result["player2"]) != 1 {
		t.Errorf("Expected player2 to have 1 shot, got %d", len(result["player2"]))
	}
}

func TestAimUtilityService_groupDamageEventsByPlayer(t *testing.T) {
	service, err := NewAimUtilityService("de_ancient")
	if err != nil {
		t.Fatalf("Failed to create aim utility service: %v", err)
	}

	damageEvents := []types.DamageEvent{
		{
			AttackerSteamID: "player1",
			RoundNumber:     1,
		},
		{
			AttackerSteamID: "player2",
			RoundNumber:     1,
		},
		{
			AttackerSteamID: "player1",
			RoundNumber:     2,
		},
	}

	result := service.groupDamageEventsByPlayer(damageEvents, 1)

	if len(result) != 2 {
		t.Errorf("Expected 2 players, got %d", len(result))
	}

	if len(result["player1"]) != 1 {
		t.Errorf("Expected player1 to have 1 damage event, got %d", len(result["player1"]))
	}

	if len(result["player2"]) != 1 {
		t.Errorf("Expected player2 to have 1 damage event, got %d", len(result["player2"]))
	}
}
