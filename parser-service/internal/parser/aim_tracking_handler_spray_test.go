package parser

import (
	"testing"

	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
)

func TestDetectSprayingPatterns(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound: 1,
		Players:      make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger)
	handler := NewAimTrackingHandler(processor, logger)

	// Create test shooting data with a spray pattern
	// Player 1 with AK47 - 5 shots within 1 second (spray pattern)
	baseTick := int64(1000)
	sprayShots := []types.PlayerShootingData{
		{
			PlayerID:       "player1",
			WeaponName:     "ak47",
			WeaponCategory: "rifle",
			Tick:           baseTick,
			IsSpraying:     false,
		},
		{
			PlayerID:       "player1",
			WeaponName:     "ak47",
			WeaponCategory: "rifle",
			Tick:           baseTick + 10, // 10 ticks later
			IsSpraying:     false,
		},
		{
			PlayerID:       "player1",
			WeaponName:     "ak47",
			WeaponCategory: "rifle",
			Tick:           baseTick + 20, // 20 ticks later
			IsSpraying:     false,
		},
		{
			PlayerID:       "player1",
			WeaponName:     "ak47",
			WeaponCategory: "rifle",
			Tick:           baseTick + 30, // 30 ticks later
			IsSpraying:     false,
		},
		{
			PlayerID:       "player1",
			WeaponName:     "ak47",
			WeaponCategory: "rifle",
			Tick:           baseTick + 40, // 40 ticks later
			IsSpraying:     false,
		},
	}

	// Add isolated shots (not spraying)
	isolatedShots := []types.PlayerShootingData{
		{
			PlayerID:       "player2",
			WeaponName:     "ak47",
			WeaponCategory: "rifle",
			Tick:           baseTick + 200, // Much later
			IsSpraying:     false,
		},
		{
			PlayerID:       "player2",
			WeaponName:     "ak47",
			WeaponCategory: "rifle",
			Tick:           baseTick + 300, // Much later
			IsSpraying:     false,
		},
	}

	// Add non-spray weapon shots
	nonSprayShots := []types.PlayerShootingData{
		{
			PlayerID:       "player3",
			WeaponName:     "awp",
			WeaponCategory: "other",
			Tick:           baseTick + 100,
			IsSpraying:     false,
		},
		{
			PlayerID:       "player3",
			WeaponName:     "awp",
			WeaponCategory: "other",
			Tick:           baseTick + 110,
			IsSpraying:     false,
		},
		{
			PlayerID:       "player3",
			WeaponName:     "awp",
			WeaponCategory: "other",
			Tick:           baseTick + 120,
			IsSpraying:     false,
		},
	}

	// Add all shots to handler
	handler.shootingData = append(handler.shootingData, sprayShots...)
	handler.shootingData = append(handler.shootingData, isolatedShots...)
	handler.shootingData = append(handler.shootingData, nonSprayShots...)

	// Run spraying pattern detection
	handler.DetectSprayingPatterns()

	// Check results
	sprayingCount := 0
	isolatedCount := 0
	nonSprayCount := 0

	for _, shot := range handler.shootingData {
		if shot.PlayerID == "player1" && shot.WeaponName == "ak47" {
			if shot.IsSpraying {
				sprayingCount++
			}
		} else if shot.PlayerID == "player2" && shot.WeaponName == "ak47" {
			if !shot.IsSpraying {
				isolatedCount++
			}
		} else if shot.PlayerID == "player3" && shot.WeaponName == "awp" {
			if !shot.IsSpraying {
				nonSprayCount++
			}
		}
	}

	// Verify spray pattern was detected
	if sprayingCount != 5 {
		t.Errorf("Expected 5 spraying shots for player1 AK47, got %d", sprayingCount)
	}

	// Verify isolated shots were not marked as spraying
	if isolatedCount != 2 {
		t.Errorf("Expected 2 non-spraying shots for player2 AK47, got %d", isolatedCount)
	}

	// Verify non-spray weapon shots were not marked as spraying
	if nonSprayCount != 3 {
		t.Errorf("Expected 3 non-spraying shots for player3 AWP, got %d", nonSprayCount)
	}
}

func TestAnalyzeWeaponSprayPattern_NoSpray(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound: 1,
		Players:      make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger)
	handler := NewAimTrackingHandler(processor, logger)

	// Create shots that are too far apart to be considered spraying
	shots := []*types.PlayerShootingData{
		{
			PlayerID:   "player1",
			WeaponName: "ak47",
			Tick:       1000,
			IsSpraying: false,
		},
		{
			PlayerID:   "player1",
			WeaponName: "ak47",
			Tick:       1100, // 100 ticks later - too far apart
			IsSpraying: false,
		},
	}

	handler.analyzeWeaponSprayPattern(shots)

	// Verify no shots were marked as spraying
	for _, shot := range shots {
		if shot.IsSpraying {
			t.Errorf("Expected no spraying shots, but shot at tick %d was marked as spraying", shot.Tick)
		}
	}
}

func TestAnalyzeWeaponSprayPattern_InsufficientShots(t *testing.T) {
	matchState := &types.MatchState{
		CurrentRound: 1,
		Players:      make(map[string]*types.Player),
	}
	logger := logrus.New()
	processor := NewEventProcessor(matchState, logger)
	handler := NewAimTrackingHandler(processor, logger)

	// Create only 2 shots (insufficient for spray detection)
	shots := []*types.PlayerShootingData{
		{
			PlayerID:   "player1",
			WeaponName: "ak47",
			Tick:       1000,
			IsSpraying: false,
		},
		{
			PlayerID:   "player1",
			WeaponName: "ak47",
			Tick:       1010, // 10 ticks later
			IsSpraying: false,
		},
	}

	handler.analyzeWeaponSprayPattern(shots)

	// Verify no shots were marked as spraying
	for _, shot := range shots {
		if shot.IsSpraying {
			t.Errorf("Expected no spraying shots with only 2 shots, but shot at tick %d was marked as spraying", shot.Tick)
		}
	}
}
