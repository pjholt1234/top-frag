package parser

import (
	"fmt"
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

// GrenadeMovementInfo stores movement information about a grenade throw
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
	grenadeThrows   map[string]*GrenadeMovementInfo // Map projectile ID to throw info
}

// NewGrenadeHandler creates a new grenade handler
func NewGrenadeHandler(processor *EventProcessor, logger *logrus.Logger) *GrenadeHandler {
	return &GrenadeHandler{
		processor:       processor,
		logger:          logger,
		movementService: NewMovementStateService(logger),
		grenadeThrows:   make(map[string]*GrenadeMovementInfo),
	}
}

// HandleGrenadeProjectileDestroy handles grenade destruction events
func (gh *GrenadeHandler) HandleGrenadeProjectileDestroy(e events.GrenadeProjectileDestroy) {
	if e.Projectile.Thrower == nil {
		return
	}

	// Skip flashbang grenades - they are handled in HandleFlashExplode
	if e.Projectile.WeaponInstance.Type.String() == "Flashbang" {
		gh.logger.WithFields(logrus.Fields{
			"player":       e.Projectile.Thrower.Name,
			"grenade_type": e.Projectile.WeaponInstance.Type.String(),
		}).Debug("Skipping flashbang grenade destroy - handled in HandleFlashExplode")
		return
	}

	// Ensure player is tracked
	gh.processor.ensurePlayerTracked(e.Projectile.Thrower)

	// Try to find the stored throw information
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

// HandleFlashExplode handles flash grenade explosion events
func (gh *GrenadeHandler) HandleFlashExplode(e events.FlashExplode) {
	gh.logger.WithFields(logrus.Fields{
		"entity_id": e.GrenadeEntityID,
		"position":  e.Position,
		"thrower_name": func() string {
			if e.Thrower != nil {
				return e.Thrower.Name
			}
			return "unknown"
		}(),
		"current_tick":           gh.processor.currentTick,
		"existing_flash_effects": len(gh.processor.activeFlashEffects),
	}).Info("Flash grenade exploded")

	// Check if we already have a flash effect for this entity ID
	if existingFlash, exists := gh.processor.activeFlashEffects[e.GrenadeEntityID]; exists {
		gh.logger.WithFields(logrus.Fields{
			"entity_id":     e.GrenadeEntityID,
			"existing_tick": existingFlash.ExplosionTick,
			"current_tick":  gh.processor.currentTick,
		}).Warn("Flash effect already exists for this entity ID, skipping duplicate")
		return
	}

	// Create a new flash effect tracker
	flashEffect := &FlashEffect{
		EntityID:        e.GrenadeEntityID,
		ExplosionTick:   gh.processor.currentTick,
		AffectedPlayers: make(map[uint64]*PlayerFlashInfo),
	}

	// Try to find the thrower from the grenade event
	if e.Thrower != nil {
		flashEffect.ThrowerSteamID = types.SteamIDToString(e.Thrower.SteamID64)
	}

	// Store the flash effect for tracking using both entity ID and UniqueID if available
	gh.processor.activeFlashEffects[e.GrenadeEntityID] = flashEffect

	gh.logger.WithFields(logrus.Fields{
		"entity_id":      e.GrenadeEntityID,
		"thrower":        flashEffect.ThrowerSteamID,
		"explosion_tick": gh.processor.currentTick,
		"active_flashes": len(gh.processor.activeFlashEffects),
	}).Info("Started tracking flash effect")

	// Create a grenade event immediately for this flash explosion
	// We'll update it later when we get PlayerFlashed events
	playerPos := types.Position{} // Default position
	playerAim := types.Vector{}   // Default aim

	if e.Thrower != nil {
		// Try to get player position and aim, but use defaults if they fail
		playerPos = gh.processor.getPlayerPosition(e.Thrower)
		playerAim = gh.processor.getPlayerAim(e.Thrower)
	}

	// Capture movement state for flash grenade
	var movementThrowType string
	if e.Thrower != nil {
		movementThrowType = gh.movementService.GetPlayerThrowType(e.Thrower, gh.processor.currentTick)
	} else {
		movementThrowType = "Unknown"
	}

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:    gh.processor.matchState.CurrentRound,
		RoundTime:      gh.processor.getCurrentRoundTime(),
		TickTimestamp:  gh.processor.currentTick,
		PlayerSteamID:  flashEffect.ThrowerSteamID,
		PlayerSide:     gh.processor.getPlayerCurrentSide(flashEffect.ThrowerSteamID),
		GrenadeType:    "Flashbang",
		PlayerPosition: playerPos,
		PlayerAim:      playerAim,
		ThrowType:      movementThrowType,
	}

	// Set the grenade final position
	grenadeEvent.GrenadeFinalPosition = &types.Position{
		X: e.Position.X,
		Y: e.Position.Y,
		Z: e.Position.Z,
	}

	// Add the grenade event to the match state
	gh.processor.matchState.GrenadeEvents = append(gh.processor.matchState.GrenadeEvents, grenadeEvent)

	gh.logger.WithFields(logrus.Fields{
		"entity_id": e.GrenadeEntityID,
		"thrower":   flashEffect.ThrowerSteamID,
	}).Info("Created grenade event for flash explosion")
}

// HandlePlayerFlashed handles player flashed events
func (gh *GrenadeHandler) HandlePlayerFlashed(e events.PlayerFlashed) {
	if e.Player == nil {
		gh.logger.Warn("PlayerFlashed event received with nil player")
		return
	}

	playerSteamID := types.SteamIDToString(e.Player.SteamID64)
	flashDuration := e.FlashDuration().Seconds()

	gh.logger.WithFields(logrus.Fields{
		"player":         e.Player.Name,
		"steam_id":       playerSteamID,
		"flash_duration": flashDuration,
		"tick":           gh.processor.currentTick,
		"active_flashes": len(gh.processor.activeFlashEffects),
	}).Info("Player flashed")

	// Find the most recent flash effect that could have caused this
	// We'll look for flash effects within a reasonable time window (e.g., 1 second)
	var mostRecentFlash *FlashEffect
	var mostRecentTick int64

	for entityID, flashEffect := range gh.processor.activeFlashEffects {
		gh.logger.WithFields(logrus.Fields{
			"entity_id":            entityID,
			"flash_thrower":        flashEffect.ThrowerSteamID,
			"explosion_tick":       flashEffect.ExplosionTick,
			"time_since_explosion": gh.processor.currentTick - flashEffect.ExplosionTick,
		}).Debug("Checking flash effect")

		// Check if this flash effect is recent enough (within 1 second = 64 ticks)
		if gh.processor.currentTick-flashEffect.ExplosionTick <= 64 {
			if mostRecentFlash == nil || flashEffect.ExplosionTick > mostRecentTick {
				mostRecentFlash = flashEffect
				mostRecentTick = flashEffect.ExplosionTick
			}
		}
	}

	if mostRecentFlash != nil {
		// Determine if this player is friendly or enemy to the thrower
		isFriendly := false
		if mostRecentFlash.ThrowerSteamID != "" {
			throwerTeam := gh.processor.getAssignedTeam(mostRecentFlash.ThrowerSteamID)
			playerTeam := gh.processor.getAssignedTeam(playerSteamID)
			isFriendly = throwerTeam == playerTeam

			gh.logger.WithFields(logrus.Fields{
				"thrower_team": throwerTeam,
				"player_team":  playerTeam,
				"is_friendly":  isFriendly,
			}).Debug("Team assignment for flash effect")
		}

		// Add player to the flash effect
		playerFlashInfo := &PlayerFlashInfo{
			SteamID:       playerSteamID,
			Team:          gh.processor.getAssignedTeam(playerSteamID),
			FlashDuration: flashDuration,
			IsFriendly:    isFriendly,
		}

		mostRecentFlash.AffectedPlayers[e.Player.SteamID64] = playerFlashInfo

		// Update friendly/enemy totals
		if isFriendly {
			mostRecentFlash.FriendlyDuration += flashDuration
			mostRecentFlash.FriendlyCount++
		} else {
			mostRecentFlash.EnemyDuration += flashDuration
			mostRecentFlash.EnemyCount++
		}

		gh.logger.WithFields(logrus.Fields{
			"entity_id":      mostRecentFlash.EntityID,
			"player":         e.Player.Name,
			"is_friendly":    isFriendly,
			"flash_duration": flashDuration,
			"friendly_total": mostRecentFlash.FriendlyDuration,
			"enemy_total":    mostRecentFlash.EnemyDuration,
			"friendly_count": mostRecentFlash.FriendlyCount,
			"enemy_count":    mostRecentFlash.EnemyCount,
		}).Info("Added player to flash effect")

		// Update the corresponding grenade event with flash tracking data
		gh.updateGrenadeEventWithFlashData(mostRecentFlash)
	} else {
		gh.logger.WithFields(logrus.Fields{
			"player":         e.Player.Name,
			"tick":           gh.processor.currentTick,
			"active_flashes": len(gh.processor.activeFlashEffects),
		}).Warn("No recent flash effect found for player")
	}
}

// HandleHeExplode handles HE grenade explosion events
func (gh *GrenadeHandler) HandleHeExplode(e events.HeExplode) {
	gh.logger.Debug("HE grenade exploded")
}

// HandleGrenadeProjectileThrow handles grenade throw events to capture movement state
func (gh *GrenadeHandler) HandleGrenadeProjectileThrow(e events.GrenadeProjectileThrow) {
	if e.Projectile.Thrower == nil {
		return
	}

	// Capture movement state at the exact moment of throw
	movementThrowType := gh.movementService.GetPlayerThrowType(e.Projectile.Thrower, gh.processor.currentTick)

	// Store throw information with movement state
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

// HandleSmokeStart handles smoke grenade start events
func (gh *GrenadeHandler) HandleSmokeStart(e events.SmokeStart) {
	gh.logger.Debug("Smoke grenade started")
}

// trackGrenadeThrow stores information about a grenade throw for later use
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

// updateGrenadeEventWithFlashData updates the corresponding grenade event with flash tracking data
func (gh *GrenadeHandler) updateGrenadeEventWithFlashData(flashEffect *FlashEffect) {
	// Find the grenade event that corresponds to this flash effect
	// We'll look for the most recent flashbang grenade event from the same thrower
	var targetGrenadeEvent *types.GrenadeEvent
	var mostRecentTick int64

	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType == "Flashbang" &&
			grenadeEvent.PlayerSteamID == flashEffect.ThrowerSteamID &&
			grenadeEvent.TickTimestamp <= flashEffect.ExplosionTick &&
			grenadeEvent.TickTimestamp > mostRecentTick {
			targetGrenadeEvent = grenadeEvent
			mostRecentTick = grenadeEvent.TickTimestamp
		}
	}

	if targetGrenadeEvent != nil {
		// Update the grenade event with flash tracking data
		if flashEffect.FriendlyDuration > 0 {
			targetGrenadeEvent.FriendlyFlashDuration = &flashEffect.FriendlyDuration
		}
		if flashEffect.EnemyDuration > 0 {
			targetGrenadeEvent.EnemyFlashDuration = &flashEffect.EnemyDuration
		}
		targetGrenadeEvent.FriendlyPlayersAffected = flashEffect.FriendlyCount
		targetGrenadeEvent.EnemyPlayersAffected = flashEffect.EnemyCount

		gh.logger.WithFields(logrus.Fields{
			"entity_id":         flashEffect.EntityID,
			"friendly_duration": flashEffect.FriendlyDuration,
			"enemy_duration":    flashEffect.EnemyDuration,
			"friendly_players":  flashEffect.FriendlyCount,
			"enemy_players":     flashEffect.EnemyCount,
		}).Info("Updated grenade event with flash tracking data")
	} else {
		gh.logger.WithFields(logrus.Fields{
			"entity_id": flashEffect.EntityID,
			"thrower":   flashEffect.ThrowerSteamID,
		}).Warn("No grenade event found to update with flash data")
	}
}
