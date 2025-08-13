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

	// Ensure player is tracked
	ep.ensurePlayerTracked(e.Projectile.Thrower)

	roundTime := ep.getCurrentRoundTime()

	playerPos := ep.getPlayerPosition(e.Projectile.Thrower)
	playerAim := ep.getPlayerAim(e.Projectile.Thrower)

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:    ep.matchState.CurrentRound,
		RoundTime:      roundTime,
		TickTimestamp:  ep.currentTick,
		PlayerSteamID:  types.SteamIDToString(e.Projectile.Thrower.SteamID64),
		GrenadeType:    "hegrenade",
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
	ep.logger.Debug("Flash grenade exploded")
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

	gunfightEvent := types.GunfightEvent{
		RoundNumber:       ep.matchState.CurrentRound,
		RoundTime:         roundTime,
		TickTimestamp:     ep.currentTick,
		Player1SteamID:    types.SteamIDToString(e.Killer.SteamID64),
		Player2SteamID:    types.SteamIDToString(e.Victim.SteamID64),
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

	victorSteamID := types.SteamIDToString(e.Killer.SteamID64)
	gunfightEvent.VictorSteamID = &victorSteamID

	gunfightEvent.DamageDealt = 100 - gunfightEvent.Player2HPStart

	return gunfightEvent
}

func (ep *EventProcessor) getPlayerPosition(player *common.Player) types.Position {
	if player == nil {
		return types.Position{}
	}
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

// assignTeamBasedOnRound1To12 assigns a player to team A or B based on their side in rounds 1-12
func (ep *EventProcessor) assignTeamBasedOnRound1To12(steamID string, side string) {
	// Only assign teams during rounds 1-12
	if ep.currentRound > 12 {
		return
	}

	if ep.assignmentComplete {
		return // Stop assigning once complete
	}

	if _, assigned := ep.teamAssignments[steamID]; assigned {
		return // Already assigned
	}

	// Assign based on side in rounds 1-12
	if side == "CT" {
		ep.teamAssignments[steamID] = "A"
		if ep.teamAStartedAs == "" {
			ep.teamAStartedAs = "CT"
			ep.teamBStartedAs = "T"
			ep.teamACurrentSide = "CT"
			ep.teamBCurrentSide = "T"
		}
	} else if side == "T" {
		ep.teamAssignments[steamID] = "B"
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
func (ep *EventProcessor) getCurrentRoundTime() int {
	if ep.matchState.RoundStartTick == 0 {
		return 0
	}
	return int((ep.currentTick - ep.matchState.RoundStartTick) / 64) // Convert ticks to seconds
}
