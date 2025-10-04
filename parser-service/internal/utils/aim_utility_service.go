package utils

import (
	"math"
	"parser-service/internal/types"
	"runtime"
	"sort"
	"strings"
	"sync"

	"github.com/sirupsen/logrus"
)

// CalculationCache holds cached expensive calculations
type CalculationCache struct {
	distanceCache map[string]float64
	vectorCache   map[string]Vector3
	fovCache      map[string]bool
	mutex         sync.RWMutex
}

// ObjectPool holds reusable objects to reduce memory allocations
type ObjectPool struct {
	vectorPool   sync.Pool
	trianglePool sync.Pool
	resultPool   sync.Pool
}

// AimUtilityService handles aim tracking calculations and analysis
type AimUtilityService struct {
	logger      *logrus.Logger
	losDetector *LOSDetector
	cache       *CalculationCache
	pool        *ObjectPool
}

// Constants for reaction time calculation
const (
	// EngagementGapSeconds - time gap in seconds to consider a new engagement
	EngagementGapSeconds = 5

	// ReactionTimeSearchWindow - search window for player movement data before damage
	ReactionTimeSearchWindow = 128

	// MaxParallelLOSWorkers - maximum number of parallel workers for LOS detection
	MaxParallelLOSWorkers = 9

	// LOSCheckInterval - check LOS every N ticks to improve performance
	LOSCheckInterval = 4

	// MovementThreshold - minimum movement to trigger LOS check
	MovementThreshold = 5.0
)

// NewAimUtilityService creates a new aim utility service
func NewAimUtilityService(logger *logrus.Logger, mapName string) (*AimUtilityService, error) {
	losDetector, err := NewLOSDetector(mapName)
	if err != nil {
		return nil, err
	}

	return &AimUtilityService{
		logger:      logger,
		losDetector: losDetector,
		cache: &CalculationCache{
			distanceCache: make(map[string]float64),
			vectorCache:   make(map[string]Vector3),
			fovCache:      make(map[string]bool),
		},
		pool: &ObjectPool{
			vectorPool: sync.Pool{
				New: func() interface{} { return &Vector3{} },
			},
			trianglePool: sync.Pool{
				New: func() interface{} { return &Triangle{} },
			},
			resultPool: sync.Pool{
				New: func() interface{} { return &LOSResult{} },
			},
		},
	}, nil
}

// ProcessAimTrackingForRound processes all shooting and damage data for a given round
func (aus *AimUtilityService) ProcessAimTrackingForRound(
	shootingData []types.PlayerShootingData,
	damageEvents []types.DamageEvent,
	playerTickData []types.PlayerTickData,
	roundNumber int,
) ([]types.AimAnalysisResult, []types.WeaponAimAnalysisResult, error) {

	aus.logger.WithFields(logrus.Fields{
		"round":           roundNumber,
		"shooting_events": len(shootingData),
		"damage_events":   len(damageEvents),
		"player_ticks":    len(playerTickData),
	}).Info("Starting aim tracking analysis for round")

	// Group shooting data by player
	playerShootingData := aus.groupShootingDataByPlayer(shootingData, roundNumber)
	aus.logger.WithFields(logrus.Fields{
		"round":              roundNumber,
		"players_with_shots": len(playerShootingData),
	}).Debug("Grouped shooting data by player")

	// Group damage events by player
	playerDamageEvents := aus.groupDamageEventsByPlayer(damageEvents, roundNumber)
	aus.logger.WithFields(logrus.Fields{
		"round":               roundNumber,
		"players_with_damage": len(playerDamageEvents),
	}).Debug("Grouped damage events by player")

	var aimResults []types.AimAnalysisResult
	var weaponResults []types.WeaponAimAnalysisResult

	// Process reaction times using time-gap approach
	reactionTimes := aus.calculateReactionTimesFromDamageEvents(damageEvents, playerTickData, roundNumber)

	// Process each player's data
	for playerID, shots := range playerShootingData {
		damages := playerDamageEvents[playerID]

		// DEBUG: Count spraying shots before processing
		sprayingShotsCount := 0
		for _, shot := range shots {
			if shot.IsSpraying {
				sprayingShotsCount++
			}
		}

		aus.logger.WithFields(logrus.Fields{
			"round":          roundNumber,
			"player_id":      playerID,
			"shots_count":    len(shots),
			"damages_count":  len(damages),
			"spraying_shots": sprayingShotsCount,
		}).Info("DEBUG: Processing aim tracking for player - pre-analysis")

		// Analyze all shots once to avoid duplicate calculations
		shotAnalyses := aus.analyzeShots(shots, damages, playerTickData, playerID, roundNumber)

		// Calculate basic aim statistics using pre-analyzed shot data
		aimResult := aus.calculatePlayerAimStatsFromAnalysis(playerID, roundNumber, shotAnalyses)

		// Integrate reaction times from damage events
		if reactionTime, exists := reactionTimes[playerID]; exists {
			// Only integrate if we have a valid reaction time (> 0)
			if reactionTime > 0 {
				// Average with existing reaction time: (old + new) / 2
				if aimResult.AverageTimeToDamage > 0 {
					aimResult.AverageTimeToDamage = (aimResult.AverageTimeToDamage + reactionTime) / 2.0
				} else {
					aimResult.AverageTimeToDamage = reactionTime
				}

				aus.logger.WithFields(logrus.Fields{
					"round":           roundNumber,
					"player_id":       playerID,
					"damage_reaction": reactionTime,
					"final_reaction":  aimResult.AverageTimeToDamage,
				}).Debug("Integrated damage-based reaction time")
			} else {
				aus.logger.WithFields(logrus.Fields{
					"round":           roundNumber,
					"player_id":       playerID,
					"damage_reaction": reactionTime,
				}).Debug("Skipped integration of invalid reaction time (0.0)")
			}
		}

		aimResults = append(aimResults, aimResult)

		// Calculate weapon-specific statistics using pre-analyzed shot data
		weaponStats := aus.calculateWeaponAimStatsFromAnalysis(playerID, roundNumber, shotAnalyses)
		weaponResults = append(weaponResults, weaponStats...)

		aus.logger.WithFields(logrus.Fields{
			"round":                roundNumber,
			"player_id":            playerID,
			"accuracy":             aimResult.AccuracyAllShots,
			"spray_accuracy":       aimResult.SprayingAccuracy,
			"spraying_shots_fired": aimResult.SprayingShotsFired,
			"spraying_shots_hit":   aimResult.SprayingShotsHit,
			"time_to_damage":       aimResult.AverageTimeToDamage,
			"weapon_stats_count":   len(weaponStats),
		}).Info("DEBUG: Completed aim analysis for player - post-analysis")
	}

	aus.logger.WithFields(logrus.Fields{
		"round":          roundNumber,
		"aim_results":    len(aimResults),
		"weapon_results": len(weaponResults),
	}).Info("Completed aim tracking analysis for round")

	return aimResults, weaponResults, nil
}

// calculateReactionTimesFromDamageEvents calculates reaction times using time-gap approach
func (aus *AimUtilityService) calculateReactionTimesFromDamageEvents(
	damageEvents []types.DamageEvent,
	playerTickData []types.PlayerTickData,
	roundNumber int,
) map[string]float64 {
	reactionTimes := make(map[string]float64)

	aus.logger.WithFields(logrus.Fields{
		"round":         roundNumber,
		"damage_events": len(damageEvents),
		"player_ticks":  len(playerTickData),
		"gap_seconds":   EngagementGapSeconds,
	}).Info("Starting reaction time calculation from damage events")

	// Sort damage events by timestamp
	sortedDamageEvents := make([]types.DamageEvent, len(damageEvents))
	copy(sortedDamageEvents, damageEvents)

	// Simple bubble sort by tick timestamp
	for i := 0; i < len(sortedDamageEvents)-1; i++ {
		for j := 0; j < len(sortedDamageEvents)-i-1; j++ {
			if sortedDamageEvents[j].TickTimestamp > sortedDamageEvents[j+1].TickTimestamp {
				sortedDamageEvents[j], sortedDamageEvents[j+1] = sortedDamageEvents[j+1], sortedDamageEvents[j]
			}
		}
	}

	// Debug: Log first few damage events to verify sorting
	aus.logger.WithFields(logrus.Fields{
		"round":        roundNumber,
		"total_events": len(sortedDamageEvents),
	}).Debug("Sorted damage events for first shot detection")

	// Find first shots using time gap approach
	firstShots := aus.identifyFirstShots(sortedDamageEvents)

	aus.logger.WithFields(logrus.Fields{
		"round":       roundNumber,
		"first_shots": len(firstShots),
	}).Info("Identified first shots using time gap approach")

	// Calculate reaction times for first shots
	for _, damage := range firstShots {
		reactionTime := aus.calculateReactionTimeForFirstShot(damage, playerTickData)
		if reactionTime > 0 {
			reactionTimes[damage.AttackerSteamID] = reactionTime

			aus.logger.WithFields(logrus.Fields{
				"attacker_id":   damage.AttackerSteamID,
				"victim_id":     damage.VictimSteamID,
				"damage_tick":   damage.TickTimestamp,
				"reaction_time": reactionTime,
			}).Debug("Calculated reaction time for first shot")
		}
	}

	aus.logger.WithFields(logrus.Fields{
		"round":             roundNumber,
		"reaction_times":    len(reactionTimes),
		"first_shots_found": len(firstShots),
	}).Info("Completed reaction time calculation from damage events")

	return reactionTimes
}

// identifyFirstShots identifies first shots using time gap approach
// Only considers gun damage events (not grenades or knife damage)
func (aus *AimUtilityService) identifyFirstShots(damageEvents []types.DamageEvent) []types.DamageEvent {
	var firstShots []types.DamageEvent
	gapTicks := int64(EngagementGapSeconds * 64) // Convert seconds to ticks

	aus.logger.WithFields(logrus.Fields{
		"total_events": len(damageEvents),
		"gap_ticks":    gapTicks,
	}).Debug("Identifying first shots using time gap")

	for i, damage := range damageEvents {
		// Skip non-gun damage events (grenades, knife, etc.)
		if !aus.isGunDamage(damage) {
			continue
		}

		isFirstShot := true
		previousDamageCount := 0

		// Check if there was damage from the same attacker-victim-weapon combination within the gap window
		for j := i - 1; j >= 0; j-- {
			timeDiff := damage.TickTimestamp - damageEvents[j].TickTimestamp
			if timeDiff > gapTicks {
				break // We're beyond the gap window
			}

			// If same attacker-victim-weapon dealt damage within gap window, this isn't a first shot
			if damageEvents[j].AttackerSteamID == damage.AttackerSteamID &&
				damageEvents[j].VictimSteamID == damage.VictimSteamID &&
				damageEvents[j].Weapon == damage.Weapon {
				previousDamageCount++
				isFirstShot = false
				aus.logger.WithFields(logrus.Fields{
					"current_attacker":  damage.AttackerSteamID,
					"current_victim":    damage.VictimSteamID,
					"current_weapon":    damage.Weapon,
					"current_tick":      damage.TickTimestamp,
					"previous_attacker": damageEvents[j].AttackerSteamID,
					"previous_victim":   damageEvents[j].VictimSteamID,
					"previous_weapon":   damageEvents[j].Weapon,
					"previous_tick":     damageEvents[j].TickTimestamp,
					"time_diff":         timeDiff,
					"gap_ticks":         gapTicks,
				}).Debug("Found previous damage from same attacker-victim-weapon - not first shot")
				break
			}
		}

		if isFirstShot {
			firstShots = append(firstShots, damage)
			aus.logger.WithFields(logrus.Fields{
				"attacker_id":           damage.AttackerSteamID,
				"victim_id":             damage.VictimSteamID,
				"weapon":                damage.Weapon,
				"tick":                  damage.TickTimestamp,
				"damage":                damage.Damage,
				"index":                 i,
				"previous_damage_count": previousDamageCount,
			}).Info("Identified first shot")
		}
	}

	return firstShots
}

// isGunDamage checks if the damage event is from a gun (not grenade or knife)
func (aus *AimUtilityService) isGunDamage(damage types.DamageEvent) bool {
	// List of non-gun weapons to exclude
	nonGunWeapons := []string{
		"hegrenade", "he grenade", "flashbang", "flash", "smokegrenade", "smoke",
		"incgrenade", "incendiary", "molotov", "decoy",
		"knife", "knife_t", "knife_ct", "bayonet", "karambit", "m9_bayonet",
		"flip", "gut", "huntsman", "falchion", "bowie", "butterfly", "shadow_daggers",
		"zeus", "taser",
	}

	weapon := strings.ToLower(damage.Weapon)
	aus.logger.WithFields(logrus.Fields{
		"weapon":   weapon,
		"attacker": damage.AttackerSteamID,
		"victim":   damage.VictimSteamID,
	}).Debug("Checking if weapon is gun damage")

	for _, nonGun := range nonGunWeapons {
		if strings.Contains(weapon, nonGun) {
			aus.logger.WithFields(logrus.Fields{
				"weapon":  weapon,
				"non_gun": nonGun,
			}).Debug("Weapon is non-gun, excluding from first shot detection")
			return false
		}
	}

	aus.logger.WithFields(logrus.Fields{
		"weapon": weapon,
	}).Debug("Weapon is gun, including in first shot detection")
	return true
}

// findFirstAttackerLOS finds the OLDEST (first) occurrence where attacker has LOS on victim
// We only care if attacker can see victim, not vice versa
func (aus *AimUtilityService) findFirstAttackerLOS(
	attackerTicks []types.PlayerTickData,
	victimTicks []types.PlayerTickData,
	damage types.DamageEvent,
) (int64, bool) {
	// Create a map for quick victim tick lookup
	victimTickMap := make(map[int64]types.PlayerTickData)
	for _, tick := range victimTicks {
		victimTickMap[tick.Tick] = tick
	}

	// Sort attacker ticks by tick number (oldest first) - O(n log n) instead of O(n²)
	sort.Slice(attackerTicks, func(i, j int) bool {
		return attackerTicks[i].Tick < attackerTicks[j].Tick
	})

	// Find the OLDEST (first) occurrence where attacker has LOS on victim
	for _, attackerTick := range attackerTicks {
		if victimTick, exists := victimTickMap[attackerTick.Tick]; exists {
			// Check if attacker has LOS to victim at this tick
			hasLOS := aus.checkLineOfSight(&attackerTick, &victimTick)

			aus.logger.WithFields(logrus.Fields{
				"attacker_id": damage.AttackerSteamID,
				"victim_id":   damage.VictimSteamID,
				"tick":        attackerTick.Tick,
				"damage_tick": damage.TickTimestamp,
				"tick_diff":   damage.TickTimestamp - attackerTick.Tick,
				"has_los":     hasLOS,
			}).Debug("Checked LOS at tick for first occurrence")

			if hasLOS {
				aus.logger.WithFields(logrus.Fields{
					"attacker_id":     damage.AttackerSteamID,
					"victim_id":       damage.VictimSteamID,
					"visibility_tick": attackerTick.Tick,
					"damage_tick":     damage.TickTimestamp,
					"tick_diff":       damage.TickTimestamp - attackerTick.Tick,
				}).Info("Found first LOS for reaction time calculation")

				return attackerTick.Tick, true
			}
		}
	}

	return 0, false
}

// calculateReactionTimeForFirstShot calculates reaction time for a first shot
func (aus *AimUtilityService) calculateReactionTimeForFirstShot(
	damage types.DamageEvent,
	playerTickData []types.PlayerTickData,
) float64 {
	searchStartTick := damage.TickTimestamp - ReactionTimeSearchWindow
	if searchStartTick < 0 {
		searchStartTick = 0
	}

	// Find player tick data for the attacker in the search window
	var attackerTicks []types.PlayerTickData
	for _, tick := range playerTickData {
		if tick.PlayerID == damage.AttackerSteamID &&
			tick.Tick >= searchStartTick &&
			tick.Tick <= damage.TickTimestamp {
			attackerTicks = append(attackerTicks, tick)
		}
	}

	if len(attackerTicks) == 0 {
		aus.logger.WithFields(logrus.Fields{
			"attacker_id": damage.AttackerSteamID,
			"damage_tick": damage.TickTimestamp,
		}).Debug("No attacker tick data found for reaction time calculation")
		return 0.0
	}

	// Find when attacker first saw victim by checking LOS at each tick
	// We'll look for the first tick where attacker has LOS to victim
	var visibilityTick int64 = 0
	foundVisibility := false

	// Sort attacker ticks by tick number - O(n log n) instead of O(n²)
	sort.Slice(attackerTicks, func(i, j int) bool {
		return attackerTicks[i].Tick < attackerTicks[j].Tick
	})

	// Find victim tick data for the same time window
	var victimTicks []types.PlayerTickData
	for _, tick := range playerTickData {
		if tick.PlayerID == damage.VictimSteamID &&
			tick.Tick >= searchStartTick &&
			tick.Tick <= damage.TickTimestamp {
			victimTicks = append(victimTicks, tick)
		}
	}

	// Sort victim ticks by tick number (oldest first) - O(n log n) instead of O(n²)
	sort.Slice(victimTicks, func(i, j int) bool {
		return victimTicks[i].Tick < victimTicks[j].Tick
	})

	// Check LOS at each tick to find when attacker first saw victim (parallel)
	aus.logger.WithFields(logrus.Fields{
		"attacker_id":    damage.AttackerSteamID,
		"victim_id":      damage.VictimSteamID,
		"damage_tick":    damage.TickTimestamp,
		"search_start":   searchStartTick,
		"attacker_ticks": len(attackerTicks),
		"victim_ticks":   len(victimTicks),
		"search_window":  damage.TickTimestamp - searchStartTick,
	}).Debug("Starting parallel LOS detection for reaction time calculation")

	// Use parallel processing to find the OLDEST (first) LOS occurrence
	visibilityTick, foundVisibility = aus.findFirstAttackerLOSParallel(attackerTicks, victimTicks, damage)

	// If no visibility found, we can't calculate reaction time
	if !foundVisibility {
		aus.logger.WithFields(logrus.Fields{
			"attacker_id": damage.AttackerSteamID,
			"victim_id":   damage.VictimSteamID,
			"damage_tick": damage.TickTimestamp,
		}).Debug("No LOS found, skipping reaction time calculation")
		return 0.0
	}

	// Calculate reaction time in milliseconds
	tickDiff := float64(damage.TickTimestamp - visibilityTick)
	reactionTimeMs := (tickDiff / 64.0) * 1000.0

	aus.logger.WithFields(logrus.Fields{
		"attacker_id":      damage.AttackerSteamID,
		"victim_id":        damage.VictimSteamID,
		"damage_tick":      damage.TickTimestamp,
		"visibility_tick":  visibilityTick,
		"tick_diff":        tickDiff,
		"reaction_time_ms": reactionTimeMs,
		"found_los":        foundVisibility,
	}).Debug("Calculated reaction time for first shot")

	return reactionTimeMs
}

// checkLineOfSight checks if there's line of sight between two players using FOV-aware detection
func (aus *AimUtilityService) checkLineOfSight(attacker, victim *types.PlayerTickData) bool {
	// Convert positions to Vector3 for LOS detection
	attackerPos := Vector3{
		X: float32(attacker.PositionX),
		Y: float32(attacker.PositionY),
		Z: float32(attacker.PositionZ),
	}

	victimPos := Vector3{
		X: float32(victim.PositionX),
		Y: float32(victim.PositionY),
		Z: float32(victim.PositionZ),
	}

	// Default player box dimensions (72 units tall, 32 units wide, 32 units deep)
	playerBox := Box{
		Width:  32, // X dimension
		Height: 72, // Y dimension
		Depth:  32, // Z dimension
	}

	// Use FOV-aware LOS detection with player dimensions
	// We only care if the attacker can see the victim (not vice versa)
	attackerCanSee, _, _ := aus.losDetector.CheckLineOfSightWithFOVAndBox(
		attackerPos, victimPos, playerBox, playerBox,
		attacker.AimX, attacker.AimY, // Attacker's aim angles
		victim.AimX, victim.AimY, // Victim's aim angles (not used for LOS check)
	)

	return attackerCanSee
}

// groupShootingDataByPlayer groups shooting data by player for a specific round
func (aus *AimUtilityService) groupShootingDataByPlayer(
	shootingData []types.PlayerShootingData,
	roundNumber int,
) map[string][]types.PlayerShootingData {
	playerData := make(map[string][]types.PlayerShootingData)

	for _, shot := range shootingData {
		if shot.RoundNumber == roundNumber {
			playerData[shot.PlayerID] = append(playerData[shot.PlayerID], shot)
		}
	}

	return playerData
}

// groupDamageEventsByPlayer groups damage events by attacker for a specific round
func (aus *AimUtilityService) groupDamageEventsByPlayer(
	damageEvents []types.DamageEvent,
	roundNumber int,
) map[string][]types.DamageEvent {
	playerDamages := make(map[string][]types.DamageEvent)

	for _, damage := range damageEvents {
		if damage.RoundNumber == roundNumber {
			playerDamages[damage.AttackerSteamID] = append(playerDamages[damage.AttackerSteamID], damage)
		}
	}

	return playerDamages
}

// calculatePlayerAimStats calculates aim statistics for a player
func (aus *AimUtilityService) calculatePlayerAimStats(
	playerID string,
	roundNumber int,
	shots []types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
) types.AimAnalysisResult {

	aus.logger.WithFields(logrus.Fields{
		"round":         roundNumber,
		"player_id":     playerID,
		"shots_count":   len(shots),
		"damages_count": len(damages),
	}).Debug("Calculating player aim statistics")

	result := types.AimAnalysisResult{
		PlayerSteamID: playerID,
		RoundNumber:   roundNumber,
		ShotsFired:    len(shots),
	}

	// Calculate shots on hit
	shotsOnHit := 0
	sprayingShotsFired := 0
	sprayingShotsHit := 0

	var crosshairPlacementsX []float64
	var crosshairPlacementsY []float64
	var reactionTimes []float64

	headHits := 0
	upperChestHits := 0
	chestHits := 0
	legsHits := 0

	for _, shot := range shots {
		// Check if this shot resulted in damage
		shotHit := false
		for _, damage := range damages {
			// A damage event is considered linked to a shot if it happened around the same tick
			// and the attacker/victim match.
			if damage.AttackerSteamID == shot.PlayerID &&
				abs(damage.TickTimestamp-shot.Tick) <= 10 { // Within 10 ticks
				shotHit = true
				shotsOnHit++

				// Categorize hit region
				switch damage.HitGroup {
				case types.HitGroupHead:
					headHits++
				case types.HitGroupChest:
					chestHits++
				case types.HitGroupStomach:
					upperChestHits++ // Group stomach with upper chest for simplicity
				case types.HitGroupLeftLeg, types.HitGroupRightLeg:
					legsHits++
				}
				break // Only count one damage event per shot for hit detection
			}
		}

		// Track spraying shots
		if shot.IsSpraying {
			sprayingShotsFired++
			if shotHit {
				sprayingShotsHit++
			}

			// Debug logging for spraying shots
			aus.logger.WithFields(logrus.Fields{
				"round":          roundNumber,
				"player_id":      playerID,
				"shot_tick":      shot.Tick,
				"weapon":         shot.WeaponName,
				"is_spraying":    shot.IsSpraying,
				"shot_hit":       shotHit,
				"spraying_fired": sprayingShotsFired,
				"spraying_hit":   sprayingShotsHit,
			}).Info("Processing spraying shot")
		}

		// Calculate crosshair placement using LOS detection
		crosshairX, crosshairY := aus.calculateCrosshairPlacement(shot, damages, playerTickData)
		crosshairPlacementsX = append(crosshairPlacementsX, crosshairX)
		crosshairPlacementsY = append(crosshairPlacementsY, crosshairY)

		// Calculate reaction time
		reactionTime := aus.calculateReactionTime(shot, damages, playerTickData)
		reactionTimes = append(reactionTimes, reactionTime)

		aus.logger.WithFields(logrus.Fields{
			"round":         roundNumber,
			"player_id":     playerID,
			"shot_tick":     shot.Tick,
			"weapon":        shot.WeaponName,
			"is_spraying":   shot.IsSpraying,
			"crosshair_x":   crosshairX,
			"crosshair_y":   crosshairY,
			"reaction_time": reactionTime,
		}).Debug("Processed individual shot analysis")
	}

	// Calculate accuracy
	// Persist shots hit for downstream consumers
	result.ShotsHit = shotsOnHit
	if result.ShotsFired > 0 {
		result.AccuracyAllShots = float64(result.ShotsHit) / float64(result.ShotsFired) * 100.0
	}

	// Calculate spray accuracy
	result.SprayingShotsFired = sprayingShotsFired
	result.SprayingShotsHit = sprayingShotsHit
	if sprayingShotsFired > 0 {
		result.SprayingAccuracy = float64(sprayingShotsHit) / float64(sprayingShotsFired) * 100.0
	}

	// DEBUG: Log final spraying statistics
	aus.logger.WithFields(logrus.Fields{
		"round":                roundNumber,
		"player_id":            playerID,
		"total_shots":          len(shots),
		"spraying_shots_fired": sprayingShotsFired,
		"spraying_shots_hit":   sprayingShotsHit,
		"spraying_accuracy":    result.SprayingAccuracy,
		"shots_hit":            shotsOnHit,
		"accuracy_all_shots":   result.AccuracyAllShots,
	}).Info("DEBUG: Final spraying statistics calculated")

	// Calculate average crosshair placement
	if len(crosshairPlacementsX) > 0 {
		result.AverageCrosshairPlacementX = aus.calculateAverage(crosshairPlacementsX)
		result.AverageCrosshairPlacementY = aus.calculateAverage(crosshairPlacementsY)
	}

	// Calculate headshot accuracy
	if result.ShotsHit > 0 {
		result.HeadshotAccuracy = float64(headHits) / float64(result.ShotsHit) * 100.0
	}

	// Calculate average reaction time
	if len(reactionTimes) > 0 {
		result.AverageTimeToDamage = aus.calculateAverage(reactionTimes)
	}

	// Set hit region totals
	result.HeadHitsTotal = headHits
	result.UpperChestHitsTotal = upperChestHits
	result.ChestHitsTotal = chestHits
	result.LegsHitsTotal = legsHits

	return result
}

// calculateWeaponAimStats calculates weapon-specific aim statistics for a player
func (aus *AimUtilityService) calculateWeaponAimStats(
	playerID string,
	roundNumber int,
	shots []types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
) []types.WeaponAimAnalysisResult {

	// Group shots by weapon
	weaponShots := make(map[string][]types.PlayerShootingData)
	for _, shot := range shots {
		weaponShots[shot.WeaponName] = append(weaponShots[shot.WeaponName], shot)
	}

	var results []types.WeaponAimAnalysisResult

	for weaponName, weaponShotData := range weaponShots {
		result := types.WeaponAimAnalysisResult{
			PlayerSteamID:     playerID,
			RoundNumber:       roundNumber,
			WeaponName:        weaponName,
			WeaponDisplayName: types.FormatWeaponName(weaponName),
			ShotsFired:        len(weaponShotData),
		}

		// Calculate weapon-specific statistics (similar to player stats)
		shotsOnHit := 0
		sprayingShotsFired := 0
		sprayingShotsHit := 0

		headHits := 0
		upperChestHits := 0
		chestHits := 0
		legsHits := 0

		// Arrays to store crosshair placement and reaction time data for this weapon
		var crosshairPlacementsX []float64
		var crosshairPlacementsY []float64
		var reactionTimes []float64

		for _, shot := range weaponShotData {
			// Check if this shot resulted in damage
			shotHit := false
			for _, damage := range damages {
				if damage.AttackerSteamID == shot.PlayerID &&
					abs(damage.TickTimestamp-shot.Tick) <= 10 { // Within 10 ticks
					shotHit = true
					shotsOnHit++

					// Categorize hit region
					switch damage.HitGroup {
					case types.HitGroupHead:
						headHits++
					case types.HitGroupChest:
						chestHits++
					case types.HitGroupStomach:
						upperChestHits++
					case types.HitGroupLeftLeg, types.HitGroupRightLeg:
						legsHits++
					}
					break
				}
			}

			// Track spraying shots
			if shot.IsSpraying {
				sprayingShotsFired++
				if shotHit {
					sprayingShotsHit++
				}
			}

			// Calculate crosshair placement for this weapon shot
			crosshairX, crosshairY := aus.calculateCrosshairPlacement(shot, damages, playerTickData)
			crosshairPlacementsX = append(crosshairPlacementsX, crosshairX)
			crosshairPlacementsY = append(crosshairPlacementsY, crosshairY)

			// Calculate reaction time for this weapon shot
			reactionTime := aus.calculateReactionTime(shot, damages, playerTickData)
			reactionTimes = append(reactionTimes, reactionTime)

			aus.logger.WithFields(logrus.Fields{
				"round":         roundNumber,
				"player_id":     playerID,
				"weapon":        weaponName,
				"shot_tick":     shot.Tick,
				"is_spraying":   shot.IsSpraying,
				"crosshair_x":   crosshairX,
				"crosshair_y":   crosshairY,
				"reaction_time": reactionTime,
			}).Debug("Processed weapon shot analysis")
		}

		// Persist shots hit for downstream consumers
		result.ShotsHit = shotsOnHit
		// Calculate accuracy
		if result.ShotsFired > 0 {
			result.AccuracyAllShots = float64(result.ShotsHit) / float64(result.ShotsFired) * 100.0
		}

		// Calculate spray accuracy
		result.SprayingShotsFired = sprayingShotsFired
		if sprayingShotsFired > 0 {
			result.SprayingShotsHit = sprayingShotsHit
			result.SprayingAccuracy = float64(sprayingShotsHit) / float64(sprayingShotsFired) * 100.0
		}

		// Calculate average crosshair placement for this weapon
		if len(crosshairPlacementsX) > 0 {
			result.AverageCrosshairPlacementX = aus.calculateAverage(crosshairPlacementsX)
			result.AverageCrosshairPlacementY = aus.calculateAverage(crosshairPlacementsY)
		}

		// Calculate average reaction time for this weapon
		if len(reactionTimes) > 0 {
			result.AverageTimeToDamage = aus.calculateAverage(reactionTimes)
		}

		// Calculate headshot accuracy
		if result.ShotsHit > 0 {
			result.HeadshotAccuracy = float64(headHits) / float64(result.ShotsHit) * 100.0
		}

		// Set hit region totals
		result.HeadHitsTotal = headHits
		result.UpperChestHitsTotal = upperChestHits
		result.ChestHitsTotal = chestHits
		result.LegsHitsTotal = legsHits

		aus.logger.WithFields(logrus.Fields{
			"round":             roundNumber,
			"player_id":         playerID,
			"weapon":            weaponName,
			"shots_fired":       result.ShotsFired,
			"shots_hit":         result.ShotsHit,
			"accuracy":          result.AccuracyAllShots,
			"spray_accuracy":    result.SprayingAccuracy,
			"crosshair_x":       result.AverageCrosshairPlacementX,
			"crosshair_y":       result.AverageCrosshairPlacementY,
			"reaction_time":     result.AverageTimeToDamage,
			"headshot_accuracy": result.HeadshotAccuracy,
		}).Info("Completed weapon-specific aim analysis")

		results = append(results, result)
	}

	return results
}

// calculateAverage calculates the average of a slice of float64 values
func (aus *AimUtilityService) calculateAverage(values []float64) float64 {
	if len(values) == 0 {
		return 0.0
	}

	sum := 0.0
	for _, value := range values {
		sum += value
	}

	return sum / float64(len(values))
}

// abs returns the absolute value of an int64
func abs(x int64) int64 {
	if x < 0 {
		return -x
	}
	return x
}

// calculateCrosshairPlacement calculates the crosshair placement accuracy for a shot
func (aus *AimUtilityService) calculateCrosshairPlacement(
	shot types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
) (float64, float64) {

	aus.logger.WithFields(logrus.Fields{
		"player_id": shot.PlayerID,
		"shot_tick": shot.Tick,
		"weapon":    shot.WeaponName,
	}).Debug("Calculating crosshair placement for shot")

	// Find the player's position and aim data at the time of the shot
	var playerTick *types.PlayerTickData
	for _, tick := range playerTickData {
		if tick.PlayerID == shot.PlayerID && tick.Tick == shot.Tick {
			playerTick = &tick
			break
		}
	}

	if playerTick == nil {
		aus.logger.WithFields(logrus.Fields{
			"player_id": shot.PlayerID,
			"shot_tick": shot.Tick,
		}).Warn("Player tick data not found for crosshair placement calculation")
		return 0.0, 0.0
	}

	// Find the closest damage event to this shot (within 10 ticks)
	var closestDamage *types.DamageEvent
	minTimeDiff := int64(10)

	for i, damage := range damages {
		timeDiff := abs(shot.Tick - damage.TickTimestamp)
		if timeDiff <= minTimeDiff {
			minTimeDiff = timeDiff
			closestDamage = &damages[i]
		}
	}

	if closestDamage == nil {
		aus.logger.WithFields(logrus.Fields{
			"player_id": shot.PlayerID,
			"shot_tick": shot.Tick,
		}).Debug("No damage event found for crosshair placement calculation")
		return 0.0, 0.0
	}

	// Find the victim's position at the time of damage
	var victimTick *types.PlayerTickData
	for _, tick := range playerTickData {
		if tick.PlayerID == closestDamage.VictimSteamID &&
			abs(tick.Tick-closestDamage.TickTimestamp) <= 5 {
			victimTick = &tick
			break
		}
	}

	if victimTick == nil {
		return 0.0, 0.0
	}

	// Calculate crosshair placement using LOS detection
	crosshairX, crosshairY := aus.calculateCrosshairPlacementWithLOS(playerTick, victimTick)

	aus.logger.WithFields(logrus.Fields{
		"player_id":   shot.PlayerID,
		"shot_tick":   shot.Tick,
		"crosshair_x": crosshairX,
		"crosshair_y": crosshairY,
	}).Debug("Calculated crosshair placement")

	return crosshairX, crosshairY
}

// calculateCrosshairPlacementWithLOS calculates crosshair placement using LOS detection
func (aus *AimUtilityService) calculateCrosshairPlacementWithLOS(
	shooterTick, victimTick *types.PlayerTickData,
) (float64, float64) {

	// Convert positions to Vector3
	shooterPos := Vector3{
		X: float32(shooterTick.PositionX),
		Y: float32(shooterTick.PositionY),
		Z: float32(shooterTick.PositionZ),
	}

	victimPos := Vector3{
		X: float32(victimTick.PositionX),
		Y: float32(victimTick.PositionY),
		Z: float32(victimTick.PositionZ),
	}

	// Calculate the ideal aim direction (from shooter to victim)
	idealDirection := Vector3{
		X: victimPos.X - shooterPos.X,
		Y: victimPos.Y - shooterPos.Y,
		Z: victimPos.Z - shooterPos.Z,
	}
	idealDirection = normalizeVector(idealDirection)

	// Get the shooter's actual aim direction
	actualAimDirection := Vector3{
		X: float32(shooterTick.AimX),
		Y: float32(shooterTick.AimY),
		Z: 0.0, // AimZ is not available in PlayerTickData
	}
	actualAimDirection = normalizeVector(actualAimDirection)

	// Calculate the angle difference between ideal and actual aim
	angleDiff := calculateAngleBetweenVectors(idealDirection, actualAimDirection)

	// Convert angle to degrees
	angleDegrees := float64(angleDiff) * 180.0 / math.Pi

	// Calculate crosshair placement error (X and Y components)
	// This is a simplified calculation - in reality, we'd need to decompose the angle
	// into X and Y components based on the shooter's view angles
	crosshairX := angleDegrees * math.Cos(float64(shooterTick.AimX)*math.Pi/180.0)
	crosshairY := angleDegrees * math.Sin(float64(shooterTick.AimY)*math.Pi/180.0)

	// Normalize crosshair placement values to positive values
	// Use absolute value to ensure positive crosshair placement measurements
	crosshairX = math.Abs(crosshairX)
	crosshairY = math.Abs(crosshairY)

	return crosshairX, crosshairY
}

// calculateReactionTime calculates the reaction time for a shot
func (aus *AimUtilityService) calculateReactionTime(
	shot types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
) float64 {

	aus.logger.WithFields(logrus.Fields{
		"player_id": shot.PlayerID,
		"shot_tick": shot.Tick,
		"weapon":    shot.WeaponName,
	}).Debug("Calculating reaction time for shot")

	// Find the closest damage event to this shot (within 10 ticks)
	var closestDamage *types.DamageEvent
	minTimeDiff := int64(10)

	for i, damage := range damages {
		timeDiff := abs(shot.Tick - damage.TickTimestamp)
		if timeDiff <= minTimeDiff {
			minTimeDiff = timeDiff
			closestDamage = &damages[i]
		}
	}

	if closestDamage == nil {
		aus.logger.WithFields(logrus.Fields{
			"player_id": shot.PlayerID,
			"shot_tick": shot.Tick,
		}).Debug("No damage event found for reaction time calculation")
		return 0.0
	}

	// Find when the victim first came into line of sight
	// This is a simplified calculation - in reality, we'd need to track
	// when the victim first became visible to the shooter
	// For now, we'll use the time difference between shot and damage
	// as a proxy for reaction time

	// Convert ticks to milliseconds (assuming 64 tick server)
	tickDiff := float64(abs(shot.Tick - closestDamage.TickTimestamp))
	reactionTimeMs := (tickDiff / 64.0) * 1000.0 // Convert to milliseconds

	aus.logger.WithFields(logrus.Fields{
		"player_id":        shot.PlayerID,
		"shot_tick":        shot.Tick,
		"tick_diff":        tickDiff,
		"reaction_time_ms": reactionTimeMs,
	}).Debug("Calculated reaction time")

	return reactionTimeMs
}

// normalizeVector normalizes a vector to unit length
func normalizeVector(v Vector3) Vector3 {
	length := float32(math.Sqrt(float64(v.X*v.X + v.Y*v.Y + v.Z*v.Z)))
	if length == 0 {
		return v
	}
	return Vector3{
		X: v.X / length,
		Y: v.Y / length,
		Z: v.Z / length,
	}
}

// calculateAngleBetweenVectors calculates the angle between two vectors in radians
func calculateAngleBetweenVectors(v1, v2 Vector3) float32 {
	dot := v1.X*v2.X + v1.Y*v2.Y + v1.Z*v2.Z
	// Clamp dot product to avoid numerical errors
	if dot > 1.0 {
		dot = 1.0
	} else if dot < -1.0 {
		dot = -1.0
	}
	return float32(math.Acos(float64(dot)))
}

// findFirstAttackerLOSParallel finds the OLDEST (first) occurrence where attacker has LOS on victim using parallel processing
func (aus *AimUtilityService) findFirstAttackerLOSParallel(
	attackerTicks []types.PlayerTickData,
	victimTicks []types.PlayerTickData,
	damage types.DamageEvent,
) (int64, bool) {
	if len(attackerTicks) == 0 || len(victimTicks) == 0 {
		return 0, false
	}

	// Create a map for quick victim tick lookup
	victimTickMap := make(map[int64]types.PlayerTickData)
	for _, tick := range victimTicks {
		victimTickMap[tick.Tick] = tick
	}

	// Create channels for work distribution
	jobs := make(chan int, len(attackerTicks))
	results := make(chan LOSResult, len(attackerTicks))

	// Use dynamic worker count based on data size and CPU cores
	numWorkers := min(len(attackerTicks)/10, runtime.NumCPU())
	if numWorkers < 1 {
		numWorkers = 1
	}
	if numWorkers > MaxParallelLOSWorkers {
		numWorkers = MaxParallelLOSWorkers
	}

	aus.logger.WithFields(logrus.Fields{
		"attacker_id": damage.AttackerSteamID,
		"victim_id":   damage.VictimSteamID,
		"total_ticks": len(attackerTicks),
		"workers":     numWorkers,
	}).Debug("Starting parallel LOS workers for first occurrence")

	var wg sync.WaitGroup

	// Start workers
	for i := 0; i < numWorkers; i++ {
		wg.Add(1)
		go func(workerID int) {
			defer wg.Done()
			for tickIndex := range jobs {
				attackerTick := attackerTicks[tickIndex]

				// Only check LOS every 4 ticks for performance
				if tickIndex%LOSCheckInterval != 0 {
					continue
				}

				// Find corresponding victim tick
				if victimTick, exists := victimTickMap[attackerTick.Tick]; exists {
					// Check LOS between attacker and victim at this tick
					hasLOS := aus.checkLineOfSight(&attackerTick, &victimTick)

					aus.logger.WithFields(logrus.Fields{
						"attacker_id": damage.AttackerSteamID,
						"victim_id":   damage.VictimSteamID,
						"tick":        attackerTick.Tick,
						"damage_tick": damage.TickTimestamp,
						"tick_diff":   damage.TickTimestamp - attackerTick.Tick,
						"has_los":     hasLOS,
						"tick_index":  tickIndex,
						"worker_id":   workerID,
					}).Debug("Checked LOS at tick (parallel)")

					results <- LOSResult{
						TickIndex: tickIndex,
						Tick:      attackerTick.Tick,
						HasLOS:    hasLOS,
					}
				}
			}
		}(i)
	}

	// Send jobs to workers
	go func() {
		for i := range attackerTicks {
			jobs <- i
		}
		close(jobs)
	}()

	// Close results channel when all workers are done
	go func() {
		wg.Wait()
		close(results)
	}()

	// Collect results and find the OLDEST (first) LOS occurrence
	var firstLOSResult *LOSResult
	var minTickIndex int = len(attackerTicks)

	for result := range results {
		if result.HasLOS && result.TickIndex < minTickIndex {
			minTickIndex = result.TickIndex
			firstLOSResult = &result
			// Early termination: we found the first LOS, no need to process more
			break
		}
	}

	if firstLOSResult != nil {
		aus.logger.WithFields(logrus.Fields{
			"attacker_id":     damage.AttackerSteamID,
			"victim_id":       damage.VictimSteamID,
			"visibility_tick": firstLOSResult.Tick,
			"damage_tick":     damage.TickTimestamp,
			"tick_diff":       damage.TickTimestamp - firstLOSResult.Tick,
			"tick_index":      firstLOSResult.TickIndex,
		}).Info("Found first LOS for reaction time calculation (parallel)")

		return firstLOSResult.Tick, true
	}

	return 0, false
}

// LOSResult represents the result of a line of sight check
type LOSResult struct {
	TickIndex int
	Tick      int64
	HasLOS    bool
}

// ShotAnalysisData holds the calculated analysis data for a single shot
type ShotAnalysisData struct {
	Shot         types.PlayerShootingData
	CrosshairX   float64
	CrosshairY   float64
	ReactionTime float64
	Hit          bool
	HitGroup     int
	SprayingShot bool
}

// analyzeShots performs comprehensive analysis on all shots for a player
func (aus *AimUtilityService) analyzeShots(
	shots []types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
	playerID string,
	roundNumber int,
) []ShotAnalysisData {
	var shotAnalyses []ShotAnalysisData

	aus.logger.WithFields(logrus.Fields{
		"round":         roundNumber,
		"player_id":     playerID,
		"shots_count":   len(shots),
		"damages_count": len(damages),
	}).Debug("Analyzing all shots for player")

	for _, shot := range shots {
		analysis := ShotAnalysisData{
			Shot:         shot,
			SprayingShot: shot.IsSpraying,
		}

		// Check if this shot resulted in damage
		shotHit := false
		var hitGroup int
		for _, damage := range damages {
			if damage.AttackerSteamID == shot.PlayerID &&
				abs(damage.TickTimestamp-shot.Tick) <= 10 { // Within 10 ticks
				shotHit = true
				hitGroup = damage.HitGroup
				break
			}
		}

		analysis.Hit = shotHit
		analysis.HitGroup = hitGroup

		// Calculate crosshair placement for this shot
		crosshairX, crosshairY := aus.calculateCrosshairPlacement(shot, damages, playerTickData)
		analysis.CrosshairX = crosshairX
		analysis.CrosshairY = crosshairY

		// Calculate reaction time for this shot
		reactionTime := aus.calculateReactionTime(shot, damages, playerTickData)
		analysis.ReactionTime = reactionTime

		shotAnalyses = append(shotAnalyses, analysis)

		aus.logger.WithFields(logrus.Fields{
			"round":         roundNumber,
			"player_id":     playerID,
			"shot_tick":     shot.Tick,
			"weapon":        shot.WeaponName,
			"is_spraying":   shot.IsSpraying,
			"hit":           shotHit,
			"crosshair_x":   crosshairX,
			"crosshair_y":   crosshairY,
			"reaction_time": reactionTime,
		}).Debug("Analyzed individual shot")
	}

	aus.logger.WithFields(logrus.Fields{
		"round":          roundNumber,
		"player_id":      playerID,
		"shots_analyzed": len(shotAnalyses),
	}).Info("Completed shot analysis for player")

	return shotAnalyses
}

// calculatePlayerAimStatsFromAnalysis calculates aim statistics using pre-analyzed shot data
func (aus *AimUtilityService) calculatePlayerAimStatsFromAnalysis(
	playerID string,
	roundNumber int,
	shotAnalyses []ShotAnalysisData,
) types.AimAnalysisResult {

	aus.logger.WithFields(logrus.Fields{
		"round":       roundNumber,
		"player_id":   playerID,
		"shots_count": len(shotAnalyses),
	}).Debug("Calculating player aim statistics from analysis")

	result := types.AimAnalysisResult{
		PlayerSteamID: playerID,
		RoundNumber:   roundNumber,
		ShotsFired:    len(shotAnalyses),
	}

	// Calculate shots on hit
	shotsOnHit := 0
	sprayingShotsFired := 0
	sprayingShotsHit := 0

	var crosshairPlacementsX []float64
	var crosshairPlacementsY []float64
	var reactionTimes []float64

	headHits := 0
	upperChestHits := 0
	chestHits := 0
	legsHits := 0

	for _, analysis := range shotAnalyses {
		if analysis.Hit {
			shotsOnHit++

			// Categorize hit region
			switch analysis.HitGroup {
			case types.HitGroupHead:
				headHits++
			case types.HitGroupChest:
				chestHits++
			case types.HitGroupStomach:
				upperChestHits++ // Group stomach with upper chest for simplicity
			case types.HitGroupLeftLeg, types.HitGroupRightLeg:
				legsHits++
			}
		}

		// Track spraying shots
		if analysis.SprayingShot {
			sprayingShotsFired++
			if analysis.Hit {
				sprayingShotsHit++
			}
		}

		// Collect crosshair placement and reaction time data
		crosshairPlacementsX = append(crosshairPlacementsX, analysis.CrosshairX)
		crosshairPlacementsY = append(crosshairPlacementsY, analysis.CrosshairY)
		reactionTimes = append(reactionTimes, analysis.ReactionTime)
	}

	// Calculate accuracy
	result.ShotsHit = shotsOnHit
	if result.ShotsFired > 0 {
		result.AccuracyAllShots = float64(result.ShotsHit) / float64(result.ShotsFired) * 100.0
	}

	// Calculate spray accuracy
	result.SprayingShotsFired = sprayingShotsFired
	result.SprayingShotsHit = sprayingShotsHit
	if sprayingShotsFired > 0 {
		result.SprayingAccuracy = float64(sprayingShotsHit) / float64(sprayingShotsFired) * 100.0
	}

	// Calculate average crosshair placement
	if len(crosshairPlacementsX) > 0 {
		result.AverageCrosshairPlacementX = aus.calculateAverage(crosshairPlacementsX)
		result.AverageCrosshairPlacementY = aus.calculateAverage(crosshairPlacementsY)
	}

	// Calculate headshot accuracy
	if result.ShotsHit > 0 {
		result.HeadshotAccuracy = float64(headHits) / float64(result.ShotsHit) * 100.0
	}

	// Calculate average reaction time
	if len(reactionTimes) > 0 {
		result.AverageTimeToDamage = aus.calculateAverage(reactionTimes)
	}

	// Set hit region totals
	result.HeadHitsTotal = headHits
	result.UpperChestHitsTotal = upperChestHits
	result.ChestHitsTotal = chestHits
	result.LegsHitsTotal = legsHits

	return result
}

// calculateWeaponAimStatsFromAnalysis calculates weapon-specific aim statistics using pre-analyzed shot data
func (aus *AimUtilityService) calculateWeaponAimStatsFromAnalysis(
	playerID string,
	roundNumber int,
	shotAnalyses []ShotAnalysisData,
) []types.WeaponAimAnalysisResult {

	// Group shot analyses by weapon
	weaponAnalyses := make(map[string][]ShotAnalysisData)
	for _, analysis := range shotAnalyses {
		weaponAnalyses[analysis.Shot.WeaponName] = append(weaponAnalyses[analysis.Shot.WeaponName], analysis)
	}

	var results []types.WeaponAimAnalysisResult

	for weaponName, weaponShotAnalyses := range weaponAnalyses {
		result := types.WeaponAimAnalysisResult{
			PlayerSteamID:     playerID,
			RoundNumber:       roundNumber,
			WeaponName:        weaponName,
			WeaponDisplayName: types.FormatWeaponName(weaponName),
			ShotsFired:        len(weaponShotAnalyses),
		}

		// Calculate weapon-specific statistics
		shotsOnHit := 0
		sprayingShotsFired := 0
		sprayingShotsHit := 0

		headHits := 0
		upperChestHits := 0
		chestHits := 0
		legsHits := 0

		// Arrays to store crosshair placement and reaction time data for this weapon
		var crosshairPlacementsX []float64
		var crosshairPlacementsY []float64
		var reactionTimes []float64

		for _, analysis := range weaponShotAnalyses {
			if analysis.Hit {
				shotsOnHit++

				// Categorize hit region
				switch analysis.HitGroup {
				case types.HitGroupHead:
					headHits++
				case types.HitGroupChest:
					chestHits++
				case types.HitGroupStomach:
					upperChestHits++
				case types.HitGroupLeftLeg, types.HitGroupRightLeg:
					legsHits++
				}
			}

			// Track spraying shots
			if analysis.SprayingShot {
				sprayingShotsFired++
				if analysis.Hit {
					sprayingShotsHit++
				}
			}

			// Collect crosshair placement and reaction time data
			crosshairPlacementsX = append(crosshairPlacementsX, analysis.CrosshairX)
			crosshairPlacementsY = append(crosshairPlacementsY, analysis.CrosshairY)
			reactionTimes = append(reactionTimes, analysis.ReactionTime)
		}

		// Persist shots hit for downstream consumers
		result.ShotsHit = shotsOnHit
		// Calculate accuracy
		if result.ShotsFired > 0 {
			result.AccuracyAllShots = float64(result.ShotsHit) / float64(result.ShotsFired) * 100.0
		}

		// Calculate spray accuracy
		result.SprayingShotsFired = sprayingShotsFired
		if sprayingShotsFired > 0 {
			result.SprayingShotsHit = sprayingShotsHit
			result.SprayingAccuracy = float64(sprayingShotsHit) / float64(sprayingShotsFired) * 100.0
		}

		// Calculate average crosshair placement for this weapon
		if len(crosshairPlacementsX) > 0 {
			result.AverageCrosshairPlacementX = aus.calculateAverage(crosshairPlacementsX)
			result.AverageCrosshairPlacementY = aus.calculateAverage(crosshairPlacementsY)
		}

		// Calculate average reaction time for this weapon
		if len(reactionTimes) > 0 {
			result.AverageTimeToDamage = aus.calculateAverage(reactionTimes)
		}

		// Calculate headshot accuracy
		if result.ShotsHit > 0 {
			result.HeadshotAccuracy = float64(headHits) / float64(result.ShotsHit) * 100.0
		}

		// Set hit region totals
		result.HeadHitsTotal = headHits
		result.UpperChestHitsTotal = upperChestHits
		result.ChestHitsTotal = chestHits
		result.LegsHitsTotal = legsHits

		aus.logger.WithFields(logrus.Fields{
			"round":             roundNumber,
			"player_id":         playerID,
			"weapon":            weaponName,
			"shots_fired":       result.ShotsFired,
			"shots_hit":         result.ShotsHit,
			"accuracy":          result.AccuracyAllShots,
			"spray_accuracy":    result.SprayingAccuracy,
			"crosshair_x":       result.AverageCrosshairPlacementX,
			"crosshair_y":       result.AverageCrosshairPlacementY,
			"reaction_time":     result.AverageTimeToDamage,
			"headshot_accuracy": result.HeadshotAccuracy,
		}).Info("Completed weapon-specific aim analysis")

		results = append(results, result)
	}

	return results
}

// shouldCheckLOS determines if LOS should be checked based on movement
func (aus *AimUtilityService) shouldCheckLOS(prevTick, currentTick types.PlayerTickData) bool {
	// Calculate movement distance
	movement := math.Sqrt(
		math.Pow(currentTick.PositionX-prevTick.PositionX, 2) +
			math.Pow(currentTick.PositionY-prevTick.PositionY, 2) +
			math.Pow(currentTick.PositionZ-prevTick.PositionZ, 2))

	return movement > MovementThreshold
}

// GetVector returns a Vector3 from the pool
func (p *ObjectPool) GetVector() *Vector3 {
	v := p.vectorPool.Get().(*Vector3)
	*v = Vector3{} // Reset
	return v
}

// PutVector returns a Vector3 to the pool
func (p *ObjectPool) PutVector(v *Vector3) {
	p.vectorPool.Put(v)
}

// GetTriangle returns a Triangle from the pool
func (p *ObjectPool) GetTriangle() *Triangle {
	t := p.trianglePool.Get().(*Triangle)
	*t = Triangle{} // Reset
	return t
}

// PutTriangle returns a Triangle to the pool
func (p *ObjectPool) PutTriangle(t *Triangle) {
	p.trianglePool.Put(t)
}

// GetLOSResult returns a LOSResult from the pool
func (p *ObjectPool) GetLOSResult() *LOSResult {
	r := p.resultPool.Get().(*LOSResult)
	*r = LOSResult{} // Reset
	return r
}

// PutLOSResult returns a LOSResult to the pool
func (p *ObjectPool) PutLOSResult(r *LOSResult) {
	p.resultPool.Put(r)
}

// min returns the minimum of two integers
func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}
