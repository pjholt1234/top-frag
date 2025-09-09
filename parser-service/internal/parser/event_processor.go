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

	// Demo parser reference for accessing current game state
	demoParser demoinfocs.Parser

	// Grenade tracking by entity ID
	grenadeThrows map[int]*types.GrenadeThrowInfo // entityID -> throw info

	// Flash tracking
	activeFlashEffects map[int]*FlashEffect // entityID -> flash effect info

	// Event handlers
	grenadeHandler     *GrenadeHandler
	gunfightHandler    *GunfightHandler
	damageHandler      *DamageHandler
	matchHandler       *MatchHandler
	roundHandler       *RoundHandler
	playerMatchHandler *PlayerMatchHandler
}

// FlashEffect tracks information about an active flash effect
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

// PlayerFlashInfo tracks individual player flash information
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

	// Initialize event handlers
	ep.grenadeHandler = NewGrenadeHandler(ep, logger)
	ep.gunfightHandler = NewGunfightHandler(ep, logger)
	ep.damageHandler = NewDamageHandler(ep, logger)
	ep.matchHandler = NewMatchHandler(ep, logger)
	ep.roundHandler = NewRoundHandler(ep, logger)
	ep.playerMatchHandler = NewPlayerMatchHandler(ep, logger)

	return ep
}

// SetDemoParser sets the demo parser reference for accessing current game state
func (ep *EventProcessor) SetDemoParser(parser demoinfocs.Parser) {
	ep.demoParser = parser
}

func (ep *EventProcessor) HandleRoundStart(e events.RoundStart) {
	ep.matchHandler.HandleRoundStart(e)
}

func (ep *EventProcessor) HandleRoundEnd(e events.RoundEnd) {
	ep.matchHandler.HandleRoundEnd(e)
	ep.grenadeHandler.AggregateAllGrenadeDamage()
	ep.grenadeHandler.PopulateFlashGrenadeEffectiveness()
	ep.roundHandler.ProcessRoundEnd()
}

func (ep *EventProcessor) HandlePlayerKilled(e events.Kill) {
	ep.gunfightHandler.HandlePlayerKilled(e)
}

func (ep *EventProcessor) HandlePlayerHurt(e events.PlayerHurt) {
	ep.damageHandler.HandlePlayerHurt(e)
}

func (ep *EventProcessor) HandleGrenadeProjectileThrow(e events.GrenadeProjectileThrow) {
	ep.grenadeHandler.HandleGrenadeProjectileThrow(e)
}

func (ep *EventProcessor) HandleGrenadeProjectileDestroy(e events.GrenadeProjectileDestroy) {
	ep.grenadeHandler.HandleGrenadeProjectileDestroy(e)
}

func (ep *EventProcessor) HandleFlashExplode(e events.FlashExplode) {
	ep.grenadeHandler.HandleFlashExplode(e)
}

func (ep *EventProcessor) HandlePlayerFlashed(e events.PlayerFlashed) {
	ep.grenadeHandler.HandlePlayerFlashed(e)
}

func (ep *EventProcessor) HandleSmokeStart(e events.SmokeStart) {
	ep.grenadeHandler.HandleSmokeStart(e)
}

func (ep *EventProcessor) HandleWeaponFire(e events.WeaponFire) {
	ep.matchHandler.HandleWeaponFire(e)
}

func (ep *EventProcessor) HandleBombPlanted(e events.BombPlanted) {
	ep.matchHandler.HandleBombPlanted(e)
}

func (ep *EventProcessor) HandleBombDefused(e events.BombDefused) {
	ep.matchHandler.HandleBombDefused(e)
}

func (ep *EventProcessor) HandleBombExplode(e events.BombExplode) {
	ep.matchHandler.HandleBombExplode(e)
}

func (ep *EventProcessor) HandlePlayerConnect(e events.PlayerConnect) {
	ep.matchHandler.HandlePlayerConnect(e)
}

func (ep *EventProcessor) HandlePlayerDisconnected(e events.PlayerDisconnected) {
	ep.matchHandler.HandlePlayerDisconnected(e)
}

func (ep *EventProcessor) HandlePlayerTeamChange(e events.PlayerTeamChange) {
	ep.matchHandler.HandlePlayerTeamChange(e)
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

// getTeamString converts team enum to string
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

// UpdateCurrentTickAndPlayers updates the current tick and tracks all player positions
func (ep *EventProcessor) UpdateCurrentTickAndPlayers(tick int64, gameState interface{}) {
	ep.currentTick = tick

	// Debug: Log frame processing every 1000 ticks
	if tick%1000 == 0 {
		ep.logger.WithFields(logrus.Fields{
			"tick": tick,
		}).Debug("Processing frame for position tracking")
	}

	// Update position history for all active players
	// Use reflection to access the game state methods dynamically
	if gameState != nil {
		// Try different approaches to get players
		playerCount := 0

		// Approach 1: Try direct method call using reflection
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

	// Subtract freeze time to get actual gameplay time
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
		if player != nil && player.IsAlive() {
			playerSide := ep.getTeamString(player.Team)
			if playerSide == killerSide {
				killerTeamAlive++
			} else if playerSide == victimSide {
				victimTeamAlive++
			}
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

// callMethod uses reflection to call a method on an object
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

	// Call the method with no arguments
	results := method.Call(nil)
	if len(results) == 0 {
		return nil
	}

	// Return the first result
	result := results[0]
	if !result.IsValid() || result.IsNil() {
		return nil
	}

	return result.Interface()
}
