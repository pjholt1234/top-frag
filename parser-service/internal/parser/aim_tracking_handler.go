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

	ath.logger.WithFields(logrus.Fields{
		"player_id":       shootingData.PlayerID,
		"round":           shootingData.RoundNumber,
		"tick":            shootingData.Tick,
		"weapon":          shootingData.WeaponName,
		"weapon_category": shootingData.WeaponCategory,
		"is_spraying":     shootingData.IsSpraying,
		"position":        fmt.Sprintf("(%.2f, %.2f, %.2f)", shootingData.PositionX, shootingData.PositionY, shootingData.PositionZ),
	}).Debug("Recorded weapon fire for aim tracking")

	return nil
}

// DetectSprayingPatternsForRound analyzes shots for a specific round and marks spraying shots
func (ath *AimTrackingHandler) DetectSprayingPatternsForRound(roundNumber int) {
	ath.logger.WithFields(logrus.Fields{
		"round":       roundNumber,
		"total_shots": len(ath.shootingData),
	}).Info("Starting post-round spraying pattern detection for specific round")

	// Filter shots for the current round
	var roundShots []*types.PlayerShootingData
	for i := range ath.shootingData {
		shot := &ath.shootingData[i]
		if shot.RoundNumber == roundNumber {
			roundShots = append(roundShots, shot)
		}
	}

	ath.logger.WithFields(logrus.Fields{
		"round":       roundNumber,
		"round_shots": len(roundShots),
		"total_shots": len(ath.shootingData),
	}).Info("DEBUG: Filtered shots for round")

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

	// DEBUG: Log detailed grouping information
	ath.logger.WithFields(logrus.Fields{
		"round":         roundNumber,
		"players_count": len(playerWeaponShots),
	}).Info("DEBUG: Grouped shots by player and weapon for round")

	for playerID, weaponShots := range playerWeaponShots {
		ath.logger.WithFields(logrus.Fields{
			"round":         roundNumber,
			"player_id":     playerID,
			"weapons_count": len(weaponShots),
		}).Info("DEBUG: Player weapon breakdown for round")

		for weaponName, shots := range weaponShots {
			ath.logger.WithFields(logrus.Fields{
				"round":           roundNumber,
				"player_id":       playerID,
				"weapon_name":     weaponName,
				"shots_count":     len(shots),
				"is_spray_weapon": types.IsSprayWeapon(weaponName),
			}).Info("DEBUG: Weapon details for round")
		}
	}

	// Analyze each player's weapon usage for spray patterns
	for _, weaponShots := range playerWeaponShots {
		for weaponName, shots := range weaponShots {
			ath.logger.WithFields(logrus.Fields{
				"round":           roundNumber,
				"weapon_name":     weaponName,
				"is_spray_weapon": types.IsSprayWeapon(weaponName),
				"shots_count":     len(shots),
			}).Info("Analyzing weapon for spray patterns")

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

	// DEBUG: Log detailed spraying detection results for this round
	ath.logger.WithFields(logrus.Fields{
		"round":          roundNumber,
		"round_shots":    len(roundShots),
		"spraying_shots": sprayingShotsCount,
		"spray_percentage": func() float64 {
			if len(roundShots) > 0 {
				return float64(sprayingShotsCount) / float64(len(roundShots)) * 100.0
			}
			return 0.0
		}(),
	}).Info("DEBUG: Completed post-round spraying pattern detection for round - summary")

	// DEBUG: Log each shot that was marked as spraying for this round
	for _, shot := range roundShots {
		if shot.IsSpraying {
			ath.logger.WithFields(logrus.Fields{
				"round":       roundNumber,
				"player_id":   shot.PlayerID,
				"weapon":      shot.WeaponName,
				"tick":        shot.Tick,
				"is_spraying": shot.IsSpraying,
			}).Info("DEBUG: Final spraying shot detected for round")
		}
	}
}

// DetectSprayingPatterns analyzes all shots for the current round and marks spraying shots
func (ath *AimTrackingHandler) DetectSprayingPatterns() {
	ath.logger.WithFields(logrus.Fields{
		"total_shots": len(ath.shootingData),
	}).Info("Starting post-round spraying pattern detection")

	// Group shots by player and weapon for analysis
	playerWeaponShots := make(map[string]map[string][]*types.PlayerShootingData)

	for i := range ath.shootingData {
		shot := &ath.shootingData[i]
		playerID := shot.PlayerID
		weaponName := shot.WeaponName

		if playerWeaponShots[playerID] == nil {
			playerWeaponShots[playerID] = make(map[string][]*types.PlayerShootingData)
		}

		playerWeaponShots[playerID][weaponName] = append(playerWeaponShots[playerID][weaponName], shot)
	}

	// DEBUG: Log detailed grouping information
	ath.logger.WithFields(logrus.Fields{
		"players_count": len(playerWeaponShots),
	}).Info("DEBUG: Grouped shots by player and weapon")

	for playerID, weaponShots := range playerWeaponShots {
		ath.logger.WithFields(logrus.Fields{
			"player_id":     playerID,
			"weapons_count": len(weaponShots),
		}).Info("DEBUG: Player weapon breakdown")

		for weaponName, shots := range weaponShots {
			ath.logger.WithFields(logrus.Fields{
				"player_id":       playerID,
				"weapon_name":     weaponName,
				"shots_count":     len(shots),
				"is_spray_weapon": types.IsSprayWeapon(weaponName),
			}).Info("DEBUG: Weapon details")
		}
	}

	// Analyze each player's weapon usage for spray patterns
	for _, weaponShots := range playerWeaponShots {
		for weaponName, shots := range weaponShots {
			ath.logger.WithFields(logrus.Fields{
				"weapon_name":     weaponName,
				"is_spray_weapon": types.IsSprayWeapon(weaponName),
				"shots_count":     len(shots),
			}).Info("Analyzing weapon for spray patterns")

			if !types.IsSprayWeapon(weaponName) {
				continue
			}

			ath.analyzeWeaponSprayPattern(shots)
		}
	}

	// Count how many shots were marked as spraying
	sprayingShotsCount := 0
	for _, shot := range ath.shootingData {
		if shot.IsSpraying {
			sprayingShotsCount++
		}
	}

	// DEBUG: Log detailed spraying detection results
	ath.logger.WithFields(logrus.Fields{
		"total_shots":      len(ath.shootingData),
		"spraying_shots":   sprayingShotsCount,
		"spray_percentage": float64(sprayingShotsCount) / float64(len(ath.shootingData)) * 100.0,
	}).Info("DEBUG: Completed post-round spraying pattern detection - summary")

	// DEBUG: Log each shot that was marked as spraying
	for _, shot := range ath.shootingData {
		if shot.IsSpraying {
			ath.logger.WithFields(logrus.Fields{
				"player_id":   shot.PlayerID,
				"weapon":      shot.WeaponName,
				"tick":        shot.Tick,
				"round":       shot.RoundNumber,
				"is_spraying": shot.IsSpraying,
			}).Info("DEBUG: Final spraying shot detected")
		}
	}
}

// analyzeWeaponSprayPattern analyzes shots for a specific weapon and marks spraying shots
func (ath *AimTrackingHandler) analyzeWeaponSprayPattern(shots []*types.PlayerShootingData) {
	ath.logger.WithFields(logrus.Fields{
		"weapon":      shots[0].WeaponName,
		"shots_count": len(shots),
	}).Info("Starting weapon spray pattern analysis")

	if len(shots) < 2 {
		ath.logger.WithFields(logrus.Fields{
			"weapon":      shots[0].WeaponName,
			"shots_count": len(shots),
		}).Info("Insufficient shots for spray detection")
		return // Need at least 2 shots to detect spraying
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

		ath.logger.WithFields(logrus.Fields{
			"weapon":       shots[i].WeaponName,
			"spray_shots":  len(sprayShots),
			"min_required": minSprayShots,
			"window_ticks": sprayWindowTicks,
			"start_tick":   shots[i].Tick,
			"end_tick":     sprayShots[len(sprayShots)-1].Tick,
		}).Info("Checking spray pattern")

		// If we have enough shots in the window, mark them as spraying
		if len(sprayShots) >= minSprayShots {
			for _, shot := range sprayShots {
				shot.IsSpraying = true
			}

			ath.logger.WithFields(logrus.Fields{
				"player_id":   shots[i].PlayerID,
				"weapon":      shots[i].WeaponName,
				"spray_shots": len(sprayShots),
				"start_tick":  shots[i].Tick,
				"end_tick":    sprayShots[len(sprayShots)-1].Tick,
			}).Info("Detected spray pattern")

			// DEBUG: Log each shot that was marked as spraying
			for _, shot := range sprayShots {
				ath.logger.WithFields(logrus.Fields{
					"player_id":   shot.PlayerID,
					"weapon":      shot.WeaponName,
					"tick":        shot.Tick,
					"is_spraying": shot.IsSpraying,
				}).Info("DEBUG: Marked shot as spraying")
			}

			// Skip ahead to avoid overlapping spray detection
			i += len(sprayShots) - 1
		} else {
			// DEBUG: Log when spray pattern is not detected
			ath.logger.WithFields(logrus.Fields{
				"player_id":    shots[i].PlayerID,
				"weapon":       shots[i].WeaponName,
				"spray_shots":  len(sprayShots),
				"min_required": minSprayShots,
				"start_tick":   shots[i].Tick,
				"end_tick":     sprayShots[len(sprayShots)-1].Tick,
			}).Info("DEBUG: Spray pattern NOT detected - insufficient shots")
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
