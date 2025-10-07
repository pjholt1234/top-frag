package parser

import (
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

// MatchHandler handles all match and round-related events
type MatchHandler struct {
	processor *EventProcessor
	logger    *logrus.Logger
}

// NewMatchHandler creates a new match handler
func NewMatchHandler(processor *EventProcessor, logger *logrus.Logger) *MatchHandler {
	return &MatchHandler{
		processor: processor,
		logger:    logger,
	}
}

// HandleRoundStart handles round start events
func (mh *MatchHandler) HandleRoundStart(e events.RoundStart) error {
	if mh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event", "RoundStart")
	}

	if mh.processor.matchState == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "match state is nil", nil).
			WithContext("event", "RoundStart")
	}

	if mh.processor.grenadeHandler == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "grenade handler is nil", nil).
			WithContext("event", "RoundStart")
	}

	if mh.processor.grenadeHandler.movementService == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "movement service is nil", nil).
			WithContext("event", "RoundStart")
	}

	mh.processor.matchState.CurrentRound++
	mh.processor.currentRound = mh.processor.matchState.CurrentRound // Track current round for team assignment
	mh.processor.matchState.RoundStartTick = mh.processor.currentTick
	mh.processor.matchState.CurrentRoundKills = 0
	mh.processor.matchState.CurrentRoundDeaths = 0

	// Update movement service with new round number
	mh.processor.grenadeHandler.movementService.SetCurrentRound(mh.processor.matchState.CurrentRound)
	mh.processor.matchState.FirstKillPlayer = nil
	mh.processor.matchState.FirstDeathPlayer = nil

	// Clear position history at start of each round
	mh.processor.grenadeHandler.movementService.ClearPositionHistory()

	for _, playerState := range mh.processor.playerStates {
		playerState.CurrentHP = 100
		playerState.CurrentArmor = 0
		playerState.IsFlashed = false
		playerState.CurrentWeapon = ""
		playerState.EquipmentValue = 0
	}

	roundEvent := types.RoundEvent{
		RoundNumber:   mh.processor.matchState.CurrentRound,
		TickTimestamp: mh.processor.currentTick,
		EventType:     "start",
	}
	mh.processor.matchState.RoundEvents = append(mh.processor.matchState.RoundEvents, roundEvent)

	return nil
}

// HandleRoundEnd handles round end events
func (mh *MatchHandler) HandleRoundEnd(e events.RoundEnd) error {
	if mh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event", "RoundEnd")
	}

	if mh.processor.matchState == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "match state is nil", nil).
			WithContext("event", "RoundEnd")
	}

	mh.processor.matchState.RoundEndTick = 0

	var winner string
	if e.Winner == common.TeamCounterTerrorists {
		winner = "CT"
	} else if e.Winner == common.TeamTerrorists {
		winner = "T"
	} else {
		winner = "Unknown"
		mh.logger.WithField("winner_team", e.Winner).Warn("Unknown winner team")
	}

	duration := 120

	roundEvent := types.RoundEvent{
		RoundNumber:   mh.processor.matchState.CurrentRound,
		TickTimestamp: mh.processor.currentTick,
		EventType:     "end",
		Winner:        &winner,
		Duration:      &duration,
	}
	mh.processor.matchState.RoundEvents = append(mh.processor.matchState.RoundEvents, roundEvent)

	// Update team wins for determining the winning team
	if winner != "Unknown" {
		mh.updateTeamWins(winner)
	}

	mh.logger.WithFields(logrus.Fields{
		"round":       mh.processor.matchState.CurrentRound,
		"winner":      winner,
		"team_a_wins": mh.processor.teamAWins,
		"team_b_wins": mh.processor.teamBWins,
	}).Debug("Round ended")

	return nil
}

// HandleBombPlanted handles bomb planted events
func (mh *MatchHandler) HandleBombPlanted(e events.BombPlanted) error {
	if mh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event", "BombPlanted")
	}

	mh.logger.Debug("Bomb planted")
	return nil
}

// HandleBombDefused handles bomb defused events
func (mh *MatchHandler) HandleBombDefused(e events.BombDefused) error {
	if mh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event", "BombDefused")
	}

	mh.logger.Debug("Bomb defused")
	return nil
}

// HandleBombExplode handles bomb explode events
func (mh *MatchHandler) HandleBombExplode(e events.BombExplode) error {
	if mh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event", "BombExplode")
	}

	mh.logger.Debug("Bomb exploded")
	return nil
}

// HandlePlayerConnect handles player connect events
func (mh *MatchHandler) HandlePlayerConnect(e events.PlayerConnect) error {
	if mh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event", "PlayerConnect")
	}

	if mh.processor.matchState == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "match state is nil", nil).
			WithContext("event", "PlayerConnect")
	}

	if e.Player == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "player is nil", nil).
			WithContext("event", "PlayerConnect")
	}

	steamID := types.SteamIDToString(e.Player.SteamID64)
	side := mh.getTeamString(e.Player.Team)

	// Assign team based on rounds 1-12
	mh.processor.assignTeamBasedOnRound1To12(steamID, side)
	assignedTeam := mh.processor.getAssignedTeam(steamID)

	// Add player to match state if not already present
	if _, exists := mh.processor.matchState.Players[steamID]; !exists {
		mh.processor.matchState.Players[steamID] = &types.Player{
			SteamID: steamID,
			Name:    e.Player.Name,
			Team:    assignedTeam,
		}
	}

	// Add player state
	if _, exists := mh.processor.playerStates[e.Player.SteamID64]; !exists {
		mh.processor.playerStates[e.Player.SteamID64] = &types.PlayerState{
			SteamID: steamID,
			Name:    e.Player.Name,
			Team:    assignedTeam,
		}
	}

	mh.logger.WithFields(logrus.Fields{
		"steam_id":      steamID,
		"name":          e.Player.Name,
		"side":          side,
		"assigned_team": assignedTeam,
	}).Debug("Player connected")

	return nil
}

// HandlePlayerDisconnected handles player disconnect events
func (mh *MatchHandler) HandlePlayerDisconnected(e events.PlayerDisconnected) error {
	if mh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event", "PlayerDisconnected")
	}

	if e.Player == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "player is nil", nil).
			WithContext("event", "PlayerDisconnected")
	}

	steamID := types.SteamIDToString(e.Player.SteamID64)

	mh.logger.WithFields(logrus.Fields{
		"steam_id": steamID,
		"name":     e.Player.Name,
	}).Debug("Player disconnected")

	return nil
}

// HandlePlayerTeamChange handles player team change events
func (mh *MatchHandler) HandlePlayerTeamChange(e events.PlayerTeamChange) error {
	if mh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event", "PlayerTeamChange")
	}

	if mh.processor.matchState == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "match state is nil", nil).
			WithContext("event", "PlayerTeamChange")
	}

	if e.Player == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "player is nil", nil).
			WithContext("event", "PlayerTeamChange")
	}

	steamID := types.SteamIDToString(e.Player.SteamID64)
	side := mh.getTeamString(e.Player.Team)

	// Assign team based on rounds 1-12 (if not already assigned)
	mh.processor.assignTeamBasedOnRound1To12(steamID, side)
	assignedTeam := mh.processor.getAssignedTeam(steamID)

	// Update player in match state
	if player, exists := mh.processor.matchState.Players[steamID]; exists {
		player.Team = assignedTeam
	}

	// Update player state
	if playerState, exists := mh.processor.playerStates[e.Player.SteamID64]; exists {
		playerState.Team = assignedTeam
	}

	mh.logger.WithFields(logrus.Fields{
		"steam_id":      steamID,
		"name":          e.Player.Name,
		"side":          side,
		"assigned_team": assignedTeam,
	}).Debug("Player team changed")

	return nil
}

// HandleWeaponFire handles weapon fire events
func (mh *MatchHandler) HandleWeaponFire(e events.WeaponFire) error {
	if mh.processor == nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "processor is nil", nil).
			WithContext("event", "WeaponFire")
	}

	if e.Shooter == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "shooter is nil", nil).
			WithContext("event", "WeaponFire")
	}

	if e.Weapon == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "weapon is nil", nil).
			WithContext("event", "WeaponFire")
	}

	// Ensure player is tracked
	if err := mh.processor.ensurePlayerTracked(e.Shooter); err != nil {
		return err
	}

	if playerState, exists := mh.processor.playerStates[e.Shooter.SteamID64]; exists {
		playerState.CurrentWeapon = e.Weapon.String()
	}

	// Check if this is a grenade throw - delegate to grenade handler
	if mh.isGrenadeWeapon(*e.Weapon) {
		// This will be handled by the grenade handler when we refactor the main processor
		// For now, we'll keep the tracking logic here temporarily
		mh.trackGrenadeThrow(e)
	}

	return nil
}

// getTeamString converts team enum to string
func (mh *MatchHandler) getTeamString(team common.Team) string {
	switch team {
	case common.TeamCounterTerrorists:
		return "CT"
	case common.TeamTerrorists:
		return "T"
	default:
		return "Unknown"
	}
}

// updateTeamWins updates the win count for the appropriate team based on round winner
func (mh *MatchHandler) updateTeamWins(winner string) {
	// Check for side switches before updating wins
	mh.checkForSideSwitch()

	if winner == mh.processor.teamACurrentSide {
		mh.processor.teamAWins++
	} else if winner == mh.processor.teamBCurrentSide {
		mh.processor.teamBWins++
	}
}

// checkForSideSwitch handles halftime and overtime side switches
func (mh *MatchHandler) checkForSideSwitch() {
	// Halftime switch: after round 12, teams switch sides
	if mh.processor.currentRound == 13 {
		mh.switchTeamSides()
		mh.logger.Info("Halftime switch occurred", logrus.Fields{
			"round":               mh.processor.currentRound,
			"team_a_current_side": mh.processor.teamACurrentSide,
			"team_b_current_side": mh.processor.teamBCurrentSide,
		})
		return
	}

	// Overtime switches: every 3 rounds after round 24
	if mh.processor.currentRound > 24 {
		// Calculate which overtime period we're in
		overtimeRounds := mh.processor.currentRound - 24
		// Switch sides at the start of each new overtime period (rounds 28, 31, 34, etc.)
		// This means: overtime rounds 4, 7, 10, etc. (every 3rd overtime round starting from round 4)
		if overtimeRounds%3 == 1 && overtimeRounds > 3 {
			mh.switchTeamSides()
			mh.logger.Info("Overtime side switch occurred", logrus.Fields{
				"round":               mh.processor.currentRound,
				"overtime_round":      overtimeRounds,
				"team_a_current_side": mh.processor.teamACurrentSide,
				"team_b_current_side": mh.processor.teamBCurrentSide,
			})
		}
	}
}

// switchTeamSides swaps the current sides of both teams
func (mh *MatchHandler) switchTeamSides() {
	mh.processor.teamACurrentSide, mh.processor.teamBCurrentSide = mh.processor.teamBCurrentSide, mh.processor.teamACurrentSide
}

// isGrenadeWeapon checks if a weapon is a grenade type
func (mh *MatchHandler) isGrenadeWeapon(weapon common.Equipment) bool {
	weaponType := weapon.Type
	return weaponType == common.EqHE || weaponType == common.EqFlash || weaponType == common.EqSmoke ||
		weaponType == common.EqMolotov || weaponType == common.EqIncendiary || weaponType == common.EqDecoy
}

// trackGrenadeThrow stores information about a grenade throw for later use
// TODO: This should be moved to the grenade handler when we refactor the main processor
func (mh *MatchHandler) trackGrenadeThrow(e events.WeaponFire) {
	// For now, we'll use a combination of player and tick to create a unique key
	// This is a fallback since entity ID might not be directly accessible
	key := int(e.Shooter.SteamID64) + int(mh.processor.currentTick)

	roundTime := mh.processor.getCurrentRoundTime()
	playerPos := mh.processor.getPlayerPosition(e.Shooter)
	playerAim := mh.processor.getPlayerAim(e.Shooter)

	throwInfo := &types.GrenadeThrowInfo{
		PlayerSteamID:  types.SteamIDToString(e.Shooter.SteamID64),
		PlayerPosition: playerPos,
		PlayerAim:      playerAim,
		ThrowTick:      mh.processor.currentTick,
		RoundNumber:    mh.processor.matchState.CurrentRound,
		RoundTime:      roundTime,
		GrenadeType:    e.Weapon.Type.String(),
	}

	mh.processor.grenadeThrows[key] = throwInfo

	mh.logger.WithFields(logrus.Fields{
		"key":          key,
		"player":       e.Shooter.Name,
		"grenade_type": e.Weapon.Type.String(),
		"round":        mh.processor.matchState.CurrentRound,
		"round_time":   roundTime,
	}).Debug("Tracked grenade throw")
}
