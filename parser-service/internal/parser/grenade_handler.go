package parser

import (
	"fmt"
	"parser-service/internal/types"

	grenade_rating "parser-service/internal/utils"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

type GrenadeMovementInfo struct {
	Tick               int64
	RoundNumber        int
	RoundTime          int
	PlayerPos          types.Position
	PlayerAim          types.Vector
	ThrowType          string // Movement state at time of throw
	ProjectileUniqueID int64  // Unique ID of the projectile for linking
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

	// Log entity ID for verification (especially for flashbangs)
	projectileEntityID := e.Projectile.Entity.ID()
	grenadeTypeString := e.Projectile.WeaponInstance.Type.String()

	gh.logger.WithFields(logrus.Fields{
		"event_type":           "GrenadeProjectileDestroy",
		"projectile_entity_id": projectileEntityID,
		"grenade_type":         grenadeTypeString,
		"tick":                 gh.processor.currentTick,
	}).Info("GrenadeProjectileDestroy event - entity ID for verification")

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

	grenadeType := gh.getGrenadeDisplayName(grenadeTypeString)

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:       gh.processor.matchState.CurrentRound,
		RoundTime:         roundTime,
		TickTimestamp:     tickTimestamp,
		ExplosionTick:     gh.processor.currentTick, // Add explosion time for matching
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
	// Log all FlashExplode events for verification - no filtering
	gh.logger.WithFields(logrus.Fields{
		"event_type": "FlashExplode",
		"entity_id":  e.GrenadeEntityID,
		"thrower":    e.Thrower.Name,
		"tick":       gh.processor.currentTick,
	}).Info("UNIQUE_ID_TEST: FlashExplode - entity ID for verification")

	// Check if flash effect already exists for this entity ID
	var flashEffect *FlashEffect
	if existingEffect, exists := gh.processor.activeFlashEffects[e.GrenadeEntityID]; exists {
		// Update existing flash effect with new explosion data
		flashEffect = existingEffect
		flashEffect.ExplosionTick = gh.processor.currentTick
		gh.logger.WithFields(logrus.Fields{
			"entity_id": e.GrenadeEntityID,
		}).Debug("Updating existing flash effect")
	} else {
		// Create new flash effect
		flashEffect = &FlashEffect{
			EntityID:      e.GrenadeEntityID,
			ExplosionTick: gh.processor.currentTick,
			RoundNumber:   gh.processor.matchState.CurrentRound,
			ExplosionPosition: types.Position{
				X: e.Position.X,
				Y: e.Position.Y,
				Z: e.Position.Z,
			},
			AffectedPlayers: make(map[uint64]*PlayerFlashInfo),
		}
		gh.processor.activeFlashEffects[e.GrenadeEntityID] = flashEffect
		gh.logger.WithFields(logrus.Fields{
			"entity_id": e.GrenadeEntityID,
		}).Debug("Created new flash effect")
	}

	if e.Thrower != nil {
		flashEffect.ThrowerSteamID = types.SteamIDToString(e.Thrower.SteamID64)
	}

	// Try to find stored throw information for this flashbang using entity ID
	projectileID := fmt.Sprintf("entity_%d", e.GrenadeEntityID)
	movementInfo, hasMovementInfo := gh.grenadeThrows[projectileID]

	var playerPos types.Position
	var playerAim types.Vector
	var roundTime int
	var movementThrowType string

	if !hasMovementInfo {
		// No throw info available - discard this flash
		gh.logger.WithFields(logrus.Fields{
			"entity_id":      e.GrenadeEntityID,
			"projectile_id":  projectileID,
			"thrower":        flashEffect.ThrowerSteamID,
			"explosion_tick": gh.processor.currentTick,
		}).Warn("No stored throw info found for flashbang - discarding flash")
		return
	}

	// Use throw-time data for position, aim, and round time
	playerPos = movementInfo.PlayerPos
	playerAim = movementInfo.PlayerAim
	roundTime = movementInfo.RoundTime
	movementThrowType = movementInfo.ThrowType

	gh.logger.WithFields(logrus.Fields{
		"entity_id":      e.GrenadeEntityID,
		"projectile_id":  projectileID,
		"thrower":        flashEffect.ThrowerSteamID,
		"throw_tick":     movementInfo.Tick,
		"explosion_tick": gh.processor.currentTick,
		"movement":       movementInfo.ThrowType,
	}).Debug("Using stored throw info for flashbang")

	// Use throw-time round number when available, otherwise use explosion round
	roundNumber := gh.processor.matchState.CurrentRound
	if hasMovementInfo {
		roundNumber = movementInfo.RoundNumber
	}

	// Check if we already have a GrenadeEvent for this flashbang (squashing logic)
	var existingGrenadeEvent *types.GrenadeEvent
	for i := range gh.processor.matchState.GrenadeEvents {
		if gh.processor.matchState.GrenadeEvents[i].GrenadeType == "Flashbang" &&
			gh.processor.matchState.GrenadeEvents[i].PlayerSteamID == flashEffect.ThrowerSteamID &&
			gh.processor.matchState.GrenadeEvents[i].ExplosionTick == gh.processor.currentTick {
			existingGrenadeEvent = &gh.processor.matchState.GrenadeEvents[i]
			break
		}
	}

	// If flash data already exists, skip this duplicate event (first event wins)
	if existingGrenadeEvent != nil {
		return
	}

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:       roundNumber,
		RoundTime:         roundTime,
		TickTimestamp:     movementInfo.Tick,        // Use throw time for TickTimestamp
		ExplosionTick:     gh.processor.currentTick, // Add explosion time for matching
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
		return
	}

	playerSteamID := types.SteamIDToString(e.Player.SteamID64)
	flashDuration := e.FlashDuration().Seconds()

	// Find the flash effect using contextual matching (exact match only)
	var targetFlashEffect *FlashEffect
	currentRound := gh.processor.matchState.CurrentRound
	currentTick := gh.processor.currentTick
	attackerSteamID := ""

	if e.Attacker != nil {
		attackerSteamID = types.SteamIDToString(e.Attacker.SteamID64)
	}

	// Look for exact contextual match: same round, same tick, same thrower
	for _, flashEffect := range gh.processor.activeFlashEffects {
		if flashEffect.RoundNumber == currentRound &&
			flashEffect.ExplosionTick == currentTick &&
			flashEffect.ThrowerSteamID == attackerSteamID {
			targetFlashEffect = flashEffect
			break
		}
	}

	if targetFlashEffect == nil {
		return
	}

	isFriendly := false
	if targetFlashEffect.ThrowerSteamID != "" {
		throwerTeam := gh.processor.getAssignedTeam(targetFlashEffect.ThrowerSteamID)
		playerTeam := gh.processor.getAssignedTeam(playerSteamID)
		isFriendly = throwerTeam == playerTeam
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

	gh.updateGrenadeEventWithFlashData(targetFlashEffect)
}

func (gh *GrenadeHandler) HandleGrenadeProjectileThrow(e events.GrenadeProjectileThrow) {
	if e.Projectile.Thrower == nil {
		return
	}

	movementThrowType := gh.movementService.GetPlayerThrowType(e.Projectile.Thrower, gh.processor.currentTick)

	// Use entity ID for proper matching instead of memory address
	projectileID := fmt.Sprintf("entity_%d", e.Projectile.Entity.ID())
	projectileUniqueID := e.Projectile.UniqueID()
	throwInfo := &GrenadeMovementInfo{
		Tick:               gh.processor.currentTick,
		RoundNumber:        gh.processor.matchState.CurrentRound,
		RoundTime:          gh.processor.getCurrentRoundTime(),
		PlayerPos:          gh.processor.getPlayerPosition(e.Projectile.Thrower),
		PlayerAim:          gh.processor.getPlayerAim(e.Projectile.Thrower),
		ThrowType:          movementThrowType,
		ProjectileUniqueID: projectileUniqueID,
	}

	gh.grenadeThrows[projectileID] = throwInfo

	gh.logger.WithFields(logrus.Fields{
		"projectile_id":        projectileID,
		"entity_id":            e.Projectile.Entity.ID(),
		"projectile_unique_id": projectileUniqueID,
		"player":               e.Projectile.Thrower.Name,
		"grenade_type":         e.Projectile.WeaponInstance.Type.String(),
		"throw_tick":           gh.processor.currentTick,
		"movement_type":        movementThrowType,
		"round":                gh.processor.matchState.CurrentRound,
	}).Debug("Captured grenade throw movement state")

	// Test logging for unique ID equivalence - only for flashbangs
	if e.Projectile.WeaponInstance.Type.String() == "Flashbang" {
		gh.logger.WithFields(logrus.Fields{
			"event_type":           "GrenadeProjectileThrow",
			"projectile_unique_id": projectileUniqueID,
			"entity_id":            e.Projectile.Entity.ID(),
			"player":               e.Projectile.Thrower.Name,
			"throw_tick":           gh.processor.currentTick,
		}).Info("UNIQUE_ID_TEST: GrenadeProjectileThrow - unique ID for verification")
	}
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

	// Find the grenade event that matches this flash effect using exact ExplosionTick matching:
	// 1. Same grenade type (Flashbang)
	// 2. Same thrower (PlayerSteamID)
	// 3. Exact ExplosionTick match
	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]

		if grenadeEvent.GrenadeType == "Flashbang" &&
			grenadeEvent.PlayerSteamID == flashEffect.ThrowerSteamID &&
			grenadeEvent.ExplosionTick == flashEffect.ExplosionTick {
			targetGrenadeEvent = grenadeEvent
			break
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

	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType == "Flashbang" &&
			grenadeEvent.PlayerSteamID == flashEffect.ThrowerSteamID &&
			grenadeEvent.ExplosionTick == flashEffect.ExplosionTick {
			targetGrenadeEvent = grenadeEvent
			break
		}
	}

	if targetGrenadeEvent != nil {
		targetGrenadeEvent.FlashLeadsToKill = true
	}
}

func (gh *GrenadeHandler) markFlashLeadsToDeath(flashEffect *FlashEffect) {
	var targetGrenadeEvent *types.GrenadeEvent

	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType == "Flashbang" &&
			grenadeEvent.PlayerSteamID == flashEffect.ThrowerSteamID &&
			grenadeEvent.ExplosionTick == flashEffect.ExplosionTick {
			targetGrenadeEvent = grenadeEvent
			break
		}
	}

	if targetGrenadeEvent != nil {
		targetGrenadeEvent.FlashLeadsToDeath = true
	}
}

func (gh *GrenadeHandler) PopulateFlashGrenadeEffectiveness() {
	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType != "Flashbang" || gh.processor.matchState.CurrentRound != grenadeEvent.RoundNumber {
			continue
		}

		grenadeEvent.EffectivenessRating = grenade_rating.ScoreFlash(*grenadeEvent)
	}
}

func (gh *GrenadeHandler) AggregateAllGrenadeDamage() {
	gh.logger.Debug("Starting deferred grenade damage aggregation")

	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType == "Molotov" || grenadeEvent.GrenadeType == "Incendiary Grenade" || grenadeEvent.GrenadeType == "HE Grenade" {
			gh.aggregateGrenadeDamage(grenadeEvent)
		}
	}

	gh.logger.Debug("Completed deferred grenade damage aggregation")
}

func (gh *GrenadeHandler) aggregateGrenadeDamage(grenadeEvent *types.GrenadeEvent) {
	enemyDamage := 0
	teamDamage := 0

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
			if gh.processor.getAssignedTeam(damageEvent.VictimSteamID) == gh.processor.getAssignedTeam(grenadeEvent.PlayerSteamID) {
				teamDamage += damageEvent.Damage
			} else {
				enemyDamage += damageEvent.Damage
			}
		}
	}

	grenadeEvent.DamageDealt = enemyDamage
	grenadeEvent.TeamDamageDealt = teamDamage
	grenadeEvent.EffectivenessRating = grenade_rating.ScoreExplosive(*grenadeEvent)
}
