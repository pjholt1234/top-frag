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
	ThrowType          string
	ProjectileUniqueID int64
}

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

func (gh *GrenadeHandler) HandleGrenadeProjectileDestroy(e events.GrenadeProjectileDestroy) error {
	if e.Projectile == nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "projectile is nil", nil).
			WithContext("event_type", "GrenadeProjectileDestroy").
			WithContext("tick", gh.processor.currentTick)
	}

	if e.Projectile.Thrower == nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "projectile thrower is nil", nil).
			WithContext("event_type", "GrenadeProjectileDestroy").
			WithContext("tick", gh.processor.currentTick)
	}

	if e.Projectile.WeaponInstance == nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "projectile weapon instance is nil", nil).
			WithContext("event_type", "GrenadeProjectileDestroy").
			WithContext("tick", gh.processor.currentTick).
			WithContext("thrower", types.SteamIDToString(e.Projectile.Thrower.SteamID64))
	}

	grenadeTypeString := e.Projectile.WeaponInstance.Type.String()
	gh.processor.ensurePlayerTracked(e.Projectile.Thrower)

	projectileID := fmt.Sprintf("entity_%d", e.Projectile.Entity.ID())
	movementInfo, hasMovementInfo := gh.grenadeThrows[projectileID]

	if !hasMovementInfo {
		return types.NewParseError(types.ErrorTypeEventProcessing, "no movement info found for projectile", nil).
			WithContext("projectile_id", projectileID).
			WithContext("thrower", types.SteamIDToString(e.Projectile.Thrower.SteamID64)).
			WithContext("tick", gh.processor.currentTick)
	}

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
		ExplosionTick:     gh.processor.currentTick,
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

	return nil
}

func (gh *GrenadeHandler) HandleFlashExplode(e events.FlashExplode) error {
	if e.GrenadeEntityID == 0 {
		return types.NewParseError(types.ErrorTypeEventProcessing, "grenade entity ID is zero", nil).
			WithContext("event_type", "FlashExplode").
			WithContext("tick", gh.processor.currentTick)
	}

	var flashEffect *FlashEffect
	if existingEffect, exists := gh.processor.activeFlashEffects[e.GrenadeEntityID]; exists {
		if existingEffect.ExplosionTick == gh.processor.currentTick {
			return nil // Already processed this tick
		}

		flashEffect = existingEffect
		flashEffect.ExplosionTick = gh.processor.currentTick
	} else {
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
	}

	if e.Thrower != nil {
		flashEffect.ThrowerSteamID = types.SteamIDToString(e.Thrower.SteamID64)
	}

	projectileID := fmt.Sprintf("entity_%d", e.GrenadeEntityID)
	movementInfo, hasMovementInfo := gh.grenadeThrows[projectileID]

	var playerPos types.Position
	var playerAim types.Vector
	var roundTime int
	var movementThrowType string

	if !hasMovementInfo {
		return types.NewParseError(types.ErrorTypeEventProcessing, "no movement info found for flash grenade", nil).
			WithContext("projectile_id", projectileID).
			WithContext("entity_id", e.GrenadeEntityID).
			WithContext("tick", gh.processor.currentTick)
	}

	playerPos = movementInfo.PlayerPos
	playerAim = movementInfo.PlayerAim
	roundTime = movementInfo.RoundTime
	movementThrowType = movementInfo.ThrowType

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

	if existingGrenadeEvent != nil {
		return nil
	}

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:       roundNumber,
		RoundTime:         roundTime,
		TickTimestamp:     movementInfo.Tick,
		ExplosionTick:     gh.processor.currentTick,
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

	return nil
}

func (gh *GrenadeHandler) HandlePlayerFlashed(e events.PlayerFlashed) error {
	if e.Player == nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "player is nil", nil).
			WithContext("event_type", "PlayerFlashed").
			WithContext("tick", gh.processor.currentTick)
	}

	playerSteamID := types.SteamIDToString(e.Player.SteamID64)
	flashDuration := e.FlashDuration().Seconds()

	if flashDuration < 0 {
		return types.NewParseError(types.ErrorTypeEventProcessing, "flash duration cannot be negative", nil).
			WithContext("flash_duration", flashDuration).
			WithContext("player", playerSteamID).
			WithContext("tick", gh.processor.currentTick)
	}

	var targetFlashEffect *FlashEffect
	currentRound := gh.processor.matchState.CurrentRound
	currentTick := gh.processor.currentTick

	// Look for contextual match: same round, within ±1 tick
	for _, flashEffect := range gh.processor.activeFlashEffects {
		if flashEffect.RoundNumber == currentRound {
			tickDifference := currentTick - flashEffect.ExplosionTick
			if tickDifference >= -1 && tickDifference <= 1 {
				targetFlashEffect = flashEffect
				break
			}
		}
	}

	if targetFlashEffect == nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "no matching flash effect found for player", nil).
			WithContext("player", playerSteamID).
			WithContext("current_round", currentRound).
			WithContext("current_tick", currentTick).
			WithContext("tick", gh.processor.currentTick)
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

	return nil
}

func (gh *GrenadeHandler) HandleGrenadeProjectileThrow(e events.GrenadeProjectileThrow) error {
	if e.Projectile == nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "projectile is nil", nil).
			WithContext("event_type", "GrenadeProjectileThrow").
			WithContext("tick", gh.processor.currentTick)
	}

	if e.Projectile.Thrower == nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "projectile thrower is nil", nil).
			WithContext("event_type", "GrenadeProjectileThrow").
			WithContext("tick", gh.processor.currentTick)
	}

	movementThrowType := gh.movementService.GetPlayerThrowType(e.Projectile.Thrower, gh.processor.currentTick)

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

	return nil
}

func (gh *GrenadeHandler) HandleSmokeStart(e events.SmokeStart) error {
	gh.logger.Debug("Smoke grenade started")
	return nil
}

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
		return weaponType
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

	gh.logger.WithFields(logrus.Fields{
		"event_type":        "GrenadeEventCompleted",
		"entity_id":         flashEffect.EntityID,
		"thrower":           flashEffect.ThrowerSteamID,
		"round":             flashEffect.RoundNumber,
		"tick":              flashEffect.ExplosionTick,
		"friendly_duration": flashEffect.FriendlyDuration,
		"enemy_duration":    flashEffect.EnemyDuration,
		"friendly_count":    flashEffect.FriendlyCount,
		"enemy_count":       flashEffect.EnemyCount,
	}).Info("Flash grenade event completed")
}

func (gh *GrenadeHandler) CheckFlashEffectiveness(killerSteamID, victimSteamID string, killTick int64) *string {
	for _, flashEffect := range gh.processor.activeFlashEffects {
		if killTick-flashEffect.ExplosionTick <= MAX_FLASH_DURATION {
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
	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		if grenadeEvent.GrenadeType == "Molotov" || grenadeEvent.GrenadeType == "Incendiary Grenade" || grenadeEvent.GrenadeType == "HE Grenade" {
			gh.aggregateGrenadeDamage(grenadeEvent)
		}
	}
}

func (gh *GrenadeHandler) aggregateGrenadeDamage(grenadeEvent *types.GrenadeEvent) {
	enemyDamage := 0
	teamDamage := 0
	affectedPlayers := make([]types.AffectedPlayer, 0)

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

			affectedPlayers = append(affectedPlayers, types.AffectedPlayer{
				SteamID:     damageEvent.VictimSteamID,
				DamageTaken: &damageEvent.Damage,
			})
		}
	}

	grenadeEvent.DamageDealt = enemyDamage
	grenadeEvent.TeamDamageDealt = teamDamage
	grenadeEvent.AffectedPlayers = affectedPlayers
	grenadeEvent.EffectivenessRating = grenade_rating.ScoreExplosive(*grenadeEvent)
}
