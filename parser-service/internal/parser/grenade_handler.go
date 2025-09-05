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
		gh.logger.WithFields(logrus.Fields{
			"player":       e.Projectile.Thrower.Name,
			"grenade_type": e.Projectile.WeaponInstance.Type.String(),
		}).Debug("Skipping flashbang grenade destroy - handled in HandleFlashExplode")
		return
	}

	gh.processor.ensurePlayerTracked(e.Projectile.Thrower)

	var throwInfo *types.GrenadeThrowInfo
	playerSteamID := types.SteamIDToString(e.Projectile.Thrower.SteamID64)

	// Look for the most recent throw by this player for this grenade type
	for _, info := range gh.processor.grenadeThrows {
		if info.PlayerSteamID == playerSteamID &&
			info.GrenadeType == e.Projectile.WeaponInstance.Type.String() &&
			info.RoundNumber == gh.processor.matchState.CurrentRound {
			if throwInfo == nil || info.ThrowTick > throwInfo.ThrowTick {
				throwInfo = info
			}
		}
	}
	if throwInfo == nil {
		gh.logger.WithFields(logrus.Fields{
			"player":       e.Projectile.Thrower.Name,
			"grenade_type": e.Projectile.WeaponInstance.Type.String(),
			"found_throw":  false,
		}).Warn("No stored grenade throw info found, using current position")
		return
	}

	playerPos := throwInfo.PlayerPosition
	playerAim := throwInfo.PlayerAim
	roundTime := throwInfo.RoundTime
	tickTimestamp := throwInfo.ThrowTick

	// Clean up the stored throw info
	for key, info := range gh.processor.grenadeThrows {
		if info == throwInfo {
			delete(gh.processor.grenadeThrows, key)
			break
		}
	}

	gh.logger.WithFields(logrus.Fields{
		"player":       e.Projectile.Thrower.Name,
		"grenade_type": e.Projectile.WeaponInstance.Type.String(),
		"found_throw":  true,
	}).Debug("Using stored grenade throw info")

	// Get stored throw information including movement state captured at throw time
	projectileID := fmt.Sprintf("%p", e.Projectile)
	movementInfo, hasMovementInfo := gh.grenadeThrows[projectileID]
	var movementThrowType string

	if hasMovementInfo {
		// Use movement state captured at throw time
		movementThrowType = movementInfo.ThrowType
		gh.logger.WithFields(logrus.Fields{
			"projectile_id": projectileID,
			"throw_tick":    movementInfo.Tick,
			"destroy_tick":  gh.processor.currentTick,
			"movement":      movementInfo.ThrowType,
		}).Debug("Using stored throw movement state")

		// Clean up the stored info
		delete(gh.grenadeThrows, projectileID)
	} else {
		// Fallback to current movement state if no throw info stored
		movementThrowType = gh.movementService.GetPlayerThrowType(e.Projectile.Thrower, gh.processor.currentTick)
		gh.logger.WithFields(logrus.Fields{
			"projectile_id": projectileID,
			"player":        e.Projectile.Thrower.Name,
		}).Warn("No stored throw info found, using current movement state")
	}

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:    gh.processor.matchState.CurrentRound,
		RoundTime:      roundTime,
		TickTimestamp:  tickTimestamp,
		PlayerSteamID:  playerSteamID,
		PlayerSide:     gh.processor.getPlayerCurrentSide(playerSteamID),
		GrenadeType:    e.Projectile.WeaponInstance.Type.String(),
		PlayerPosition: playerPos,
		PlayerAim:      playerAim,
		ThrowType:      movementThrowType,
		// Initialize flash effectiveness tracking fields
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

	if playerState, exists := gh.processor.playerStates[e.Projectile.Thrower.SteamID64]; exists {
		playerState.HEDamage++
	}
}

func (gh *GrenadeHandler) HandleFlashExplode(e events.FlashExplode) {
	if _, exists := gh.processor.activeFlashEffects[e.GrenadeEntityID]; exists {
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

	playerPos := types.Position{}
	playerAim := types.Vector{}

	if e.Thrower != nil {
		playerPos = gh.processor.getPlayerPosition(e.Thrower)
		playerAim = gh.processor.getPlayerAim(e.Thrower)
	}

	var movementThrowType string
	if e.Thrower != nil {
		movementThrowType = gh.movementService.GetPlayerThrowType(e.Thrower, gh.processor.currentTick)
	} else {
		movementThrowType = "Unknown"
	}

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:       gh.processor.matchState.CurrentRound,
		RoundTime:         gh.processor.getCurrentRoundTime(),
		TickTimestamp:     gh.processor.currentTick,
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

	playerSteamID := types.SteamIDToString(e.Player.SteamID64)
	flashDuration := e.FlashDuration().Seconds()

	var mostRecentFlash *FlashEffect
	var mostRecentTick int64

	for _, flashEffect := range gh.processor.activeFlashEffects {
		if gh.processor.currentTick-flashEffect.ExplosionTick <= MAX_FLASH_DURATION {
			if mostRecentFlash == nil || flashEffect.ExplosionTick > mostRecentTick {
				mostRecentFlash = flashEffect
				mostRecentTick = flashEffect.ExplosionTick
			}
		}
	}

	if mostRecentFlash == nil {
		return
	}

	isFriendly := false
	if mostRecentFlash.ThrowerSteamID != "" {
		throwerTeam := gh.processor.getAssignedTeam(mostRecentFlash.ThrowerSteamID)
		playerTeam := gh.processor.getAssignedTeam(playerSteamID)
		isFriendly = throwerTeam == playerTeam && mostRecentFlash.ThrowerSteamID != playerSteamID
	}

	playerFlashInfo := &PlayerFlashInfo{
		SteamID:       playerSteamID,
		Team:          gh.processor.getAssignedTeam(playerSteamID),
		FlashDuration: flashDuration,
		IsFriendly:    isFriendly,
	}

	mostRecentFlash.AffectedPlayers[e.Player.SteamID64] = playerFlashInfo

	if isFriendly {
		mostRecentFlash.FriendlyDuration += flashDuration
		mostRecentFlash.FriendlyCount++
	} else {
		mostRecentFlash.EnemyDuration += flashDuration
		mostRecentFlash.EnemyCount++
	}

	gh.updateGrenadeEventWithFlashData(mostRecentFlash)
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

	projectileID := fmt.Sprintf("%p", e.Projectile)
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

func (gh *GrenadeHandler) TrackGrenadeThrow(e events.WeaponFire) {
	// For now, we'll use a combination of player and tick to create a unique key
	// This is a fallback since entity ID might not be directly accessible
	key := int(e.Shooter.SteamID64) + int(gh.processor.currentTick)

	roundTime := gh.processor.getCurrentRoundTime()
	playerPos := gh.processor.getPlayerPosition(e.Shooter)
	playerAim := gh.processor.getPlayerAim(e.Shooter)

	throwInfo := &types.GrenadeThrowInfo{
		PlayerSteamID:  types.SteamIDToString(e.Shooter.SteamID64),
		PlayerPosition: playerPos,
		PlayerAim:      playerAim,
		ThrowTick:      gh.processor.currentTick,
		RoundNumber:    gh.processor.matchState.CurrentRound,
		RoundTime:      roundTime,
		GrenadeType:    e.Weapon.Type.String(),
	}

	gh.processor.grenadeThrows[key] = throwInfo

	gh.logger.WithFields(logrus.Fields{
		"key":          key,
		"player":       e.Shooter.Name,
		"grenade_type": e.Weapon.Type.String(),
		"round":        gh.processor.matchState.CurrentRound,
		"round_time":   roundTime,
	}).Debug("Tracked grenade throw")
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
