package parser

import (
	"context"
	"fmt"
	"parser-service/internal/config"
	"parser-service/internal/database"
	"parser-service/internal/types"
	"parser-service/internal/utils"
	"reflect"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

type EventProcessor struct {
	matchState   *types.MatchState
	logger       *logrus.Logger
	config       *config.Config
	playerStates map[uint64]*types.PlayerState
	perfLogger   *utils.PerformanceLogger

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
	aimTrackingHandler *AimTrackingHandler
	rankExtractor      *RankExtractor
	playerTickService  *database.PlayerTickService
	roundTickCache     *RoundTickCache
	matchID            string

	// Aim tracking results storage
	aimEvents       []types.AimAnalysisResult
	aimWeaponEvents []types.WeaponAimAnalysisResult
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

func NewEventProcessor(matchState *types.MatchState, logger *logrus.Logger, cfg *config.Config, perfLogger *utils.PerformanceLogger) *EventProcessor {
	ep := &EventProcessor{
		matchState:   matchState,
		logger:       logger,
		config:       cfg,
		perfLogger:   perfLogger,
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
	ep.aimTrackingHandler = NewAimTrackingHandler(ep, logger)
	ep.rankExtractor = NewRankExtractor(logger)

	return ep
}

func (ep *EventProcessor) SetDemoParser(parser demoinfocs.Parser) {
	ep.demoParser = parser
}

func (ep *EventProcessor) SetPlayerTickService(service *database.PlayerTickService) {
	ep.playerTickService = service
}

func (ep *EventProcessor) InitializeRoundTickCache(matchID string) {
	if ep.playerTickService != nil {
		ep.roundTickCache = NewRoundTickCache(ep.playerTickService, ep.logger, matchID)
	}
}

func (ep *EventProcessor) SetMatchID(matchID string) {
	ep.matchID = matchID
}

func (ep *EventProcessor) HandleRoundStart(e events.RoundStart) error {
	if ep.matchHandler == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "match handler is nil", nil).
			WithContext("event", "RoundStart")
	}
	return ep.matchHandler.HandleRoundStart(e)
}

func (ep *EventProcessor) HandleRoundEnd(e events.RoundEnd) error {
	if ep.matchHandler == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "match handler is nil", nil).
			WithContext("event", "RoundEnd")
	}
	if err := ep.matchHandler.HandleRoundEnd(e); err != nil {
		ep.logger.WithError(err).Error("Failed to handle round end")
		return types.NewParseError(types.ErrorTypeEventProcessing, "failed to handle round end", err).
			WithContext("event", "RoundEnd").
			WithContext("round", ep.matchState.CurrentRound)
	}
	if ep.grenadeHandler != nil {
		ep.grenadeHandler.CleanupDuplicateFlashGrenades()

		// Performance tracking for AggregateAllGrenadeDamage
		if ep.perfLogger != nil {
			timer := ep.perfLogger.StartTimer("AggregateAllGrenadeDamage").
				WithMetadata("round_number", ep.matchState.CurrentRound)
			ep.grenadeHandler.AggregateAllGrenadeDamage()
			timer.Stop()
		} else {
			ep.grenadeHandler.AggregateAllGrenadeDamage()
		}

		// Performance tracking for PopulateFlashGrenadeEffectiveness
		if ep.perfLogger != nil {
			timer := ep.perfLogger.StartTimer("PopulateFlashGrenadeEffectiveness").
				WithMetadata("round_number", ep.matchState.CurrentRound)
			ep.grenadeHandler.PopulateFlashGrenadeEffectiveness()
			timer.Stop()
		} else {
			ep.grenadeHandler.PopulateFlashGrenadeEffectiveness()
		}

		// Use the new post-processing method for smoke blocking duration
		if ep.playerTickService != nil {
			_ = ep.grenadeHandler.ProcessSmokeBlockingDurationPostProcess(ep.matchID)
		}
	}
	if ep.roundHandler == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "round handler is nil", nil).
			WithContext("event", "RoundEnd")
	}
	if err := ep.roundHandler.ProcessRoundEnd(); err != nil {
		ep.logger.WithError(err).Error("Failed to process round end")
		return types.NewParseError(types.ErrorTypeEventProcessing, "failed to process round end", err).
			WithContext("event", "RoundEnd").
			WithContext("round", ep.matchState.CurrentRound)
	}

	// Process aim tracking data for the round
	if ep.aimTrackingHandler != nil {
		// Performance tracking for DetectSprayingPatternsForRound
		if ep.perfLogger != nil {
			timer := ep.perfLogger.StartTimer("DetectSprayingPatternsForRound").
				WithMetadata("round_number", ep.matchState.CurrentRound)
			ep.aimTrackingHandler.DetectSprayingPatternsForRound(ep.matchState.CurrentRound)
			timer.Stop()
		} else {
			ep.aimTrackingHandler.DetectSprayingPatternsForRound(ep.matchState.CurrentRound)
		}

		// Performance tracking for processAimTrackingForRound
		if ep.perfLogger != nil {
			timer := ep.perfLogger.StartTimer("processAimTrackingForRound").
				WithMetadata("round_number", ep.matchState.CurrentRound)
			err := ep.processAimTrackingForRound()
			timer.Stop()
			if err != nil {
				ep.logger.WithError(err).Error("Failed to process aim tracking for round")
				return types.NewParseError(types.ErrorTypeEventProcessing, "failed to process aim tracking for round", err).
					WithContext("event", "RoundEnd").
					WithContext("round", ep.matchState.CurrentRound)
			}
		} else {
			if err := ep.processAimTrackingForRound(); err != nil {
				ep.logger.WithError(err).Error("Failed to process aim tracking for round")
				return types.NewParseError(types.ErrorTypeEventProcessing, "failed to process aim tracking for round", err).
					WithContext("event", "RoundEnd").
					WithContext("round", ep.matchState.CurrentRound)
			}
		}
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
	if err := ep.matchHandler.HandleWeaponFire(e); err != nil {
		return err
	}
	return ep.aimTrackingHandler.HandleWeaponFire(e)
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

	// Extract player rank
	var rankInfo *RankInfo
	if ep.rankExtractor != nil {
		rankInfo = ep.rankExtractor.ExtractPlayerRank(player)
	}

	if _, exists := ep.matchState.Players[steamID]; !exists {
		playerData := &types.Player{
			SteamID: steamID,
			Name:    player.Name,
			Team:    assignedTeam,
		}

		// Set rank fields if available
		if rankInfo != nil {
			playerData.Rank = rankInfo.RankString // Legacy field
			playerData.RankString = rankInfo.RankString
			playerData.RankType = rankInfo.RankType
			playerData.RankValue = rankInfo.RankValue
		}

		ep.matchState.Players[steamID] = playerData
	} else {
		// Update the rank if the player already exists but doesn't have a rank
		existingPlayer := ep.matchState.Players[steamID]
		if existingPlayer.Rank == nil && rankInfo != nil {
			existingPlayer.Rank = rankInfo.RankString // Legacy field
			existingPlayer.RankString = rankInfo.RankString
			existingPlayer.RankType = rankInfo.RankType
			existingPlayer.RankValue = rankInfo.RankValue
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

	// Attempting team assignment

	if ep.currentRound > 12 {
		// Skipping team assignment - round > 12
		return nil
	}

	if ep.assignmentComplete {
		// Skipping team assignment - already complete
		return nil
	}

	if _, assigned := ep.teamAssignments[steamID]; assigned {
		// Skipping team assignment - player already assigned
		return nil
	}

	if side == "CT" {
		ep.teamAssignments[steamID] = "A"
		// Player assigned to team A (CT)
		if ep.teamAStartedAs == "" {
			ep.teamAStartedAs = "CT"
			ep.teamBStartedAs = "T"
			ep.teamACurrentSide = "CT"
			ep.teamBCurrentSide = "T"
		}
	} else if side == "T" {
		ep.teamAssignments[steamID] = "B"
		// Player assigned to team B (T)
		if ep.teamBStartedAs == "" {
			ep.teamBStartedAs = "T"
			ep.teamAStartedAs = "CT"
			ep.teamACurrentSide = "CT"
			ep.teamBCurrentSide = "T"
		}
	}

	if len(ep.teamAssignments) == 10 {
		ep.assignmentComplete = true
		// Team assignment complete
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
		// Player not assigned to team
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
		// Processing frame for position tracking
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
		// Calculated round time (still in freeze time)

		return 0
	}

	actualRoundTime := timeSinceRoundStart - types.CS2FreezeTime

	// Calculated round time

	return actualRoundTime
}

func (ep *EventProcessor) getRoundScenario(killerSide, victimSide string) string {
	if ep.demoParser == nil {
		// removed non-error warn log
		return "0v0"
	}

	gameState := ep.demoParser.GameState()
	if gameState == nil {
		// removed non-error warn log
		return "0v0"
	}

	killerTeamAlive := 0
	victimTeamAlive := 0

	participants := gameState.Participants()
	if participants == nil {
		// removed non-error warn log
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

	// Calculated round scenario

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

// processAimTrackingForRound processes aim tracking data for the current round
func (ep *EventProcessor) processAimTrackingForRound() error {
	// Get shooting data for the current round
	shootingData := ep.aimTrackingHandler.GetShootingData()

	// Filter data for current round
	var roundShootingData []types.PlayerShootingData
	for _, shot := range shootingData {
		if shot.RoundNumber == ep.matchState.CurrentRound {
			roundShootingData = append(roundShootingData, shot)
		}
	}

	// Create aim utility service
	mapName := ep.matchState.MapName
	if mapName == "" {
		mapName = "de_ancient" // Default fallback map
	}

	aimService, err := utils.NewAimUtilityService(mapName, ep.config)
	if err != nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "failed to create aim utility service", err).
			WithContext("event", "RoundEnd").
			WithContext("round", ep.matchState.CurrentRound)
	}

	// Get damage events for the round
	var damageEvents []types.DamageEvent
	for _, damage := range ep.matchState.DamageEvents {
		if damage.RoundNumber == ep.matchState.CurrentRound {
			damageEvents = append(damageEvents, damage)
		}
	}

	// Get player tick data for the round using cache
	// Handle case where RoundEndTick is 0 (not set) by using current tick as fallback
	roundEndTick := ep.matchState.RoundEndTick
	if roundEndTick == 0 {
		roundEndTick = ep.currentTick
		ep.logger.WithFields(logrus.Fields{
			"round":         ep.matchState.CurrentRound,
			"round_start":   ep.matchState.RoundStartTick,
			"round_end":     ep.matchState.RoundEndTick,
			"current_tick":  ep.currentTick,
			"fallback_used": true,
		}).Warn("RoundEndTick is 0, using current tick as fallback for player tick data query")
	}

	// Load tick data for this round into cache (single bulk query)
	if ep.roundTickCache != nil {
		err := ep.roundTickCache.LoadRound(
			context.Background(),
			ep.matchState.CurrentRound,
			ep.matchState.RoundStartTick,
			roundEndTick,
		)
		if err != nil {
			ep.logger.WithError(err).Error("Failed to load round tick data into cache")
			return types.NewParseError(types.ErrorTypeEventProcessing, "failed to load round tick data", err).
				WithContext("event", "RoundEnd").
				WithContext("round", ep.matchState.CurrentRound)
		}

		// Log cache statistics
		cacheStats := ep.roundTickCache.GetCacheStats()
		ep.logger.WithFields(logrus.Fields{
			"round":            cacheStats["round"],
			"rows_loaded":      cacheStats["rows_loaded"],
			"load_duration_ms": cacheStats["load_duration_ms"],
			"memory_mb":        cacheStats["memory_estimate_mb"],
		}).Info("Round tick cache loaded")

		// Get all data from cache
		playerTickDataPointers := ep.roundTickCache.GetAllTickDataForRound()

		// Convert pointers to values for compatibility
		var playerTickData []types.PlayerTickData
		for _, ptr := range playerTickDataPointers {
			playerTickData = append(playerTickData, *ptr)
		}

		// Process aim tracking calculations
		aimResults, weaponResults, err := aimService.ProcessAimTrackingForRound(
			roundShootingData,
			damageEvents,
			playerTickData,
			ep.matchState.CurrentRound,
		)
		if err != nil {
			return types.NewParseError(types.ErrorTypeEventProcessing, "failed to process aim tracking calculations", err).
				WithContext("event", "RoundEnd").
				WithContext("round", ep.matchState.CurrentRound)
		}

		// Store results
		if len(aimResults) > 0 {
			ep.aimEvents = append(ep.aimEvents, aimResults...)
		}
		if len(weaponResults) > 0 {
			ep.aimWeaponEvents = append(ep.aimWeaponEvents, weaponResults...)
		}

		return nil
	}

	// Fallback to direct database query if cache not available
	playerTickDataPointers, err := ep.playerTickService.GetPlayerTickDataByRound(
		context.Background(),
		ep.matchID,
		ep.matchState.RoundStartTick,
		roundEndTick,
	)
	if err != nil {
		ep.logger.WithError(err).Error("Failed to retrieve player tick data for round")
		return types.NewParseError(types.ErrorTypeEventProcessing, "failed to retrieve player tick data", err).
			WithContext("event", "RoundEnd").
			WithContext("round", ep.matchState.CurrentRound)
	}

	// Convert pointers to values for compatibility
	var playerTickData []types.PlayerTickData
	for _, ptr := range playerTickDataPointers {
		playerTickData = append(playerTickData, *ptr)
	}

	// Process aim tracking calculations
	aimResults, weaponResults, err := aimService.ProcessAimTrackingForRound(
		roundShootingData,
		damageEvents,
		playerTickData,
		ep.matchState.CurrentRound,
	)
	if err != nil {
		return types.NewParseError(types.ErrorTypeEventProcessing, "failed to process aim tracking calculations", err).
			WithContext("event", "RoundEnd").
			WithContext("round", ep.matchState.CurrentRound)
	}

	// Store results for batch sending to Laravel
	if len(aimResults) > 0 {
		// Store aim results for batch sending
		ep.aimEvents = append(ep.aimEvents, aimResults...)

	}

	if len(weaponResults) > 0 {
		// Store weapon aim results for batch sending
		ep.aimWeaponEvents = append(ep.aimWeaponEvents, weaponResults...)

	}

	return nil
}
