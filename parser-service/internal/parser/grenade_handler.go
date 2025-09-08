package parser

import (
	"fmt"
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

func abs(x int64) int64 {
	if x < 0 {
		return -x
	}
	return x
}

type GrenadeMovementInfo struct {
	Tick      int64
	RoundTime int
	PlayerPos types.Position
	PlayerAim types.Vector
	ThrowType string // Movement state at time of throw
}

// GrenadeHandler handles all grenade-related events
type GrenadeHandler struct {
	processor       *EventProcessor
	logger          *logrus.Logger
	movementService *MovementStateService
	grenadeThrows   map[string]*GrenadeMovementInfo
}

const MAX_FLASH_DURATION = 288 // 4.5 seconds

func NewGrenadeHandler(processor *EventProcessor, logger *logrus.Logger) *GrenadeHandler {
	return &GrenadeHandler{
		processor:       processor,
		logger:          logger,
		movementService: NewMovementStateService(logger),
		grenadeThrows:   make(map[string]*GrenadeMovementInfo),
	}
}

func (gh *GrenadeHandler) HandleGrenadeProjectileDestroy(e events.GrenadeProjectileDestroy) {
	if e.Projectile.Thrower == nil {
		return
	}

	if e.Projectile.WeaponInstance.Type.String() == "Flashbang" {
		return
	}

	gh.processor.ensurePlayerTracked(e.Projectile.Thrower)

	// Get stored throw information from HandleGrenadeProjectileThrow
	projectileID := fmt.Sprintf("entity_%d", e.Projectile.Entity.ID())
	movementInfo, hasMovementInfo := gh.grenadeThrows[projectileID]

	if !hasMovementInfo {
		return
	}

	// Use throw-time data for accurate timing and positioning
	playerPos := movementInfo.PlayerPos
	playerAim := movementInfo.PlayerAim
	roundTime := movementInfo.RoundTime
	tickTimestamp := movementInfo.Tick
	movementThrowType := movementInfo.ThrowType

	delete(gh.grenadeThrows, projectileID)

	grenadeType := gh.getGrenadeDisplayName(e.Projectile.WeaponInstance.Type.String())

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:       gh.processor.matchState.CurrentRound,
		RoundTime:         roundTime,
		TickTimestamp:     tickTimestamp,
		PlayerSteamID:     types.SteamIDToString(e.Projectile.Thrower.SteamID64),
		PlayerSide:        gh.processor.getPlayerCurrentSide(types.SteamIDToString(e.Projectile.Thrower.SteamID64)),
		GrenadeType:       grenadeType,
		PlayerPosition:    playerPos,
		PlayerAim:         playerAim,
		ThrowType:         movementThrowType,
		FlashLeadsToKill:  false,
		FlashLeadsToDeath: false,
	}

	position := e.Projectile.Position()
	grenadeEvent.GrenadeFinalPosition = &types.Position{
		X: position.X,
		Y: position.Y,
		Z: position.Z,
	}

	gh.processor.matchState.GrenadeEvents = append(gh.processor.matchState.GrenadeEvents, grenadeEvent)
}

func (gh *GrenadeHandler) HandleFlashExplode(e events.FlashExplode) {
	if _, exists := gh.processor.activeFlashEffects[e.GrenadeEntityID]; exists {
		gh.logger.WithFields(logrus.Fields{
			"entity_id": e.GrenadeEntityID,
		}).Debug("Flash effect already exists, skipping")
		return
	}

	flashEffect := &FlashEffect{
		EntityID:        e.GrenadeEntityID,
		ExplosionTick:   gh.processor.currentTick,
		AffectedPlayers: make(map[uint64]*PlayerFlashInfo),
	}

	if e.Thrower != nil {
		flashEffect.ThrowerSteamID = types.SteamIDToString(e.Thrower.SteamID64)
	}

	gh.processor.activeFlashEffects[e.GrenadeEntityID] = flashEffect

	gh.logger.WithFields(logrus.Fields{
		"entity_id":      e.GrenadeEntityID,
		"thrower":        flashEffect.ThrowerSteamID,
		"explosion_tick": gh.processor.currentTick,
		"position":       fmt.Sprintf("(%.2f, %.2f, %.2f)", e.Position.X, e.Position.Y, e.Position.Z),
	}).Info("Flash explosion detected")

	// Try to find stored throw information for this flashbang using entity ID
	projectileID := fmt.Sprintf("entity_%d", e.GrenadeEntityID)
	movementInfo, hasMovementInfo := gh.grenadeThrows[projectileID]

	var playerPos types.Position
	var playerAim types.Vector
	var roundTime int
	var tickTimestamp int64
	var movementThrowType string

	if hasMovementInfo {
		// Use throw-time data for accurate timing and positioning
		playerPos = movementInfo.PlayerPos
		playerAim = movementInfo.PlayerAim
		roundTime = movementInfo.RoundTime
		tickTimestamp = movementInfo.Tick
		movementThrowType = movementInfo.ThrowType

		// Clean up the stored info
		delete(gh.grenadeThrows, projectileID)

		gh.logger.WithFields(logrus.Fields{
			"entity_id":      e.GrenadeEntityID,
			"projectile_id":  projectileID,
			"thrower":        flashEffect.ThrowerSteamID,
			"throw_tick":     movementInfo.Tick,
			"explosion_tick": gh.processor.currentTick,
			"movement":       movementInfo.ThrowType,
		}).Debug("Using stored throw info for flashbang")
	} else {
		// Fallback to current position/aim if no throw info found
		if e.Thrower != nil {
			playerPos = gh.processor.getPlayerPosition(e.Thrower)
			playerAim = gh.processor.getPlayerAim(e.Thrower)
			movementThrowType = gh.movementService.GetPlayerThrowType(e.Thrower, gh.processor.currentTick)
		} else {
			movementThrowType = "Unknown"
		}
		roundTime = gh.processor.getCurrentRoundTime()
		tickTimestamp = gh.processor.currentTick

		gh.logger.WithFields(logrus.Fields{
			"entity_id":      e.GrenadeEntityID,
			"projectile_id":  projectileID,
			"thrower":        flashEffect.ThrowerSteamID,
			"explosion_tick": gh.processor.currentTick,
		}).Warn("No stored throw info found for flashbang, using current position")
	}

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:       gh.processor.matchState.CurrentRound,
		RoundTime:         roundTime,
		TickTimestamp:     tickTimestamp,
		PlayerSteamID:     flashEffect.ThrowerSteamID,
		PlayerSide:        gh.processor.getPlayerCurrentSide(flashEffect.ThrowerSteamID),
		GrenadeType:       "Flashbang",
		PlayerPosition:    playerPos,
		PlayerAim:         playerAim,
		ThrowType:         movementThrowType,
		FlashLeadsToKill:  false,
		FlashLeadsToDeath: false,
	}

	grenadeEvent.GrenadeFinalPosition = &types.Position{
		X: e.Position.X,
		Y: e.Position.Y,
		Z: e.Position.Z,
	}

	gh.processor.matchState.GrenadeEvents = append(gh.processor.matchState.GrenadeEvents, grenadeEvent)
}

func (gh *GrenadeHandler) HandlePlayerFlashed(e events.PlayerFlashed) {
	if e.Player == nil {
		gh.logger.Warn("PlayerFlashed event received with nil player")
		return
	}

	if e.Projectile == nil {
		gh.logger.WithFields(logrus.Fields{
			"player": e.Player.Name,
		}).Warn("PlayerFlashed event received with nil projectile")
		return
	}

	playerSteamID := types.SteamIDToString(e.Player.SteamID64)
	flashDuration := e.FlashDuration().Seconds()

	gh.logger.WithFields(logrus.Fields{
		"player":          e.Player.Name,
		"player_steam_id": playerSteamID,
		"flash_duration":  flashDuration,
		"current_tick":    gh.processor.currentTick,
	}).Info("Player flashed event received")

	// Find the specific flash effect using the projectile's entity ID
	// The projectile should have the same entity ID as the flash explosion
	projectileEntityID := e.Projectile.Entity.ID()
	targetFlashEffect, exists := gh.processor.activeFlashEffects[projectileEntityID]

	gh.logger.WithFields(logrus.Fields{
		"projectile_entity_id": projectileEntityID,
		"direct_match_found":   exists,
		"active_flash_effects": len(gh.processor.activeFlashEffects),
	}).Debug("Looking for flash effect match")

	if !exists {
		// Fallback: try to find by thrower and timing if direct entity ID match fails
		gh.logger.Debug("Direct entity ID match failed, trying fallback matching")
		for entityID, flashEffect := range gh.processor.activeFlashEffects {
			timeDiff := gh.processor.currentTick - flashEffect.ExplosionTick
			gh.logger.WithFields(logrus.Fields{
				"flash_entity_id":      entityID,
				"flash_thrower":        flashEffect.ThrowerSteamID,
				"flash_explosion_tick": flashEffect.ExplosionTick,
				"time_diff":            timeDiff,
			}).Debug("Checking flash effect for fallback match")

			// Look for flash effects within a reasonable time window (5 seconds = 320 ticks)
			if timeDiff >= 0 && timeDiff <= 320 {
				// Check if this flash effect matches the projectile's thrower
				if e.Projectile.Thrower != nil && flashEffect.ThrowerSteamID == types.SteamIDToString(e.Projectile.Thrower.SteamID64) {
					// Use the most recent matching flash effect
					if targetFlashEffect == nil || flashEffect.ExplosionTick > targetFlashEffect.ExplosionTick {
						targetFlashEffect = flashEffect
						gh.logger.WithFields(logrus.Fields{
							"matched_flash_entity_id": entityID,
							"time_diff":               timeDiff,
						}).Debug("Found fallback flash effect match")
					}
				}
			}
		}
	}

	if targetFlashEffect == nil {
		gh.logger.WithFields(logrus.Fields{
			"player":               e.Player.Name,
			"current_tick":         gh.processor.currentTick,
			"flash_duration":       flashDuration,
			"projectile_entity_id": projectileEntityID,
		}).Debug("No matching flash effect found for PlayerFlashed event")
		return
	}

	// Determine if this is a friendly or enemy flash
	isFriendly := false
	if targetFlashEffect.ThrowerSteamID != "" {
		throwerTeam := gh.processor.getAssignedTeam(targetFlashEffect.ThrowerSteamID)
		playerTeam := gh.processor.getAssignedTeam(playerSteamID)
		isFriendly = throwerTeam == playerTeam && targetFlashEffect.ThrowerSteamID != playerSteamID
	}

	playerFlashInfo := &PlayerFlashInfo{
		SteamID:       playerSteamID,
		Team:          gh.processor.getAssignedTeam(playerSteamID),
		FlashDuration: flashDuration,
		IsFriendly:    isFriendly,
	}

	targetFlashEffect.AffectedPlayers[e.Player.SteamID64] = playerFlashInfo

	if isFriendly {
		targetFlashEffect.FriendlyDuration += flashDuration
		targetFlashEffect.FriendlyCount++
	} else {
		targetFlashEffect.EnemyDuration += flashDuration
		targetFlashEffect.EnemyCount++
	}

	gh.logger.WithFields(logrus.Fields{
		"player":            e.Player.Name,
		"thrower":           targetFlashEffect.ThrowerSteamID,
		"flash_duration":    flashDuration,
		"is_friendly":       isFriendly,
		"friendly_count":    targetFlashEffect.FriendlyCount,
		"enemy_count":       targetFlashEffect.EnemyCount,
		"friendly_duration": targetFlashEffect.FriendlyDuration,
		"enemy_duration":    targetFlashEffect.EnemyDuration,
	}).Debug("Updated flash effect with player flash data")

	gh.updateGrenadeEventWithFlashData(targetFlashEffect)
}

func (gh *GrenadeHandler) HandleHeExplode(e events.HeExplode) {
	// For now, HE damage is better tracked through the damage events system
	// This event can be used for other HE-specific tracking in the future
	gh.logger.WithFields(logrus.Fields{
		"round": gh.processor.matchState.CurrentRound,
		"tick":  gh.processor.currentTick,
	}).Debug("HE grenade exploded")
}

func (gh *GrenadeHandler) HandleGrenadeProjectileThrow(e events.GrenadeProjectileThrow) {
	if e.Projectile.Thrower == nil {
		return
	}

	movementThrowType := gh.movementService.GetPlayerThrowType(e.Projectile.Thrower, gh.processor.currentTick)

	// Use entity ID for proper matching instead of memory address
	projectileID := fmt.Sprintf("entity_%d", e.Projectile.Entity.ID())
	throwInfo := &GrenadeMovementInfo{
		Tick:      gh.processor.currentTick,
		RoundTime: gh.processor.getCurrentRoundTime(),
		PlayerPos: gh.processor.getPlayerPosition(e.Projectile.Thrower),
		PlayerAim: gh.processor.getPlayerAim(e.Projectile.Thrower),
		ThrowType: movementThrowType,
	}

	gh.grenadeThrows[projectileID] = throwInfo

	gh.logger.WithFields(logrus.Fields{
		"projectile_id": projectileID,
		"entity_id":     e.Projectile.Entity.ID(),
		"player":        e.Projectile.Thrower.Name,
		"grenade_type":  e.Projectile.WeaponInstance.Type.String(),
		"throw_tick":    gh.processor.currentTick,
		"movement_type": movementThrowType,
		"round":         gh.processor.matchState.CurrentRound,
	}).Debug("Captured grenade throw movement state")
}

func (gh *GrenadeHandler) HandleSmokeStart(e events.SmokeStart) {
	gh.logger.Debug("Smoke grenade started")
}

// getGrenadeDisplayName converts internal weapon type strings to display names
func (gh *GrenadeHandler) getGrenadeDisplayName(weaponType string) string {
	switch weaponType {
	case "hegrenade":
		return "HE Grenade"
	case "flashbang":
		return "Flashbang"
	case "smokegrenade":
		return "Smoke Grenade"
	case "molotov":
		return "Molotov"
	case "incendiary":
		return "Incendiary"
	case "decoy":
		return "Decoy"
	default:
		return weaponType // Return as-is if no mapping found
	}
}

func (gh *GrenadeHandler) updateGrenadeEventWithFlashData(flashEffect *FlashEffect) {
	var targetGrenadeEvent *types.GrenadeEvent
	var closestTick int64 = -1

	// Find the grenade event that matches this flash effect by looking for:
	// 1. Same grenade type (Flashbang)
	// 2. Same thrower (PlayerSteamID)
	// 3. Timestamp within ±10 ticks of the explosion (accounts for minor timing differences)
	// If multiple matches exist, pick the one with the timestamp closest to the explosion tick
	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType == "Flashbang" &&
			grenadeEvent.PlayerSteamID == flashEffect.ThrowerSteamID &&
			grenadeEvent.TickTimestamp <= flashEffect.ExplosionTick+10 &&
			grenadeEvent.TickTimestamp >= flashEffect.ExplosionTick-10 {
			if closestTick == -1 || abs(grenadeEvent.TickTimestamp-flashEffect.ExplosionTick) < abs(closestTick-flashEffect.ExplosionTick) {
				targetGrenadeEvent = grenadeEvent
				closestTick = grenadeEvent.TickTimestamp
			}
		}
	}

	if targetGrenadeEvent == nil {
		gh.logger.WithFields(logrus.Fields{
			"entity_id": flashEffect.EntityID,
			"thrower":   flashEffect.ThrowerSteamID,
		}).Warn("No grenade event found to update with flash data")
		return
	}

	if flashEffect.FriendlyDuration > 0 {
		targetGrenadeEvent.FriendlyFlashDuration = &flashEffect.FriendlyDuration
	}
	if flashEffect.EnemyDuration > 0 {
		targetGrenadeEvent.EnemyFlashDuration = &flashEffect.EnemyDuration
	}
	targetGrenadeEvent.FriendlyPlayersAffected = flashEffect.FriendlyCount
	targetGrenadeEvent.EnemyPlayersAffected = flashEffect.EnemyCount
}

func (gh *GrenadeHandler) CheckFlashEffectiveness(killerSteamID, victimSteamID string, killTick int64) *string {
	// Check if the victim was flashed at the time of death
	// We'll look for active flash effects that could have affected the victim
	for _, flashEffect := range gh.processor.activeFlashEffects {
		if killTick-flashEffect.ExplosionTick <= MAX_FLASH_DURATION {
			// Check if the victim was affected by this flash
			if victimInfo, exists := flashEffect.AffectedPlayers[types.StringToSteamID(victimSteamID)]; exists {
				killerTeam := gh.processor.getAssignedTeam(killerSteamID)
				flashThrowerTeam := gh.processor.getAssignedTeam(flashEffect.ThrowerSteamID)
				isKillerAndThrowerSameTeam := killerTeam == flashThrowerTeam

				if !isKillerAndThrowerSameTeam && victimInfo.IsFriendly {
					gh.markFlashLeadsToDeath(flashEffect)
				}

				if isKillerAndThrowerSameTeam && !victimInfo.IsFriendly {
					gh.markFlashLeadsToKill(flashEffect)
				}

				return &flashEffect.ThrowerSteamID
			}
		}
	}

	return nil
}

func (gh *GrenadeHandler) markFlashLeadsToKill(flashEffect *FlashEffect) {
	var targetGrenadeEvent *types.GrenadeEvent
	var closestTick int64 = -1

	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType == "Flashbang" &&
			grenadeEvent.PlayerSteamID == flashEffect.ThrowerSteamID &&
			grenadeEvent.TickTimestamp <= flashEffect.ExplosionTick+10 &&
			grenadeEvent.TickTimestamp >= flashEffect.ExplosionTick-10 {
			if closestTick == -1 || abs(grenadeEvent.TickTimestamp-flashEffect.ExplosionTick) < abs(closestTick-flashEffect.ExplosionTick) {
				targetGrenadeEvent = grenadeEvent
				closestTick = grenadeEvent.TickTimestamp
			}
		}
	}

	if targetGrenadeEvent != nil {
		targetGrenadeEvent.FlashLeadsToKill = true
	}
}

func (gh *GrenadeHandler) markFlashLeadsToDeath(flashEffect *FlashEffect) {
	var targetGrenadeEvent *types.GrenadeEvent
	var closestTick int64 = -1

	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType == "Flashbang" &&
			grenadeEvent.PlayerSteamID == flashEffect.ThrowerSteamID &&
			grenadeEvent.TickTimestamp <= flashEffect.ExplosionTick+10 &&
			grenadeEvent.TickTimestamp >= flashEffect.ExplosionTick-10 {
			if closestTick == -1 || abs(grenadeEvent.TickTimestamp-flashEffect.ExplosionTick) < abs(closestTick-flashEffect.ExplosionTick) {
				targetGrenadeEvent = grenadeEvent
				closestTick = grenadeEvent.TickTimestamp
			}
		}
	}

	if targetGrenadeEvent != nil {
		targetGrenadeEvent.FlashLeadsToDeath = true
	}
}

func (gh *GrenadeHandler) AggregateAllGrenadeDamage() {
	gh.logger.Debug("Starting deferred grenade damage aggregation")

	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		gh.aggregateGrenadeDamage(grenadeEvent)
	}

	gh.logger.Debug("Completed deferred grenade damage aggregation")
}

func (gh *GrenadeHandler) aggregateGrenadeDamage(grenadeEvent *types.GrenadeEvent) {
	totalDamage := 0
	var affectedPlayers []types.AffectedPlayer

	for _, damageEvent := range gh.processor.matchState.DamageEvents {
		if damageEvent.RoundNumber != grenadeEvent.RoundNumber || damageEvent.AttackerSteamID != grenadeEvent.PlayerSteamID {
			continue
		}

		if damageEvent.Weapon != grenadeEvent.GrenadeType {
			continue
		}

		timeWindow := int64(types.GrenadeDamageWindow * 64)

		if grenadeEvent.GrenadeType == "Molotov" {
			timeWindow = int64(types.MolotovDuration * 64)
		}

		if grenadeEvent.GrenadeType == "Incendiary Grenade" {
			timeWindow = int64(types.IncendiaryDuration * 64)
		}

		if damageEvent.TickTimestamp >= grenadeEvent.TickTimestamp && damageEvent.TickTimestamp <= grenadeEvent.TickTimestamp+timeWindow {
			totalDamage += damageEvent.Damage

			affectedPlayers = append(affectedPlayers, types.AffectedPlayer{
				SteamID:     damageEvent.VictimSteamID,
				DamageTaken: &damageEvent.Damage,
			})
		}
	}

	grenadeEvent.DamageDealt = totalDamage
	grenadeEvent.AffectedPlayers = affectedPlayers

	gh.logger.WithFields(logrus.Fields{
		"grenade_type":     grenadeEvent.GrenadeType,
		"total_damage":     totalDamage,
		"affected_players": len(affectedPlayers),
	}).Debug("Completed grenade damage aggregation")
}
