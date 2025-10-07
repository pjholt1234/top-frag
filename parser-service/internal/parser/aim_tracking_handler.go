package parser

import (
	"fmt"
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

// AimTrackingHandler handles aim tracking and shooting data collection
type AimTrackingHandler struct {
	processor    *EventProcessor
	logger       *logrus.Logger
	shootingData []types.PlayerShootingData
}

// NewAimTrackingHandler creates a new aim tracking handler
func NewAimTrackingHandler(processor *EventProcessor, logger *logrus.Logger) *AimTrackingHandler {
	return &AimTrackingHandler{
		processor:    processor,
		logger:       logger,
		shootingData: make([]types.PlayerShootingData, 0),
	}
}

// HandleWeaponFire handles weapon fire events for aim tracking
func (ath *AimTrackingHandler) HandleWeaponFire(e events.WeaponFire) error {
	if e.Shooter == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "shooter is nil", nil).
			WithContext("event_type", "WeaponFire")
	}

	if e.Weapon == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "weapon is nil", nil).
			WithContext("event_type", "WeaponFire")
	}

	weaponName := e.Weapon.String()
	normalizedWeaponName := types.NormalizeWeaponName(weaponName)

	// Check if weapon should be excluded from aim tracking
	if types.ShouldExcludeFromAimTracking(weaponName) {
		return nil
	}

	weaponCategory := types.GetWeaponCategory(weaponName)

	playerPos := ath.processor.getPlayerPosition(e.Shooter)

	shootingData := types.PlayerShootingData{
		MatchID:        ath.processor.matchID,
		RoundNumber:    ath.processor.matchState.CurrentRound,
		Tick:           ath.processor.currentTick,
		PlayerID:       types.SteamIDToString(e.Shooter.SteamID64),
		PositionX:      playerPos.X,
		PositionY:      playerPos.Y,
		PositionZ:      playerPos.Z,
		WeaponName:     normalizedWeaponName, // Use normalized weapon name
		WeaponCategory: weaponCategory,
		IsSpraying:     false, // Will be determined post-round
	}

	ath.shootingData = append(ath.shootingData, shootingData)

	return nil
}

// DetectSprayingPatternsForRound analyzes shots for a specific round and marks spraying shots
func (ath *AimTrackingHandler) DetectSprayingPatternsForRound(roundNumber int) {
	// Filter shots for the current round
	var roundShots []*types.PlayerShootingData
	for i := range ath.shootingData {
		shot := &ath.shootingData[i]
		if shot.RoundNumber == roundNumber {
			roundShots = append(roundShots, shot)
		}
	}

	// Group shots by player and weapon for analysis
	playerWeaponShots := make(map[string]map[string][]*types.PlayerShootingData)
	for _, shot := range roundShots {
		playerID := shot.PlayerID
		weaponName := shot.WeaponName
		if playerWeaponShots[playerID] == nil {
			playerWeaponShots[playerID] = make(map[string][]*types.PlayerShootingData)
		}
		playerWeaponShots[playerID][weaponName] = append(playerWeaponShots[playerID][weaponName], shot)
	}

	// Analyze each player's weapon usage for spray patterns
	for _, weaponShots := range playerWeaponShots {
		for weaponName, shots := range weaponShots {
			if !types.IsSprayWeapon(weaponName) {
				continue
			}
			ath.analyzeWeaponSprayPattern(shots)
		}
	}

	// Count how many shots were marked as spraying for this round
	sprayingShotsCount := 0
	for _, shot := range roundShots {
		if shot.IsSpraying {
			sprayingShotsCount++
		}
	}

	// DEBUG: Log each shot that was marked as spraying for this round
	for _, shot := range roundShots {
		if shot.IsSpraying {
			_ = fmt.Sprintf("%d %s %s %d %t", roundNumber, shot.PlayerID, shot.WeaponName, shot.Tick, shot.IsSpraying)
		}
	}
}

// DetectSprayingPatterns analyzes all shots for the current round and marks spraying shots
func (ath *AimTrackingHandler) DetectSprayingPatterns() {
}

// analyzeWeaponSprayPattern analyzes shots for a specific weapon and marks spraying shots
func (ath *AimTrackingHandler) analyzeWeaponSprayPattern(shots []*types.PlayerShootingData) {
	if len(shots) == 0 {
		return
	}
	// Need at least 2 shots to detect spraying
	if len(shots) < 2 {
		return
	}

	// Sort shots by tick to ensure chronological order
	for i := 0; i < len(shots)-1; i++ {
		for j := i + 1; j < len(shots); j++ {
			if shots[i].Tick > shots[j].Tick {
				shots[i], shots[j] = shots[j], shots[i]
			}
		}
	}

	// Detect spray sequences using a sliding window approach
	sprayWindowTicks := int64(13) // ~0.2 seconds at 64 tick rate (600 RPM = 10 shots/sec = 6.4 ticks/shot)
	minSprayShots := 2            // Lower minimum for better detection

	for i := 0; i < len(shots); i++ {
		sprayShots := []*types.PlayerShootingData{shots[i]}

		// Find all shots within the spray window
		for j := i + 1; j < len(shots); j++ {
			if shots[j].Tick-shots[i].Tick <= sprayWindowTicks {
				sprayShots = append(sprayShots, shots[j])
			} else {
				break
			}
		}

		// If we have enough shots in the window, mark them as spraying
		if len(sprayShots) >= minSprayShots {
			for _, shot := range sprayShots {
				shot.IsSpraying = true
			}
		}
	}
}

// GetShootingData returns the collected shooting data
func (ath *AimTrackingHandler) GetShootingData() []types.PlayerShootingData {
	return ath.shootingData
}

// ClearShootingData clears the shooting data for a new round
func (ath *AimTrackingHandler) ClearShootingData() {
	ath.shootingData = make([]types.PlayerShootingData, 0)
}
