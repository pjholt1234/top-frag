package parser

import (
	"context"
	"fmt"
	"parser-service/internal/types"
	"sort"

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

type SmokeEffect struct {
	EntityID       int64
	StartTick      int64
	EndTick        int64
	Position       types.Position
	ThrowerSteamID string
	RoundNumber    int
	BlockingTicks  int
}

type GrenadeHandler struct {
	processor       *EventProcessor
	logger          *logrus.Logger
	movementService *MovementStateService
	grenadeThrows   map[string]*GrenadeMovementInfo
	activeSmokes    map[int64]*SmokeEffect
}

const MAX_FLASH_DURATION = 288 // 4.5 seconds

// Smoke constants
const (
	SMOKE_DURATION_TICKS      = 1152 // 18 seconds in ticks
	SMOKE_WIDTH_UNITS         = 300  // Smoke average width in units
	SMOKE_EFFECTIVE_RANGE     = 450  // Effective range to check for enemies
	SMOKE_EFFECTIVENESS_TICKS = 64   // 1 point for every 64 ticks blocked
)

func NewGrenadeHandler(processor *EventProcessor, logger *logrus.Logger) *GrenadeHandler {
	return &GrenadeHandler{
		processor:       processor,
		logger:          logger,
		movementService: NewMovementStateService(logger),
		grenadeThrows:   make(map[string]*GrenadeMovementInfo),
		activeSmokes:    make(map[int64]*SmokeEffect),
	}
}

func (gh *GrenadeHandler) HandleGrenadeProjectileDestroy(e events.GrenadeProjectileDestroy) error {
	if e.Projectile == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "projectile is nil", nil).
			WithContext("event_type", "GrenadeProjectileDestroy").
			WithContext("tick", gh.processor.currentTick)
	}

	if e.Projectile.Thrower == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "projectile thrower is nil", nil).
			WithContext("event_type", "GrenadeProjectileDestroy").
			WithContext("tick", gh.processor.currentTick)
	}

	if e.Projectile.WeaponInstance == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "projectile weapon instance is nil", nil).
			WithContext("event_type", "GrenadeProjectileDestroy").
			WithContext("tick", gh.processor.currentTick).
			WithContext("thrower", types.SteamIDToString(e.Projectile.Thrower.SteamID64))
	}

	grenadeTypeString := e.Projectile.WeaponInstance.Type.String()
	if err := gh.processor.ensurePlayerTracked(e.Projectile.Thrower); err != nil {
		return err
	}

	projectileID := fmt.Sprintf("entity_%d", e.Projectile.Entity.ID())
	movementInfo, hasMovementInfo := gh.grenadeThrows[projectileID]

	if !hasMovementInfo {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityInfo, "no movement info found for projectile", nil).
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
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "grenade entity ID is zero", nil).
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
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityInfo, "no movement info found for flash grenade", nil).
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
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "player is nil", nil).
			WithContext("event_type", "PlayerFlashed").
			WithContext("tick", gh.processor.currentTick)
	}

	playerSteamID := types.SteamIDToString(e.Player.SteamID64)
	flashDuration := e.FlashDuration().Seconds()

	if flashDuration < 0 {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "flash duration cannot be negative", nil).
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
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityInfo, "no matching flash effect found for player", nil).
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
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "projectile is nil", nil).
			WithContext("event_type", "GrenadeProjectileThrow").
			WithContext("tick", gh.processor.currentTick)
	}

	if e.Projectile.Thrower == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "projectile thrower is nil", nil).
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
	if e.GrenadeEvent.Thrower == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "smoke thrower is nil", nil).
			WithContext("event_type", "SmokeStart").
			WithContext("tick", gh.processor.currentTick)
	}

	entityID := int64(e.GrenadeEvent.GrenadeEntityID)
	throwerSteamID := types.SteamIDToString(e.GrenadeEvent.Thrower.SteamID64)

	// Create smoke effect
	smokeEffect := &SmokeEffect{
		EntityID:       entityID,
		StartTick:      gh.processor.currentTick,
		EndTick:        gh.processor.currentTick + SMOKE_DURATION_TICKS,
		Position:       types.Position{X: e.Position.X, Y: e.Position.Y, Z: e.Position.Z},
		ThrowerSteamID: throwerSteamID,
		RoundNumber:    gh.processor.matchState.CurrentRound,
		BlockingTicks:  0,
	}

	gh.activeSmokes[entityID] = smokeEffect

	// Create GrenadeEvent record for smoke grenade (similar to other grenade types)
	projectileID := fmt.Sprintf("entity_%d", entityID)
	movementInfo, hasMovementInfo := gh.grenadeThrows[projectileID]

	var playerPos types.Position
	var playerAim types.Vector
	var roundTime int
	var movementThrowType string
	var tickTimestamp int64

	if hasMovementInfo {
		playerPos = movementInfo.PlayerPos
		playerAim = movementInfo.PlayerAim
		roundTime = movementInfo.RoundTime
		movementThrowType = movementInfo.ThrowType
		tickTimestamp = movementInfo.Tick
	} else {
		// Fallback values if movement info is not available
		playerPos = types.Position{X: 0, Y: 0, Z: 0}
		playerAim = types.Vector{X: 0, Y: 0, Z: 0}
		roundTime = gh.processor.getCurrentRoundTime()
		movementThrowType = "utility"
		tickTimestamp = gh.processor.currentTick
	}

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:       gh.processor.matchState.CurrentRound,
		RoundTime:         roundTime,
		TickTimestamp:     tickTimestamp,
		ExplosionTick:     gh.processor.currentTick, // Use start tick as explosion tick for smoke
		PlayerSteamID:     throwerSteamID,
		PlayerSide:        gh.processor.getPlayerCurrentSide(throwerSteamID),
		GrenadeType:       "Smoke Grenade",
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

	gh.logger.WithFields(logrus.Fields{
		"entity_id":             entityID,
		"thrower":               throwerSteamID,
		"position":              smokeEffect.Position,
		"start_tick":            smokeEffect.StartTick,
		"end_tick":              smokeEffect.EndTick,
		"round":                 smokeEffect.RoundNumber,
		"active_smokes_count":   len(gh.activeSmokes),
		"grenade_event_created": true,
	}).Info("Smoke grenade started - tracking for blocking duration and created GrenadeEvent")

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

// THIS IS A FIX FOR A BUG IN THE GOLANG-PARSER PACKAGE - FLASH EXPLODE EVENTS GET TRIGGERED MORE THAN ONCE SOMETIMES
func (gh *GrenadeHandler) CleanupDuplicateFlashGrenades() {
	var eventsToRemove []int
	processed := make(map[string]bool) // Key: "tick_timestamp:player_steam_id"

	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]

		// Only process flashbang events from the current round
		if grenadeEvent.GrenadeType != "Flashbang" || grenadeEvent.RoundNumber != gh.processor.matchState.CurrentRound {
			continue
		}

		// Create unique key for this flash event
		key := fmt.Sprintf("%d:%s", grenadeEvent.TickTimestamp, grenadeEvent.PlayerSteamID)

		// Skip if we've already processed this combination
		if processed[key] {
			continue
		}

		// Find all duplicate events for this tick_timestamp + player combination
		var duplicates []int
		for j := range gh.processor.matchState.GrenadeEvents {
			otherEvent := &gh.processor.matchState.GrenadeEvents[j]
			if otherEvent.GrenadeType == "Flashbang" &&
				otherEvent.RoundNumber == grenadeEvent.RoundNumber &&
				otherEvent.TickTimestamp == grenadeEvent.TickTimestamp &&
				otherEvent.PlayerSteamID == grenadeEvent.PlayerSteamID {
				duplicates = append(duplicates, j)
			}
		}

		// If we found duplicates, determine which one to keep
		if len(duplicates) > 1 {
			// Find the event with the most complete flash data
			bestIndex := duplicates[0]
			bestScore := gh.calculateFlashDataCompleteness(&gh.processor.matchState.GrenadeEvents[bestIndex])

			for _, idx := range duplicates[1:] {
				score := gh.calculateFlashDataCompleteness(&gh.processor.matchState.GrenadeEvents[idx])
				if score > bestScore {
					bestScore = score
					bestIndex = idx
				}
			}

			// Mark all duplicates except the best one for removal
			for _, idx := range duplicates {
				if idx != bestIndex {
					eventsToRemove = append(eventsToRemove, idx)
				}
			}
		}

		processed[key] = true
	}

	// Sort indices in descending order to remove from end first
	sort.Sort(sort.Reverse(sort.IntSlice(eventsToRemove)))

	for _, idx := range eventsToRemove {
		// Remove the event by slicing it out
		gh.processor.matchState.GrenadeEvents = append(
			gh.processor.matchState.GrenadeEvents[:idx],
			gh.processor.matchState.GrenadeEvents[idx+1:]...,
		)
	}
}

// calculateFlashDataCompleteness returns a score based on how complete the flash data is
// Higher score means more complete data
func (gh *GrenadeHandler) calculateFlashDataCompleteness(event *types.GrenadeEvent) int {
	score := 0

	// Check for friendly flash data
	if event.FriendlyFlashDuration != nil && *event.FriendlyFlashDuration > 0 {
		score += 10
	}
	if event.FriendlyPlayersAffected > 0 {
		score += 5
	}

	// Check for enemy flash data
	if event.EnemyFlashDuration != nil && *event.EnemyFlashDuration > 0 {
		score += 10
	}
	if event.EnemyPlayersAffected > 0 {
		score += 5
	}

	// Check for effectiveness data
	if event.FlashLeadsToKill {
		score += 3
	}
	if event.FlashLeadsToDeath {
		score += 3
	}

	// Check for final position data
	if event.GrenadeFinalPosition != nil {
		score += 2
	}

	return score
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

// CalculateSmokeBlockingDuration calculates how long a smoke blocks enemy line of sight
func (gh *GrenadeHandler) CalculateSmokeBlockingDuration(smokeEffect *SmokeEffect) int {
	blockingTicks := 0
	totalTicks := smokeEffect.EndTick - smokeEffect.StartTick

	gh.logger.WithFields(logrus.Fields{
		"entity_id":   smokeEffect.EntityID,
		"start_tick":  smokeEffect.StartTick,
		"end_tick":    smokeEffect.EndTick,
		"total_ticks": totalTicks,
		"position":    smokeEffect.Position,
		"thrower":     smokeEffect.ThrowerSteamID,
	}).Info("Starting smoke blocking duration calculation")

	// Check every tick for the smoke duration
	for tick := smokeEffect.StartTick; tick < smokeEffect.EndTick; tick++ {
		// Get all enemy players within effective range
		enemyPlayers := gh.getEnemyPlayersInRange(smokeEffect.Position, SMOKE_EFFECTIVE_RANGE, smokeEffect.ThrowerSteamID)

		// Check if any enemy has line of sight blocked by smoke
		hasBlockedEnemy := false
		for _, enemyPlayer := range enemyPlayers {
			if gh.isSmokeBlockingLOS(smokeEffect.Position, enemyPlayer.Position) {
				hasBlockedEnemy = true
				break
			}
		}

		if hasBlockedEnemy {
			blockingTicks++
		}

		// Log every 100 ticks to avoid spam
		if (tick-smokeEffect.StartTick)%100 == 0 {
			gh.logger.WithFields(logrus.Fields{
				"entity_id":      smokeEffect.EntityID,
				"current_tick":   tick,
				"blocking_ticks": blockingTicks,
				"enemy_count":    len(enemyPlayers),
				"has_blocked":    hasBlockedEnemy,
			}).Debug("Smoke blocking progress")
		}
	}

	gh.logger.WithFields(logrus.Fields{
		"entity_id":        smokeEffect.EntityID,
		"total_ticks":      totalTicks,
		"blocking_ticks":   blockingTicks,
		"blocking_percent": float64(blockingTicks) / float64(totalTicks) * 100,
	}).Info("Smoke blocking duration calculation completed")

	return blockingTicks
}

// getEnemyPlayersInRange returns enemy players within the specified range
func (gh *GrenadeHandler) getEnemyPlayersInRange(smokePos types.Position, range_ float64, throwerSteamID string) []types.PlayerState {
	var enemyPlayers []types.PlayerState
	throwerTeam := gh.processor.getAssignedTeam(throwerSteamID)
	totalPlayers := len(gh.processor.playerStates)

	gh.logger.WithFields(logrus.Fields{
		"smoke_pos":     smokePos,
		"range":         range_,
		"thrower_team":  throwerTeam,
		"total_players": totalPlayers,
	}).Debug("Getting enemy players in range")

	for _, playerState := range gh.processor.playerStates {
		playerTeam := gh.processor.getAssignedTeam(playerState.SteamID)

		// Skip if same team as thrower
		if playerTeam == throwerTeam {
			continue
		}

		// Check if within range
		distance := types.CalculateDistance(smokePos, playerState.Position)
		if distance <= range_ {
			enemyPlayers = append(enemyPlayers, *playerState)
			gh.logger.WithFields(logrus.Fields{
				"player_steam_id": playerState.SteamID,
				"player_team":     playerTeam,
				"distance":        distance,
				"position":        playerState.Position,
			}).Debug("Enemy player found in range")
		}
	}

	gh.logger.WithFields(logrus.Fields{
		"enemy_count": len(enemyPlayers),
		"range":       range_,
	}).Debug("Enemy players in range found")

	return enemyPlayers
}

// isSmokeBlockingLOS checks if smoke is blocking line of sight between two positions
func (gh *GrenadeHandler) isSmokeBlockingLOS(smokePos, playerPos types.Position) bool {
	// For now, use a simple distance-based approach
	// TODO: Implement proper LOS detection with triangle mesh
	distance := types.CalculateDistance(smokePos, playerPos)
	return distance <= float64(SMOKE_WIDTH_UNITS/2)
}

// updateGrenadeEventWithSmokeBlocking updates the grenade event with smoke blocking duration
func (gh *GrenadeHandler) updateGrenadeEventWithSmokeBlocking(entityID int64, blockingDuration int) {
	gh.logger.WithFields(logrus.Fields{
		"entity_id":         entityID,
		"blocking_duration": blockingDuration,
		"total_events":      len(gh.processor.matchState.GrenadeEvents),
	}).Info("Updating grenade event with smoke blocking duration")

	// Get smoke effect to find the thrower
	smokeEffect, exists := gh.activeSmokes[entityID]
	if !exists {
		gh.logger.WithFields(logrus.Fields{
			"entity_id": entityID,
		}).Warn("No active smoke effect found for entity ID")
		return
	}

	// Find the corresponding grenade event by matching:
	// 1. Grenade type is "Smoke Grenade"
	// 2. Same thrower (PlayerSteamID)
	// 3. Same round
	// 4. ExplosionTick matches the smoke start tick
	found := false
	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]
		gh.logger.WithFields(logrus.Fields{
			"event_index":      i,
			"grenade_type":     grenadeEvent.GrenadeType,
			"explosion_tick":   grenadeEvent.ExplosionTick,
			"entity_id":        entityID,
			"player_steam_id":  grenadeEvent.PlayerSteamID,
			"round":            grenadeEvent.RoundNumber,
			"smoke_thrower":    smokeEffect.ThrowerSteamID,
			"smoke_round":      smokeEffect.RoundNumber,
			"smoke_start_tick": smokeEffect.StartTick,
		}).Debug("Checking grenade event for smoke match")

		if grenadeEvent.GrenadeType == "Smoke Grenade" &&
			grenadeEvent.PlayerSteamID == smokeEffect.ThrowerSteamID &&
			grenadeEvent.RoundNumber == smokeEffect.RoundNumber &&
			grenadeEvent.ExplosionTick == smokeEffect.StartTick {
			grenadeEvent.SmokeBlockingDuration = blockingDuration
			// Update effectiveness rating based on smoke blocking
			grenadeEvent.EffectivenessRating = grenade_rating.ScoreSmokeWithBlockingDuration(blockingDuration)
			found = true

			gh.logger.WithFields(logrus.Fields{
				"entity_id":         entityID,
				"blocking_duration": blockingDuration,
				"effectiveness":     grenadeEvent.EffectivenessRating,
				"player_steam_id":   grenadeEvent.PlayerSteamID,
				"round":             grenadeEvent.RoundNumber,
			}).Info("Successfully updated smoke grenade event")
			break
		}
	}

	if !found {
		gh.logger.WithFields(logrus.Fields{
			"entity_id":    entityID,
			"thrower":      smokeEffect.ThrowerSteamID,
			"round":        smokeEffect.RoundNumber,
			"start_tick":   smokeEffect.StartTick,
			"total_events": len(gh.processor.matchState.GrenadeEvents),
		}).Warn("No matching smoke grenade event found for entity ID")
	}
}

// ProcessSmokeBlockingDurationPostProcess calculates smoke blocking duration using post-processing approach
// This method fetches player tick data and calculates blocking duration based on actual player positions
func (gh *GrenadeHandler) ProcessSmokeBlockingDurationPostProcess(matchID string) error {
	gh.logger.WithFields(logrus.Fields{
		"match_id":           matchID,
		"smoke_events_count": len(gh.processor.matchState.GrenadeEvents),
	}).Info("Starting post-processing smoke blocking duration calculation")

	// Find all smoke grenade events
	var smokeEvents []types.GrenadeEvent
	for _, grenadeEvent := range gh.processor.matchState.GrenadeEvents {
		if grenadeEvent.GrenadeType == "Smoke Grenade" {
			smokeEvents = append(smokeEvents, grenadeEvent)
		}
	}

	if len(smokeEvents) == 0 {
		gh.logger.Info("No smoke grenade events found for post-processing")
		return nil
	}

	gh.logger.WithFields(logrus.Fields{
		"smoke_events_count": len(smokeEvents),
	}).Info("Found smoke grenade events for post-processing")

	// Process each smoke grenade event
	for i, smokeEvent := range smokeEvents {
		gh.logger.WithFields(logrus.Fields{
			"smoke_index":     i,
			"player_steam_id": smokeEvent.PlayerSteamID,
			"round_number":    smokeEvent.RoundNumber,
			"explosion_tick":  smokeEvent.ExplosionTick,
			"position":        smokeEvent.GrenadeFinalPosition,
		}).Info("Processing smoke grenade event")

		// Calculate blocking duration using post-processing approach
		blockingDuration := gh.calculateSmokeBlockingDurationPostProcess(matchID, smokeEvent)

		// Update the grenade event with the calculated blocking duration
		gh.updateGrenadeEventWithSmokeBlockingPostProcess(smokeEvent, blockingDuration)
	}

	gh.logger.Info("Completed post-processing smoke blocking duration calculation")
	return nil
}

// calculateSmokeBlockingDurationPostProcess calculates blocking duration for a single smoke event
func (gh *GrenadeHandler) calculateSmokeBlockingDurationPostProcess(matchID string, smokeEvent types.GrenadeEvent) int {
	if smokeEvent.GrenadeFinalPosition == nil {
		gh.logger.WithFields(logrus.Fields{
			"player_steam_id": smokeEvent.PlayerSteamID,
			"round_number":    smokeEvent.RoundNumber,
		}).Warn("Smoke grenade event has no final position")
		return 0
	}

	smokePos := *smokeEvent.GrenadeFinalPosition
	startTick := smokeEvent.ExplosionTick
	endTick := startTick + SMOKE_DURATION_TICKS

	gh.logger.WithFields(logrus.Fields{
		"player_steam_id": smokeEvent.PlayerSteamID,
		"round_number":    smokeEvent.RoundNumber,
		"start_tick":      startTick,
		"end_tick":        endTick,
		"smoke_position":  smokePos,
	}).Info("Calculating smoke blocking duration with post-processing")

	// Get player tick data for the smoke duration period
	playerTickData, err := gh.processor.playerTickService.GetPlayerTickDataByTickRange(
		context.Background(), matchID, startTick, endTick)
	if err != nil {
		gh.logger.WithFields(logrus.Fields{
			"match_id":   matchID,
			"start_tick": startTick,
			"end_tick":   endTick,
			"error":      err,
		}).Error("Failed to get player tick data for smoke blocking calculation")
		return 0
	}

	gh.logger.WithFields(logrus.Fields{
		"player_tick_data_count": len(playerTickData),
		"start_tick":             startTick,
		"end_tick":               endTick,
	}).Info("Retrieved player tick data for smoke blocking calculation")

	blockingTicks := 0
	smokeThrowerTeam := gh.processor.getAssignedTeam(smokeEvent.PlayerSteamID)

	// Group tick data by tick for efficient processing
	tickDataByTick := make(map[int64][]*types.PlayerTickData)
	for _, tickData := range playerTickData {
		tickDataByTick[tickData.Tick] = append(tickDataByTick[tickData.Tick], tickData)
	}

	// Check each tick for blocking
	for tick := startTick; tick < endTick; tick++ {
		tickData, exists := tickDataByTick[tick]
		if !exists {
			continue // No player data for this tick
		}

		// Check if any enemy player is within range and has line of sight blocked
		hasBlockedEnemy := false
		for _, playerData := range tickData {
			// Skip if same team as smoke thrower
			if playerData.Team == smokeThrowerTeam {
				continue
			}

			// Check if player is within effective range
			playerPos := types.Position{
				X: playerData.PositionX,
				Y: playerData.PositionY,
				Z: playerData.PositionZ,
			}

			distance := types.CalculateDistance(smokePos, playerPos)
			if distance > SMOKE_EFFECTIVE_RANGE {
				continue
			}

			// Check if smoke is blocking line of sight
			if gh.isSmokeBlockingLOS(smokePos, playerPos) {
				hasBlockedEnemy = true
				break
			}
		}

		if hasBlockedEnemy {
			blockingTicks++
		}

		// Log progress every 100 ticks
		if (tick-startTick)%100 == 0 {
			gh.logger.WithFields(logrus.Fields{
				"current_tick":    tick,
				"blocking_ticks":  blockingTicks,
				"players_checked": len(tickData),
			}).Debug("Smoke blocking progress")
		}
	}

	gh.logger.WithFields(logrus.Fields{
		"player_steam_id":     smokeEvent.PlayerSteamID,
		"round_number":        smokeEvent.RoundNumber,
		"blocking_ticks":      blockingTicks,
		"total_duration":      SMOKE_DURATION_TICKS,
		"blocking_percentage": float64(blockingTicks) / float64(SMOKE_DURATION_TICKS) * 100,
	}).Info("Completed smoke blocking duration calculation")

	return blockingTicks
}

// updateGrenadeEventWithSmokeBlockingPostProcess updates a specific grenade event with smoke blocking duration
func (gh *GrenadeHandler) updateGrenadeEventWithSmokeBlockingPostProcess(smokeEvent types.GrenadeEvent, blockingDuration int) {
	// Find the grenade event in the match state and update it
	for i := range gh.processor.matchState.GrenadeEvents {
		grenadeEvent := &gh.processor.matchState.GrenadeEvents[i]

		if grenadeEvent.GrenadeType == "Smoke Grenade" &&
			grenadeEvent.PlayerSteamID == smokeEvent.PlayerSteamID &&
			grenadeEvent.RoundNumber == smokeEvent.RoundNumber &&
			grenadeEvent.ExplosionTick == smokeEvent.ExplosionTick {

			grenadeEvent.SmokeBlockingDuration = blockingDuration
			// Update effectiveness rating based on smoke blocking
			grenadeEvent.EffectivenessRating = grenade_rating.ScoreSmokeWithBlockingDuration(blockingDuration)

			gh.logger.WithFields(logrus.Fields{
				"player_steam_id":   grenadeEvent.PlayerSteamID,
				"round_number":      grenadeEvent.RoundNumber,
				"blocking_duration": blockingDuration,
				"effectiveness":     grenadeEvent.EffectivenessRating,
			}).Info("Updated smoke grenade event with blocking duration")

			break
		}
	}
}
