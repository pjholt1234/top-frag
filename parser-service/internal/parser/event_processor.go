package parser

import (
	"fmt"
	"parser-service/internal/types"
	"reflect"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

type EventProcessor struct {
	matchState   *types.MatchState
	logger       *logrus.Logger
	playerStates map[uint64]*types.PlayerState

	teamAssignments    map[string]string
	teamAWins          int
	teamBWins          int
	teamAStartedAs     string
	teamBStartedAs     string
	teamACurrentSide   string
	teamBCurrentSide   string
	assignmentComplete bool
	currentRound       int
	currentTick        int64

	demoParser demoinfocs.Parser

	grenadeThrows map[int]*types.GrenadeThrowInfo

	activeFlashEffects map[int]*FlashEffect

	grenadeHandler     *GrenadeHandler
	gunfightHandler    *GunfightHandler
	damageHandler      *DamageHandler
	matchHandler       *MatchHandler
	roundHandler       *RoundHandler
	playerMatchHandler *PlayerMatchHandler
}

type FlashEffect struct {
	EntityID          int
	ThrowerSteamID    string
	ExplosionTick     int64
	RoundNumber       int
	ExplosionPosition types.Position
	AffectedPlayers   map[uint64]*PlayerFlashInfo
	FriendlyDuration  float64
	EnemyDuration     float64
	FriendlyCount     int
	EnemyCount        int
}

type PlayerFlashInfo struct {
	SteamID       string
	Team          string
	FlashDuration float64
	IsFriendly    bool
}

func NewEventProcessor(matchState *types.MatchState, logger *logrus.Logger) *EventProcessor {
	ep := &EventProcessor{
		matchState:   matchState,
		logger:       logger,
		playerStates: make(map[uint64]*types.PlayerState),

		teamAssignments:    make(map[string]string),
		teamAWins:          0,
		teamBWins:          0,
		teamAStartedAs:     "",
		teamBStartedAs:     "",
		teamACurrentSide:   "",
		teamBCurrentSide:   "",
		assignmentComplete: false,
		currentRound:       0,
		currentTick:        0,

		grenadeThrows: make(map[int]*types.GrenadeThrowInfo),

		activeFlashEffects: make(map[int]*FlashEffect),
	}

	ep.grenadeHandler = NewGrenadeHandler(ep, logger)
	ep.gunfightHandler = NewGunfightHandler(ep, logger)
	ep.damageHandler = NewDamageHandler(ep, logger)
	ep.matchHandler = NewMatchHandler(ep, logger)
	ep.roundHandler = NewRoundHandler(ep, logger)
	ep.playerMatchHandler = NewPlayerMatchHandler(ep, logger)

	return ep
}

func (ep *EventProcessor) SetDemoParser(parser demoinfocs.Parser) {
	ep.demoParser = parser
}

func (ep *EventProcessor) HandleRoundStart(e events.RoundStart) error {
	return ep.matchHandler.HandleRoundStart(e)
}

func (ep *EventProcessor) HandleRoundEnd(e events.RoundEnd) error {
	if err := ep.matchHandler.HandleRoundEnd(e); err != nil {
		ep.logger.WithError(err).Error("Failed to handle round end")
		return types.NewParseError(types.ErrorTypeEventProcessing, "failed to handle round end", err).
			WithContext("event", "RoundEnd").
			WithContext("round", ep.matchState.CurrentRound)
	}
	ep.grenadeHandler.AggregateAllGrenadeDamage()
	ep.grenadeHandler.PopulateFlashGrenadeEffectiveness()
	if err := ep.roundHandler.ProcessRoundEnd(); err != nil {
		ep.logger.WithError(err).Error("Failed to process round end")
		return types.NewParseError(types.ErrorTypeEventProcessing, "failed to process round end", err).
			WithContext("event", "RoundEnd").
			WithContext("round", ep.matchState.CurrentRound)
	}
	return nil
}

func (ep *EventProcessor) HandlePlayerKilled(e events.Kill) error {
	return ep.gunfightHandler.HandlePlayerKilled(e)
}

func (ep *EventProcessor) HandlePlayerHurt(e events.PlayerHurt) error {
	return ep.damageHandler.HandlePlayerHurt(e)
}

func (ep *EventProcessor) HandleGrenadeProjectileThrow(e events.GrenadeProjectileThrow) error {
	return ep.grenadeHandler.HandleGrenadeProjectileThrow(e)
}

func (ep *EventProcessor) HandleGrenadeProjectileDestroy(e events.GrenadeProjectileDestroy) error {
	return ep.grenadeHandler.HandleGrenadeProjectileDestroy(e)
}

func (ep *EventProcessor) HandleFlashExplode(e events.FlashExplode) error {
	return ep.grenadeHandler.HandleFlashExplode(e)
}

func (ep *EventProcessor) HandlePlayerFlashed(e events.PlayerFlashed) error {
	return ep.grenadeHandler.HandlePlayerFlashed(e)
}

func (ep *EventProcessor) HandleSmokeStart(e events.SmokeStart) error {
	return ep.grenadeHandler.HandleSmokeStart(e)
}

func (ep *EventProcessor) HandleWeaponFire(e events.WeaponFire) error {
	return ep.matchHandler.HandleWeaponFire(e)
}

func (ep *EventProcessor) HandleBombPlanted(e events.BombPlanted) error {
	return ep.matchHandler.HandleBombPlanted(e)
}

func (ep *EventProcessor) HandleBombDefused(e events.BombDefused) error {
	return ep.matchHandler.HandleBombDefused(e)
}

func (ep *EventProcessor) HandleBombExplode(e events.BombExplode) error {
	return ep.matchHandler.HandleBombExplode(e)
}

func (ep *EventProcessor) HandlePlayerConnect(e events.PlayerConnect) error {
	return ep.matchHandler.HandlePlayerConnect(e)
}

func (ep *EventProcessor) HandlePlayerDisconnected(e events.PlayerDisconnected) error {
	return ep.matchHandler.HandlePlayerDisconnected(e)
}

func (ep *EventProcessor) HandlePlayerTeamChange(e events.PlayerTeamChange) error {
	return ep.matchHandler.HandlePlayerTeamChange(e)
}

func (ep *EventProcessor) getPlayerPosition(player *common.Player) types.Position {
	if player == nil {
		return types.Position{}
	}

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

func (ep *EventProcessor) ensurePlayerTracked(player *common.Player) error {
	if player == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "player is nil", nil).
			WithContext("method", "ensurePlayerTracked")
	}

	if ep.matchState == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "match state is nil", nil).
			WithContext("method", "ensurePlayerTracked")
	}

	if ep.playerStates == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "player states is nil", nil).
			WithContext("method", "ensurePlayerTracked")
	}

	steamID := types.SteamIDToString(player.SteamID64)
	side := ep.getTeamString(player.Team)

	if err := ep.assignTeamBasedOnRound1To12(steamID, side); err != nil {
		return err
	}
	assignedTeam := ep.getAssignedTeam(steamID)

	if _, exists := ep.matchState.Players[steamID]; !exists {
		ep.matchState.Players[steamID] = &types.Player{
			SteamID: steamID,
			Name:    player.Name,
			Team:    assignedTeam,
		}
	}

	if _, exists := ep.playerStates[player.SteamID64]; !exists {
		ep.playerStates[player.SteamID64] = &types.PlayerState{
			SteamID: steamID,
			Name:    player.Name,
			Team:    assignedTeam,
		}
	}

	return nil
}

func (ep *EventProcessor) assignTeamBasedOnRound1To12(steamID string, side string) error {
	if ep.logger == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "logger is nil", nil).
			WithContext("method", "assignTeamBasedOnRound1To12")
	}

	if ep.teamAssignments == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "team assignments is nil", nil).
			WithContext("method", "assignTeamBasedOnRound1To12")
	}

	ep.logger.WithFields(logrus.Fields{
		"steam_id":            steamID,
		"side":                side,
		"current_round":       ep.currentRound,
		"assignment_complete": ep.assignmentComplete,
	}).Debug("Attempting team assignment")

	if ep.currentRound > 12 {
		ep.logger.WithFields(logrus.Fields{
			"steam_id":      steamID,
			"current_round": ep.currentRound,
		}).Debug("Skipping team assignment - round > 12")
		return nil
	}

	if ep.assignmentComplete {
		ep.logger.WithFields(logrus.Fields{
			"steam_id": steamID,
		}).Debug("Skipping team assignment - already complete")
		return nil
	}

	if _, assigned := ep.teamAssignments[steamID]; assigned {
		ep.logger.WithFields(logrus.Fields{
			"steam_id": steamID,
		}).Debug("Skipping team assignment - player already assigned")
		return nil
	}

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

	return nil
}

func (ep *EventProcessor) getAssignedTeam(steamID string) string {
	if team, assigned := ep.teamAssignments[steamID]; assigned {
		return team
	}
	return "A"
}

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

	return "Unknown"
}

func (ep *EventProcessor) getWinningTeam() string {
	if ep.teamAWins > ep.teamBWins {
		return "A"
	} else if ep.teamBWins > ep.teamAWins {
		return "B"
	}
	return "A"
}

func (ep *EventProcessor) UpdateCurrentTick(tick int64) {
	ep.currentTick = tick
}

func (ep *EventProcessor) UpdateCurrentTickAndPlayers(tick int64, gameState interface{}) {
	ep.currentTick = tick

	if tick%1000 == 0 {
		ep.logger.WithFields(logrus.Fields{
			"tick": tick,
		}).Debug("Processing frame for position tracking")
	}

	if gameState == nil {
		return
	}

	playerCount := 0

	if participants := ep.callMethod(gameState, "Participants"); participants != nil {
		if players := ep.callMethod(participants, "Playing"); players != nil {
			if playerSlice, ok := players.([]*common.Player); ok {
				for _, player := range playerSlice {
					if player != nil {
						ep.grenadeHandler.movementService.UpdatePlayerPosition(player, tick)
						playerCount++
					}
				}
			}
		}
	}
}

func (ep *EventProcessor) getCurrentRoundTime() int {
	if ep.matchState.RoundStartTick == 0 {
		return 0
	}

	timeSinceRoundStart := int((ep.currentTick - ep.matchState.RoundStartTick) / 64)

	if timeSinceRoundStart < types.CS2FreezeTime {
		ep.logger.WithFields(logrus.Fields{
			"current_tick":      ep.currentTick,
			"round_start_tick":  ep.matchState.RoundStartTick,
			"time_since_start":  timeSinceRoundStart,
			"freeze_time":       types.CS2FreezeTime,
			"actual_round_time": 0,
			"tick_difference":   ep.currentTick - ep.matchState.RoundStartTick,
		}).Info("Calculated round time (still in freeze time)")

		return 0
	}

	actualRoundTime := timeSinceRoundStart - types.CS2FreezeTime

	ep.logger.WithFields(logrus.Fields{
		"current_tick":      ep.currentTick,
		"round_start_tick":  ep.matchState.RoundStartTick,
		"time_since_start":  timeSinceRoundStart,
		"freeze_time":       types.CS2FreezeTime,
		"actual_round_time": actualRoundTime,
		"tick_difference":   ep.currentTick - ep.matchState.RoundStartTick,
	}).Info("Calculated round time")

	return actualRoundTime
}

func (ep *EventProcessor) getRoundScenario(killerSide, victimSide string) string {
	if ep.demoParser == nil {
		ep.logger.Warn("Demo parser not available for round scenario calculation")
		return "0v0"
	}

	gameState := ep.demoParser.GameState()
	if gameState == nil {
		ep.logger.Warn("Game state not available for round scenario calculation")
		return "0v0"
	}

	killerTeamAlive := 0
	victimTeamAlive := 0

	participants := gameState.Participants()
	if participants == nil {
		ep.logger.Warn("Participants not available for round scenario calculation")
		return "0v0"
	}

	players := participants.Playing()
	for _, player := range players {
		if player == nil || !player.IsAlive() {
			continue
		}

		playerSide := ep.getTeamString(player.Team)
		if playerSide == killerSide {
			killerTeamAlive++
		} else if playerSide == victimSide {
			victimTeamAlive++
		}
	}

	ep.logger.WithFields(logrus.Fields{
		"killer_side":       killerSide,
		"victim_side":       victimSide,
		"killer_team_alive": killerTeamAlive,
		"victim_team_alive": victimTeamAlive,
		"round_scenario":    fmt.Sprintf("%dv%d", killerTeamAlive, victimTeamAlive),
	}).Debug("Calculated round scenario")

	return fmt.Sprintf("%dv%d", killerTeamAlive, victimTeamAlive)
}

func (ep *EventProcessor) callMethod(obj interface{}, methodName string) interface{} {
	if obj == nil {
		return nil
	}

	val := reflect.ValueOf(obj)
	if !val.IsValid() {
		return nil
	}

	method := val.MethodByName(methodName)
	if !method.IsValid() {
		return nil
	}

	results := method.Call(nil)
	if len(results) == 0 {
		return nil
	}

	result := results[0]
	if !result.IsValid() || result.IsNil() {
		return nil
	}

	return result.Interface()
}
