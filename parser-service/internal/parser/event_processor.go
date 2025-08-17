package parser

import (
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

// Struct for managing match events

type EventProcessor struct {
	matchState   *types.MatchState
	logger       *logrus.Logger
	playerStates map[uint64]*types.PlayerState

	// Team assignment tracking
	teamAssignments    map[string]string // steamID -> "A" or "B"
	teamAWins          int               // Count of wins for team A
	teamBWins          int               // Count of wins for team B
	teamAStartedAs     string            // "CT" or "T" - which side team A started on
	teamBStartedAs     string            // "CT" or "T" - which side team B started on
	teamACurrentSide   string            // "CT" or "T" - which side team A is currently on
	teamBCurrentSide   string            // "CT" or "T" - which side team B is currently on
	assignmentComplete bool              // Whether all players have been assigned teams
	currentRound       int               // Current round number for team assignment
	currentTick        int64             // Current tick timestamp

	// Grenade tracking by entity ID
	grenadeThrows map[int]*types.GrenadeThrowInfo // entityID -> throw info

	// Flash tracking
	activeFlashEffects map[int]*FlashEffect // entityID -> flash effect info
}

// FlashEffect tracks information about an active flash effect
type FlashEffect struct {
	EntityID         int
	ThrowerSteamID   string
	ExplosionTick    int64
	AffectedPlayers  map[uint64]*PlayerFlashInfo
	FriendlyDuration float64
	EnemyDuration    float64
	FriendlyCount    int
	EnemyCount       int
}

// PlayerFlashInfo tracks individual player flash information
type PlayerFlashInfo struct {
	SteamID       string
	Team          string
	FlashDuration float64
	IsFriendly    bool
}

func NewEventProcessor(matchState *types.MatchState, logger *logrus.Logger) *EventProcessor {
	return &EventProcessor{
		matchState:   matchState,
		logger:       logger,
		playerStates: make(map[uint64]*types.PlayerState),

		// Initialize team assignment tracking
		teamAssignments:    make(map[string]string),
		teamAWins:          0,
		teamBWins:          0,
		teamAStartedAs:     "",
		teamBStartedAs:     "",
		teamACurrentSide:   "",
		teamBCurrentSide:   "",
		assignmentComplete: false,
		currentRound:       0, // Initialize currentRound
		currentTick:        0, // Initialize currentTick

		// Initialize grenade tracking
		grenadeThrows: make(map[int]*types.GrenadeThrowInfo),

		// Initialize flash tracking
		activeFlashEffects: make(map[int]*FlashEffect),
	}
}

func (ep *EventProcessor) HandleRoundStart(e events.RoundStart) {
	ep.matchState.CurrentRound++
	ep.currentRound = ep.matchState.CurrentRound // Track current round for team assignment
	ep.matchState.RoundStartTick = ep.currentTick
	ep.matchState.CurrentRoundKills = 0
	ep.matchState.CurrentRoundDeaths = 0
	ep.matchState.FirstKillPlayer = nil
	ep.matchState.FirstDeathPlayer = nil

	for _, playerState := range ep.playerStates {
		playerState.CurrentHP = 100
		playerState.CurrentArmor = 0
		playerState.IsFlashed = false
		playerState.CurrentWeapon = ""
		playerState.EquipmentValue = 0
	}

	roundEvent := types.RoundEvent{
		RoundNumber:   ep.matchState.CurrentRound,
		TickTimestamp: ep.currentTick,
		EventType:     "start",
	}
	ep.matchState.RoundEvents = append(ep.matchState.RoundEvents, roundEvent)

	ep.logger.WithFields(logrus.Fields{
		"round": ep.matchState.CurrentRound,
	}).Debug("Round started")
}

func (ep *EventProcessor) HandleRoundEnd(e events.RoundEnd) {
	ep.matchState.RoundEndTick = 0

	var winner string
	if e.Winner == common.TeamCounterTerrorists {
		winner = "CT"
	} else if e.Winner == common.TeamTerrorists {
		winner = "T"
	} else {
		winner = "Unknown"
		ep.logger.WithField("winner_team", e.Winner).Warn("Unknown winner team")
	}

	duration := 120

	roundEvent := types.RoundEvent{
		RoundNumber:   ep.matchState.CurrentRound,
		TickTimestamp: ep.currentTick,
		EventType:     "end",
		Winner:        &winner,
		Duration:      &duration,
	}
	ep.matchState.RoundEvents = append(ep.matchState.RoundEvents, roundEvent)

	// Update team wins for determining the winning team
	if winner != "Unknown" {
		ep.updateTeamWins(winner)
	}

	ep.logger.WithFields(logrus.Fields{
		"round":       ep.matchState.CurrentRound,
		"winner":      winner,
		"team_a_wins": ep.teamAWins,
		"team_b_wins": ep.teamBWins,
	}).Debug("Round ended")
}

func (ep *EventProcessor) HandlePlayerKilled(e events.Kill) {
	if e.Killer == nil || e.Victim == nil {
		return
	}

	// Ensure players are tracked
	ep.ensurePlayerTracked(e.Killer)
	ep.ensurePlayerTracked(e.Victim)

	// Check if this is the first kill of the round
	isFirstKill := ep.matchState.FirstKillPlayer == nil

	if ep.matchState.FirstKillPlayer == nil {
		steamID := types.SteamIDToString(e.Killer.SteamID64)
		ep.matchState.FirstKillPlayer = &steamID
	}
	if ep.matchState.FirstDeathPlayer == nil {
		steamID := types.SteamIDToString(e.Victim.SteamID64)
		ep.matchState.FirstDeathPlayer = &steamID
	}

	ep.matchState.CurrentRoundKills++
	ep.matchState.CurrentRoundDeaths++

	if killerState, exists := ep.playerStates[e.Killer.SteamID64]; exists {
		killerState.Kills++
		if e.IsHeadshot {
			killerState.Headshots++
		}
		if e.PenetratedObjects > 0 {
			killerState.Wallbangs++
		}
	}

	if victimState, exists := ep.playerStates[e.Victim.SteamID64]; exists {
		victimState.Deaths++
	}

	gunfightEvent := ep.createGunfightEvent(e, isFirstKill)
	ep.matchState.GunfightEvents = append(ep.matchState.GunfightEvents, gunfightEvent)

	ep.logger.WithFields(logrus.Fields{
		"killer":        e.Killer.Name,
		"victim":        e.Victim.Name,
		"weapon":        e.Weapon.String(),
		"headshot":      e.IsHeadshot,
		"is_first_kill": isFirstKill,
	}).Debug("Player killed")
}

func (ep *EventProcessor) HandlePlayerHurt(e events.PlayerHurt) {
	if e.Attacker == nil || e.Player == nil {
		return
	}

	// Ensure players are tracked
	ep.ensurePlayerTracked(e.Attacker)
	ep.ensurePlayerTracked(e.Player)

	roundTime := ep.getCurrentRoundTime()

	damageEvent := types.DamageEvent{
		RoundNumber:     ep.matchState.CurrentRound,
		RoundTime:       roundTime,
		TickTimestamp:   ep.currentTick,
		AttackerSteamID: types.SteamIDToString(e.Attacker.SteamID64),
		VictimSteamID:   types.SteamIDToString(e.Player.SteamID64),
		Damage:          e.HealthDamage + e.ArmorDamage,
		ArmorDamage:     e.ArmorDamage,
		HealthDamage:    e.HealthDamage,
		Headshot:        false,
		Weapon:          e.Weapon.String(),
	}

	ep.matchState.DamageEvents = append(ep.matchState.DamageEvents, damageEvent)

	if attackerState, exists := ep.playerStates[e.Attacker.SteamID64]; exists {
		attackerState.TotalDamage += damageEvent.Damage
	}
	if victimState, exists := ep.playerStates[e.Player.SteamID64]; exists {
		victimState.DamageTaken += damageEvent.Damage
	}
}

func (ep *EventProcessor) HandleGrenadeProjectileDestroy(e events.GrenadeProjectileDestroy) {
	if e.Projectile.Thrower == nil {
		return
	}

	// Skip flashbang grenades - they are handled in HandleFlashExplode
	if e.Projectile.WeaponInstance.Type.String() == "Flashbang" {
		ep.logger.WithFields(logrus.Fields{
			"player":       e.Projectile.Thrower.Name,
			"grenade_type": e.Projectile.WeaponInstance.Type.String(),
		}).Debug("Skipping flashbang grenade destroy - handled in HandleFlashExplode")
		return
	}

	// Ensure player is tracked
	ep.ensurePlayerTracked(e.Projectile.Thrower)

	// Try to find the stored throw information
	var throwInfo *types.GrenadeThrowInfo
	playerSteamID := types.SteamIDToString(e.Projectile.Thrower.SteamID64)

	// Look for the most recent throw by this player for this grenade type
	for _, info := range ep.grenadeThrows {
		if info.PlayerSteamID == playerSteamID &&
			info.GrenadeType == e.Projectile.WeaponInstance.Type.String() &&
			info.RoundNumber == ep.matchState.CurrentRound {
			if throwInfo == nil || info.ThrowTick > throwInfo.ThrowTick {
				throwInfo = info
			}
		}
	}
	if throwInfo == nil {
		ep.logger.WithFields(logrus.Fields{
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
	for key, info := range ep.grenadeThrows {
		if info == throwInfo {
			delete(ep.grenadeThrows, key)
			break
		}
	}

	ep.logger.WithFields(logrus.Fields{
		"player":       e.Projectile.Thrower.Name,
		"grenade_type": e.Projectile.WeaponInstance.Type.String(),
		"found_throw":  true,
	}).Debug("Using stored grenade throw info")

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:    ep.matchState.CurrentRound,
		RoundTime:      roundTime,
		TickTimestamp:  tickTimestamp,
		PlayerSteamID:  playerSteamID,
		PlayerSide:     ep.getPlayerCurrentSide(playerSteamID),
		GrenadeType:    e.Projectile.WeaponInstance.Type.String(),
		PlayerPosition: playerPos,
		PlayerAim:      playerAim,
		ThrowType:      ep.determineThrowType(e.Projectile),
	}

	position := e.Projectile.Position()
	grenadeEvent.GrenadeFinalPosition = &types.Position{
		X: position.X,
		Y: position.Y,
		Z: position.Z,
	}

	ep.matchState.GrenadeEvents = append(ep.matchState.GrenadeEvents, grenadeEvent)

	if playerState, exists := ep.playerStates[e.Projectile.Thrower.SteamID64]; exists {
		playerState.HEDamage++
	}
}

func (ep *EventProcessor) HandleFlashExplode(e events.FlashExplode) {
	ep.logger.WithFields(logrus.Fields{
		"entity_id": e.GrenadeEntityID,
		"position":  e.Position,
		"thrower_name": func() string {
			if e.Thrower != nil {
				return e.Thrower.Name
			}
			return "unknown"
		}(),
		"current_tick":           ep.currentTick,
		"existing_flash_effects": len(ep.activeFlashEffects),
	}).Info("Flash grenade exploded")

	// Check if we already have a flash effect for this entity ID
	if existingFlash, exists := ep.activeFlashEffects[e.GrenadeEntityID]; exists {
		ep.logger.WithFields(logrus.Fields{
			"entity_id":     e.GrenadeEntityID,
			"existing_tick": existingFlash.ExplosionTick,
			"current_tick":  ep.currentTick,
		}).Warn("Flash effect already exists for this entity ID, skipping duplicate")
		return
	}

	// Create a new flash effect tracker
	flashEffect := &FlashEffect{
		EntityID:        e.GrenadeEntityID,
		ExplosionTick:   ep.currentTick,
		AffectedPlayers: make(map[uint64]*PlayerFlashInfo),
	}

	// Try to find the thrower from the grenade event
	if e.Thrower != nil {
		flashEffect.ThrowerSteamID = types.SteamIDToString(e.Thrower.SteamID64)
	}

	// Store the flash effect for tracking using both entity ID and UniqueID if available
	ep.activeFlashEffects[e.GrenadeEntityID] = flashEffect

	ep.logger.WithFields(logrus.Fields{
		"entity_id":      e.GrenadeEntityID,
		"thrower":        flashEffect.ThrowerSteamID,
		"explosion_tick": ep.currentTick,
		"active_flashes": len(ep.activeFlashEffects),
	}).Info("Started tracking flash effect")

	// Create a grenade event immediately for this flash explosion
	// We'll update it later when we get PlayerFlashed events
	playerPos := types.Position{} // Default position
	playerAim := types.Vector{}   // Default aim

	if e.Thrower != nil {
		// Try to get player position and aim, but use defaults if they fail
		playerPos = ep.getPlayerPosition(e.Thrower)
		playerAim = ep.getPlayerAim(e.Thrower)
	}

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:    ep.matchState.CurrentRound,
		RoundTime:      ep.getCurrentRoundTime(),
		TickTimestamp:  ep.currentTick,
		PlayerSteamID:  flashEffect.ThrowerSteamID,
		PlayerSide:     ep.getPlayerCurrentSide(flashEffect.ThrowerSteamID),
		GrenadeType:    "Flashbang",
		PlayerPosition: playerPos,
		PlayerAim:      playerAim,
		ThrowType:      "utility", // Default throw type for flashbangs
	}

	// Set the grenade final position
	grenadeEvent.GrenadeFinalPosition = &types.Position{
		X: e.Position.X,
		Y: e.Position.Y,
		Z: e.Position.Z,
	}

	// Add the grenade event to the match state
	ep.matchState.GrenadeEvents = append(ep.matchState.GrenadeEvents, grenadeEvent)

	ep.logger.WithFields(logrus.Fields{
		"entity_id": e.GrenadeEntityID,
		"thrower":   flashEffect.ThrowerSteamID,
	}).Info("Created grenade event for flash explosion")
}

func (ep *EventProcessor) HandlePlayerFlashed(e events.PlayerFlashed) {
	if e.Player == nil {
		ep.logger.Warn("PlayerFlashed event received with nil player")
		return
	}

	playerSteamID := types.SteamIDToString(e.Player.SteamID64)
	flashDuration := e.FlashDuration().Seconds()

	ep.logger.WithFields(logrus.Fields{
		"player":         e.Player.Name,
		"steam_id":       playerSteamID,
		"flash_duration": flashDuration,
		"tick":           ep.currentTick,
		"active_flashes": len(ep.activeFlashEffects),
	}).Info("Player flashed")

	// Find the most recent flash effect that could have caused this
	// We'll look for flash effects within a reasonable time window (e.g., 1 second)
	var mostRecentFlash *FlashEffect
	var mostRecentTick int64

	for entityID, flashEffect := range ep.activeFlashEffects {
		ep.logger.WithFields(logrus.Fields{
			"entity_id":            entityID,
			"flash_thrower":        flashEffect.ThrowerSteamID,
			"explosion_tick":       flashEffect.ExplosionTick,
			"time_since_explosion": ep.currentTick - flashEffect.ExplosionTick,
		}).Debug("Checking flash effect")

		// Check if this flash effect is recent enough (within 1 second = 64 ticks)
		if ep.currentTick-flashEffect.ExplosionTick <= 64 {
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
			throwerTeam := ep.getAssignedTeam(mostRecentFlash.ThrowerSteamID)
			playerTeam := ep.getAssignedTeam(playerSteamID)
			isFriendly = throwerTeam == playerTeam

			ep.logger.WithFields(logrus.Fields{
				"thrower_team": throwerTeam,
				"player_team":  playerTeam,
				"is_friendly":  isFriendly,
			}).Debug("Team assignment for flash effect")
		}

		// Add player to the flash effect
		playerFlashInfo := &PlayerFlashInfo{
			SteamID:       playerSteamID,
			Team:          ep.getAssignedTeam(playerSteamID),
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

		ep.logger.WithFields(logrus.Fields{
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
		ep.updateGrenadeEventWithFlashData(mostRecentFlash)
	} else {
		ep.logger.WithFields(logrus.Fields{
			"player":         e.Player.Name,
			"tick":           ep.currentTick,
			"active_flashes": len(ep.activeFlashEffects),
		}).Warn("No recent flash effect found for player")
	}
}

func (ep *EventProcessor) HandleHeExplode(e events.HeExplode) {
	ep.logger.Debug("HE grenade exploded")
}

func (ep *EventProcessor) HandleSmokeStart(e events.SmokeStart) {
	ep.logger.Debug("Smoke grenade started")
}

func (ep *EventProcessor) HandleWeaponFire(e events.WeaponFire) {
	if e.Shooter == nil {
		return
	}

	// Ensure player is tracked
	ep.ensurePlayerTracked(e.Shooter)

	if playerState, exists := ep.playerStates[e.Shooter.SteamID64]; exists {
		playerState.CurrentWeapon = e.Weapon.String()
	}

	// Check if this is a grenade throw
	if ep.isGrenadeWeapon(*e.Weapon) {
		ep.trackGrenadeThrow(e)
	}
}

func (ep *EventProcessor) HandleBombPlanted(e events.BombPlanted) {
	ep.logger.Debug("Bomb planted")
}

func (ep *EventProcessor) HandleBombDefused(e events.BombDefused) {
	ep.logger.Debug("Bomb defused")
}

func (ep *EventProcessor) HandleBombExplode(e events.BombExplode) {
	ep.logger.Debug("Bomb exploded")
}

func (ep *EventProcessor) HandlePlayerConnect(e events.PlayerConnect) {
	if e.Player == nil {
		return
	}

	steamID := types.SteamIDToString(e.Player.SteamID64)
	side := ep.getTeamString(e.Player.Team)

	// Assign team based on rounds 1-12
	ep.assignTeamBasedOnRound1To12(steamID, side)
	assignedTeam := ep.getAssignedTeam(steamID)

	// Add player to match state if not already present
	if _, exists := ep.matchState.Players[steamID]; !exists {
		ep.matchState.Players[steamID] = &types.Player{
			SteamID: steamID,
			Name:    e.Player.Name,
			Team:    assignedTeam,
		}
	}

	// Add player state
	if _, exists := ep.playerStates[e.Player.SteamID64]; !exists {
		ep.playerStates[e.Player.SteamID64] = &types.PlayerState{
			SteamID: steamID,
			Name:    e.Player.Name,
			Team:    assignedTeam,
		}
	}

	ep.logger.WithFields(logrus.Fields{
		"steam_id":      steamID,
		"name":          e.Player.Name,
		"side":          side,
		"assigned_team": assignedTeam,
	}).Debug("Player connected")
}

func (ep *EventProcessor) HandlePlayerDisconnected(e events.PlayerDisconnected) {
	if e.Player == nil {
		return
	}

	steamID := types.SteamIDToString(e.Player.SteamID64)

	ep.logger.WithFields(logrus.Fields{
		"steam_id": steamID,
		"name":     e.Player.Name,
	}).Debug("Player disconnected")
}

func (ep *EventProcessor) HandlePlayerTeamChange(e events.PlayerTeamChange) {
	if e.Player == nil {
		return
	}

	steamID := types.SteamIDToString(e.Player.SteamID64)
	side := ep.getTeamString(e.Player.Team)

	// Assign team based on rounds 1-12 (if not already assigned)
	ep.assignTeamBasedOnRound1To12(steamID, side)
	assignedTeam := ep.getAssignedTeam(steamID)

	// Update player in match state
	if player, exists := ep.matchState.Players[steamID]; exists {
		player.Team = assignedTeam
	}

	// Update player state
	if playerState, exists := ep.playerStates[e.Player.SteamID64]; exists {
		playerState.Team = assignedTeam
	}

	ep.logger.WithFields(logrus.Fields{
		"steam_id":      steamID,
		"name":          e.Player.Name,
		"side":          side,
		"assigned_team": assignedTeam,
	}).Debug("Player team changed")
}

func (ep *EventProcessor) createGunfightEvent(e events.Kill, isFirstKill bool) types.GunfightEvent {
	roundTime := ep.getCurrentRoundTime()

	player1Pos := ep.getPlayerPosition(e.Killer)
	player2Pos := ep.getPlayerPosition(e.Victim)

	distance := types.CalculateDistance(player1Pos, player2Pos)

	player1SteamID := types.SteamIDToString(e.Killer.SteamID64)
	player2SteamID := types.SteamIDToString(e.Victim.SteamID64)

	gunfightEvent := types.GunfightEvent{
		RoundNumber:       ep.matchState.CurrentRound,
		RoundTime:         roundTime,
		TickTimestamp:     ep.currentTick,
		Player1SteamID:    player1SteamID,
		Player2SteamID:    player2SteamID,
		Player1Side:       ep.getPlayerCurrentSide(player1SteamID),
		Player2Side:       ep.getPlayerCurrentSide(player2SteamID),
		Player1HPStart:    ep.getPlayerHP(e.Killer),
		Player2HPStart:    ep.getPlayerHP(e.Victim),
		Player1Armor:      ep.getPlayerArmor(e.Killer),
		Player2Armor:      ep.getPlayerArmor(e.Victim),
		Player1Flashed:    ep.getPlayerFlashed(e.Killer),
		Player2Flashed:    ep.getPlayerFlashed(e.Victim),
		Player1Weapon:     ep.getPlayerWeapon(e.Killer),
		Player2Weapon:     ep.getPlayerWeapon(e.Victim),
		Player1EquipValue: ep.getPlayerEquipmentValue(e.Killer),
		Player2EquipValue: ep.getPlayerEquipmentValue(e.Victim),
		Player1Position:   player1Pos,
		Player2Position:   player2Pos,
		Distance:          distance,
		Headshot:          e.IsHeadshot,
		Wallbang:          e.PenetratedObjects > 0,
		PenetratedObjects: e.PenetratedObjects,
		VictorSteamID:     nil,
		DamageDealt:       0,
		IsFirstKill:       isFirstKill,
	}

	gunfightEvent.VictorSteamID = &player1SteamID

	gunfightEvent.DamageDealt = 100 - gunfightEvent.Player2HPStart

	return gunfightEvent
}

func (ep *EventProcessor) getPlayerPosition(player *common.Player) types.Position {
	if player == nil {
		return types.Position{}
	}

	// Try to get position, but handle potential nil pointer issues
	defer func() {
		if r := recover(); r != nil {
			ep.logger.WithFields(logrus.Fields{
				"player": player.Name,
				"error":  r,
			}).Warn("Failed to get player position, using default")
		}
	}()

	position := player.Position()
	return types.Position{
		X: position.X,
		Y: position.Y,
		Z: position.Z,
	}
}

func (ep *EventProcessor) getPlayerAim(player *common.Player) types.Vector {
	if player == nil {
		return types.Vector{}
	}

	// Try to get aim, but handle potential nil pointer issues
	defer func() {
		if r := recover(); r != nil {
			ep.logger.WithFields(logrus.Fields{
				"player": player.Name,
				"error":  r,
			}).Warn("Failed to get player aim, using default")
		}
	}()

	viewX := player.ViewDirectionX()
	viewY := player.ViewDirectionY()
	return types.Vector{
		X: float64(viewX),
		Y: float64(viewY),
		Z: 0,
	}
}

func (ep *EventProcessor) getPlayerHP(player *common.Player) int {
	if player == nil {
		return 0
	}
	return player.Health()
}

func (ep *EventProcessor) getPlayerArmor(player *common.Player) int {
	if player == nil {
		return 0
	}
	return player.Armor()
}

func (ep *EventProcessor) getPlayerFlashed(player *common.Player) bool {
	if player == nil {
		return false
	}

	flashDuration := player.FlashDuration

	return flashDuration > 0
}

func (ep *EventProcessor) getPlayerWeapon(player *common.Player) string {
	if player == nil || player.ActiveWeapon() == nil {
		return ""
	}
	return player.ActiveWeapon().String()
}

func (ep *EventProcessor) getPlayerEquipmentValue(player *common.Player) int {
	if player == nil {
		return 0
	}

	equipmentValue := 0

	// Add value of all weapons in inventory (this includes the active weapon)
	for _, weapon := range player.Inventory {
		if weapon != nil {
			weaponType := int(weapon.Type)
			equipmentValue += types.GetEquipmentValue(weaponType)
		}
	}

	// Add armor value
	if player.Armor() > 0 {
		if player.HasHelmet() {
			equipmentValue += types.GetEquipmentValue(403) // EqHelmet (Kevlar + Helmet)
		} else {
			equipmentValue += types.GetEquipmentValue(402) // EqKevlar
		}
	}

	return equipmentValue
}

func (ep *EventProcessor) getTeamString(team common.Team) string {
	switch team {
	case common.TeamCounterTerrorists:
		return "CT"
	case common.TeamTerrorists:
		return "T"
	default:
		return "Unknown"
	}
}

func (ep *EventProcessor) determineThrowType(projectile *common.GrenadeProjectile) string {
	return types.ThrowTypeUtility
}

func (ep *EventProcessor) ensurePlayerTracked(player *common.Player) {
	if player == nil {
		return
	}

	steamID := types.SteamIDToString(player.SteamID64)
	side := ep.getTeamString(player.Team)

	// Assign team based on rounds 1-12 (if not already assigned)
	ep.assignTeamBasedOnRound1To12(steamID, side)
	assignedTeam := ep.getAssignedTeam(steamID)

	// Add player to match state if not already present
	if _, exists := ep.matchState.Players[steamID]; !exists {
		ep.matchState.Players[steamID] = &types.Player{
			SteamID: steamID,
			Name:    player.Name,
			Team:    assignedTeam,
		}
	}

	// Add player state if not already present
	if _, exists := ep.playerStates[player.SteamID64]; !exists {
		ep.playerStates[player.SteamID64] = &types.PlayerState{
			SteamID: steamID,
			Name:    player.Name,
			Team:    assignedTeam,
		}
	}
}

// assignTeamBasedOnRound1To12 assigns a player to team A or B based on their side in rounds 0-12
func (ep *EventProcessor) assignTeamBasedOnRound1To12(steamID string, side string) {
	ep.logger.WithFields(logrus.Fields{
		"steam_id":            steamID,
		"side":                side,
		"current_round":       ep.currentRound,
		"assignment_complete": ep.assignmentComplete,
	}).Debug("Attempting team assignment")

	// Only assign teams during rounds 0-12 (allow round 0 for early events)
	if ep.currentRound > 12 {
		ep.logger.WithFields(logrus.Fields{
			"steam_id":      steamID,
			"current_round": ep.currentRound,
		}).Debug("Skipping team assignment - round > 12")
		return
	}

	if ep.assignmentComplete {
		ep.logger.WithFields(logrus.Fields{
			"steam_id": steamID,
		}).Debug("Skipping team assignment - already complete")
		return // Stop assigning once complete
	}

	if _, assigned := ep.teamAssignments[steamID]; assigned {
		ep.logger.WithFields(logrus.Fields{
			"steam_id": steamID,
		}).Debug("Skipping team assignment - player already assigned")
		return // Already assigned
	}

	// Assign based on side in rounds 0-12
	if side == "CT" {
		ep.teamAssignments[steamID] = "A"
		ep.logger.WithFields(logrus.Fields{
			"steam_id":      steamID,
			"assigned_team": "A",
			"side":          side,
		}).Info("Player assigned to team A (CT)")
		if ep.teamAStartedAs == "" {
			ep.teamAStartedAs = "CT"
			ep.teamBStartedAs = "T"
			ep.teamACurrentSide = "CT"
			ep.teamBCurrentSide = "T"
		}
	} else if side == "T" {
		ep.teamAssignments[steamID] = "B"
		ep.logger.WithFields(logrus.Fields{
			"steam_id":      steamID,
			"assigned_team": "B",
			"side":          side,
		}).Info("Player assigned to team B (T)")
		if ep.teamBStartedAs == "" {
			ep.teamBStartedAs = "T"
			ep.teamAStartedAs = "CT"
			ep.teamACurrentSide = "CT"
			ep.teamBCurrentSide = "T"
		}
	}

	// Check if we've assigned all players (assuming 10 players total)
	if len(ep.teamAssignments) == 10 {
		ep.assignmentComplete = true
		ep.logger.Info("Team assignment complete", logrus.Fields{
			"team_a_started_as":   ep.teamAStartedAs,
			"team_b_started_as":   ep.teamBStartedAs,
			"team_a_current_side": ep.teamACurrentSide,
			"team_b_current_side": ep.teamBCurrentSide,
			"assignments":         ep.teamAssignments,
		})
	}
}

// getAssignedTeam returns the assigned team for a player, or "A" as default
func (ep *EventProcessor) getAssignedTeam(steamID string) string {
	if team, assigned := ep.teamAssignments[steamID]; assigned {
		return team
	}
	return "A" // Default fallback
}

// getPlayerCurrentSide returns the current side (CT/T) for a player
func (ep *EventProcessor) getPlayerCurrentSide(steamID string) string {
	assignedTeam, assigned := ep.teamAssignments[steamID]

	if !assigned {
		ep.logger.WithFields(logrus.Fields{
			"steam_id":               steamID,
			"current_round":          ep.currentRound,
			"team_assignments_count": len(ep.teamAssignments),
		}).Debug("Player not assigned to team")
		return "Unknown"
	}

	if assignedTeam == "A" {
		return ep.teamACurrentSide
	} else if assignedTeam == "B" {
		return ep.teamBCurrentSide
	}

	// Default fallback if team assignment hasn't happened yet
	return "Unknown"
}

// updateTeamWins updates the win count for the appropriate team based on round winner
func (ep *EventProcessor) updateTeamWins(winner string) {
	// Check for side switches before updating wins
	ep.checkForSideSwitch()

	if winner == ep.teamACurrentSide {
		ep.teamAWins++
	} else if winner == ep.teamBCurrentSide {
		ep.teamBWins++
	}
}

// checkForSideSwitch handles halftime and overtime side switches
func (ep *EventProcessor) checkForSideSwitch() {
	// Halftime switch: after round 12, teams switch sides
	if ep.currentRound == 13 {
		ep.switchTeamSides()
		ep.logger.Info("Halftime switch occurred", logrus.Fields{
			"round":               ep.currentRound,
			"team_a_current_side": ep.teamACurrentSide,
			"team_b_current_side": ep.teamBCurrentSide,
		})
		return
	}

	// Overtime switches: every 3 rounds after round 24
	if ep.currentRound > 24 {
		// Calculate which overtime period we're in
		overtimeRounds := ep.currentRound - 24
		// Switch sides every 3 rounds (rounds 1-3, 4-6, 7-9, etc.)
		if overtimeRounds%3 == 1 {
			ep.switchTeamSides()
			ep.logger.Info("Overtime side switch occurred", logrus.Fields{
				"round":               ep.currentRound,
				"overtime_round":      overtimeRounds,
				"team_a_current_side": ep.teamACurrentSide,
				"team_b_current_side": ep.teamBCurrentSide,
			})
		}
	}
}

// switchTeamSides swaps the current sides of both teams
func (ep *EventProcessor) switchTeamSides() {
	ep.teamACurrentSide, ep.teamBCurrentSide = ep.teamBCurrentSide, ep.teamACurrentSide
}

// getWinningTeam returns "A" or "B" based on which team won more rounds
func (ep *EventProcessor) getWinningTeam() string {
	if ep.teamAWins > ep.teamBWins {
		return "A"
	} else if ep.teamBWins > ep.teamAWins {
		return "B"
	}
	return "A" // Default to A in case of tie
}

// UpdateCurrentTick updates the current tick timestamp
func (ep *EventProcessor) UpdateCurrentTick(tick int64) {
	ep.currentTick = tick
}

// getCurrentRoundTime calculates the current time in seconds since the round started
// Subtracts freeze time to get actual gameplay time
func (ep *EventProcessor) getCurrentRoundTime() int {
	if ep.matchState.RoundStartTick == 0 {
		return 0
	}

	// Calculate time since round start
	timeSinceRoundStart := int((ep.currentTick - ep.matchState.RoundStartTick) / 64)

	// Subtract freeze time to get actual gameplay time
	actualRoundTime := timeSinceRoundStart - types.CS2FreezeTime

	// Don't return negative values
	if actualRoundTime < 0 {
		return 0
	}

	return actualRoundTime
}

// isGrenadeWeapon checks if a weapon is a grenade type
func (ep *EventProcessor) isGrenadeWeapon(weapon common.Equipment) bool {
	weaponType := weapon.Type
	return weaponType == common.EqHE || weaponType == common.EqFlash || weaponType == common.EqSmoke ||
		weaponType == common.EqMolotov || weaponType == common.EqIncendiary || weaponType == common.EqDecoy
}

// trackGrenadeThrow stores information about a grenade throw for later use
func (ep *EventProcessor) trackGrenadeThrow(e events.WeaponFire) {
	// For now, we'll use a combination of player and tick to create a unique key
	// This is a fallback since entity ID might not be directly accessible
	key := int(e.Shooter.SteamID64) + int(ep.currentTick)

	roundTime := ep.getCurrentRoundTime()
	playerPos := ep.getPlayerPosition(e.Shooter)
	playerAim := ep.getPlayerAim(e.Shooter)

	throwInfo := &types.GrenadeThrowInfo{
		PlayerSteamID:  types.SteamIDToString(e.Shooter.SteamID64),
		PlayerPosition: playerPos,
		PlayerAim:      playerAim,
		ThrowTick:      ep.currentTick,
		RoundNumber:    ep.matchState.CurrentRound,
		RoundTime:      roundTime,
		GrenadeType:    e.Weapon.Type.String(),
	}

	ep.grenadeThrows[key] = throwInfo

	ep.logger.WithFields(logrus.Fields{
		"key":          key,
		"player":       e.Shooter.Name,
		"grenade_type": e.Weapon.Type.String(),
		"round":        ep.matchState.CurrentRound,
		"round_time":   roundTime,
	}).Debug("Tracked grenade throw")
}

// checkAllPlayersForFlashDuration checks all players for flash duration and updates the grenade event
func (ep *EventProcessor) checkAllPlayersForFlashDuration(throwerSteamID string, grenadeEvent *types.GrenadeEvent) {
	var friendlyDuration float64
	var enemyDuration float64
	friendlyCount := 0
	enemyCount := 0

	throwerTeam := ep.getAssignedTeam(throwerSteamID)

	// Check all tracked players for flash duration
	for steamID, playerState := range ep.playerStates {
		// Skip the thrower
		playerSteamIDStr := types.SteamIDToString(steamID)
		if playerSteamIDStr == throwerSteamID {
			continue
		}

		// Check if player is currently flashed
		if playerState.IsFlashed {
			playerTeam := ep.getAssignedTeam(playerSteamIDStr)
			isFriendly := throwerTeam == playerTeam

			// For now, we'll use a default flash duration since we don't have the actual duration
			// In a real implementation, we'd get this from the player's FlashDuration field
			flashDuration := 2.0 // Default flash duration

			if isFriendly {
				friendlyDuration += flashDuration
				friendlyCount++
			} else {
				enemyDuration += flashDuration
				enemyCount++
			}

			ep.logger.WithFields(logrus.Fields{
				"player":         playerState.Name,
				"steam_id":       playerSteamIDStr,
				"is_friendly":    isFriendly,
				"flash_duration": flashDuration,
			}).Debug("Found flashed player")
		}
	}

	// Update the grenade event with flash tracking data
	if friendlyDuration > 0 {
		grenadeEvent.FriendlyFlashDuration = &friendlyDuration
	}
	if enemyDuration > 0 {
		grenadeEvent.EnemyFlashDuration = &enemyDuration
	}
	grenadeEvent.FriendlyPlayersAffected = friendlyCount
	grenadeEvent.EnemyPlayersAffected = enemyCount

	ep.logger.WithFields(logrus.Fields{
		"friendly_duration": friendlyDuration,
		"enemy_duration":    enemyDuration,
		"friendly_count":    friendlyCount,
		"enemy_count":       enemyCount,
	}).Info("Added flash tracking data from player state check")
}

// updateGrenadeEventWithFlashData updates the corresponding grenade event with flash tracking data
func (ep *EventProcessor) updateGrenadeEventWithFlashData(flashEffect *FlashEffect) {
	// Find the grenade event that corresponds to this flash effect
	// We'll look for the most recent flashbang grenade event from the same thrower
	var targetGrenadeEvent *types.GrenadeEvent
	var mostRecentTick int64

	for i := range ep.matchState.GrenadeEvents {
		grenadeEvent := &ep.matchState.GrenadeEvents[i]
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

		ep.logger.WithFields(logrus.Fields{
			"entity_id":         flashEffect.EntityID,
			"friendly_duration": flashEffect.FriendlyDuration,
			"enemy_duration":    flashEffect.EnemyDuration,
			"friendly_players":  flashEffect.FriendlyCount,
			"enemy_players":     flashEffect.EnemyCount,
		}).Info("Updated grenade event with flash tracking data")
	} else {
		ep.logger.WithFields(logrus.Fields{
			"entity_id": flashEffect.EntityID,
			"thrower":   flashEffect.ThrowerSteamID,
		}).Warn("No grenade event found to update with flash data")
	}
}
