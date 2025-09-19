package parser

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"parser-service/internal/config"
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/msg"
	"github.com/sirupsen/logrus"
)

type DemoParser struct {
	config           *config.Config
	logger           *logrus.Logger
	progressManager  *ProgressManager
	gameModeDetector *GameModeDetector
}

func NewDemoParser(cfg *config.Config, logger *logrus.Logger) *DemoParser {
	return &DemoParser{
		config:           cfg,
		logger:           logger,
		gameModeDetector: NewGameModeDetector(logger),
	}
}

func (dp *DemoParser) ParseDemo(ctx context.Context, demoPath string, progressCallback func(types.ProgressUpdate)) (*types.ParsedDemoData, error) {
	return dp.ParseDemoFromFile(ctx, demoPath, progressCallback)
}

// ParseDemoFromFile parses a demo file from a file path
func (dp *DemoParser) ParseDemoFromFile(ctx context.Context, demoPath string, progressCallback func(types.ProgressUpdate)) (*types.ParsedDemoData, error) {
	dp.logger.WithField("demo_path", demoPath).Info("Starting demo parsing")

	// Initialize progress manager
	dp.progressManager = NewProgressManager(dp.logger, progressCallback, 100*time.Millisecond)

	// Enhanced panic recovery
	defer func() {
		if r := recover(); r != nil {
			errorMsg := fmt.Sprintf("panic during demo parsing: %v", r)
			dp.progressManager.ReportError(errorMsg, "PARSING_PANIC")
			dp.logger.WithField("panic", r).Error("Panic occurred during demo parsing")
		}
	}()

	if err := dp.validateDemoFile(demoPath); err != nil {
		parseError := types.NewParseError(types.ErrorTypeValidation, "demo file validation failed", err).
			WithContext("demo_path", demoPath)
		dp.progressManager.ReportParseError(parseError)
		return nil, parseError
	}

	matchState := &types.MatchState{
		Players:           make(map[string]*types.Player),
		RoundEvents:       make([]types.RoundEvent, 0),
		GunfightEvents:    make([]types.GunfightEvent, 0),
		GrenadeEvents:     make([]types.GrenadeEvent, 0),
		DamageEvents:      make([]types.DamageEvent, 0),
		PlayerRoundEvents: make([]types.PlayerRoundEvent, 0),
	}

	eventProcessor := NewEventProcessor(matchState, dp.logger)

	dp.progressManager.UpdateProgress(types.ProgressUpdate{
		Status:         types.StatusParsing,
		Progress:       15,
		CurrentStep:    "Parsing demo file",
		StepProgress:   0,
		TotalSteps:     18, // Will be updated when we know round count
		CurrentStepNum: 1,
		StartTime:      time.Now(),
		Context:        map[string]interface{}{"step": "file_validation"},
		IsFinal:        false,
	})

	var mapName string
	var demoParser demoinfocs.Parser

	err := demoinfocs.ParseFile(demoPath, func(parser demoinfocs.Parser) error {
		// Check if error has already occurred
		if dp.progressManager.HasError() {
			return fmt.Errorf("parsing stopped due to previous error")
		}

		demoParser = parser
		eventProcessor.SetDemoParser(parser)

		parser.RegisterNetMessageHandler(func(m *msg.CDemoFileHeader) {
			mapName = m.GetMapName()
			dp.logger.WithField("map_name", mapName).Info("Map name extracted from demo header")
		})

		dp.registerEventHandlers(parser, eventProcessor)

		parser.RegisterEventHandler(func(e events.FrameDone) {
			eventProcessor.UpdateCurrentTickAndPlayers(int64(parser.GameState().IngameTick()), parser.GameState())
		})

		gameState := parser.GameState()
		if gameState == nil {
			return nil
		}

		totalRoundsPlayed := gameState.TotalRoundsPlayed()
		dp.logger.WithFields(logrus.Fields{
			"game_state_total_rounds": totalRoundsPlayed,
			"current_round":           eventProcessor.matchState.CurrentRound,
			"round_events_count":      len(eventProcessor.matchState.RoundEvents),
		}).Info("Final game state information")

		return nil
	})

	if err != nil {
		parseError := types.NewParseError(types.ErrorTypeParsing, "failed to parse demo", err).
			WithContext("demo_path", demoPath)
		dp.progressManager.ReportParseError(parseError)
		return nil, parseError
	}

	// Check if critical error occurred during parsing
	if dp.progressManager.HasError() {
		errorMsg, errorCode := dp.progressManager.GetError()
		parseError := types.NewParseError(types.ErrorTypeEventProcessing, errorMsg, nil).
			WithContext("demo_path", demoPath).
			WithContext("error_code", errorCode)
		return nil, parseError
	}

	playbackTicks := 0
	if demoParser != nil {
		playbackTicks = demoParser.CurrentFrame()
		dp.logger.WithField("playback_ticks", playbackTicks).Info("Extracted playback ticks from demo parser")
	}

	dp.progressManager.UpdateProgress(types.ProgressUpdate{
		Status:         types.StatusProcessingEvents,
		Progress:       85,
		CurrentStep:    "Processing final data",
		StepProgress:   0,
		TotalSteps:     18, // Will be updated when we know round count
		CurrentStepNum: 11, // Final data assembly step
		StartTime:      time.Now(),
		Context:        map[string]interface{}{"step": "final_data_assembly"},
		IsFinal:        false,
	})

	dp.postProcessGrenadeMovement(eventProcessor)
	dp.postProcessDamageAssists(eventProcessor)

	parsedData := dp.buildParsedData(matchState, mapName, playbackTicks, eventProcessor, demoParser)

	dp.logger.WithField("total_events", len(matchState.GunfightEvents)+len(matchState.GrenadeEvents)+len(matchState.DamageEvents)).
		Info("Demo parsing completed")

	// Report completion
	dp.progressManager.ReportCompletion(types.ProgressUpdate{
		Status:         types.StatusCompleted,
		Progress:       100,
		CurrentStep:    "Demo parsing completed",
		StepProgress:   100,
		TotalSteps:     18,
		CurrentStepNum: 18,
		StartTime:      time.Now(),
		Context:        map[string]interface{}{"step": "completion"},
		IsFinal:        true,
	})

	return parsedData, nil
}

func (dp *DemoParser) postProcessGrenadeMovement(eventProcessor *EventProcessor) {
	dp.logger.Info("Starting grenade movement post-processing", logrus.Fields{
		"total_grenades": len(eventProcessor.matchState.GrenadeEvents),
	})

	movementService := eventProcessor.grenadeHandler.movementService
	processedCount := 0

	for i := range eventProcessor.matchState.GrenadeEvents {
		grenadeEvent := &eventProcessor.matchState.GrenadeEvents[i]
		steamID := types.StringToSteamID(grenadeEvent.PlayerSteamID)

		newMovementType := movementService.CalculateGrenadeMovementSimple(
			steamID,
			grenadeEvent.RoundNumber,
			grenadeEvent.TickTimestamp,
		)

		if newMovementType == "" {
			continue
		}

		grenadeEvent.ThrowType = newMovementType
		processedCount++
	}

	dp.logger.Info("Completed grenade movement post-processing", logrus.Fields{
		"processed_count": processedCount,
		"total_grenades":  len(eventProcessor.matchState.GrenadeEvents),
	})
}

func (dp *DemoParser) postProcessDamageAssists(eventProcessor *EventProcessor) {
	dp.logger.Info("Starting damage assist post-processing", logrus.Fields{
		"total_gunfights": len(eventProcessor.matchState.GunfightEvents),
	})

	processedCount := 0
	updatedCount := 0

	for i := range eventProcessor.matchState.GunfightEvents {
		gunfightEvent := &eventProcessor.matchState.GunfightEvents[i]

		if gunfightEvent.VictorSteamID == nil {
			continue
		}

		processedCount++

		originalAssist := gunfightEvent.DamageAssistSteamID
		newAssist := eventProcessor.gunfightHandler.findDamageAssist(
			gunfightEvent.Player2SteamID,
			*gunfightEvent.VictorSteamID,
		)

		if (originalAssist == nil && newAssist != nil) ||
			(originalAssist != nil && newAssist == nil) ||
			(originalAssist != nil && newAssist != nil && *originalAssist != *newAssist) {
			gunfightEvent.DamageAssistSteamID = newAssist
			updatedCount++

			dp.logger.WithFields(logrus.Fields{
				"round_number":    gunfightEvent.RoundNumber,
				"victim_steam_id": gunfightEvent.Player2SteamID,
				"killer_steam_id": *gunfightEvent.VictorSteamID,
				"original_assist": originalAssist,
				"new_assist":      newAssist,
			}).Debug("Updated damage assist")
		}
	}

	dp.logger.Info("Completed damage assist post-processing", logrus.Fields{
		"processed_count": processedCount,
		"updated_count":   updatedCount,
		"total_gunfights": len(eventProcessor.matchState.GunfightEvents),
	})
}

func (dp *DemoParser) validateDemoFile(demoPath string) error {
	if _, err := os.Stat(demoPath); os.IsNotExist(err) {
		return fmt.Errorf("demo file does not exist: %s", demoPath)
	}

	if filepath.Ext(demoPath) != ".dem" {
		return fmt.Errorf("invalid file extension, expected .dem: %s", demoPath)
	}

	fileInfo, err := os.Stat(demoPath)
	if err != nil {
		return fmt.Errorf("failed to get file info: %w", err)
	}

	if fileInfo.Size() > dp.config.Parser.MaxDemoSize {
		return fmt.Errorf("demo file too large: %d bytes (max: %d)", fileInfo.Size(), dp.config.Parser.MaxDemoSize)
	}

	return nil
}

func (dp *DemoParser) registerEventHandlers(parser demoinfocs.Parser, eventProcessor *EventProcessor) {
	parser.RegisterEventHandler(func(e events.RoundStart) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleRoundStart(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "ROUND_START_FAILED")
			}
			return
		}

		dp.progressManager.UpdateProgress(types.ProgressUpdate{
			Status:         types.StatusProcessingEvents,
			Progress:       20 + (eventProcessor.matchState.CurrentRound * 2),
			CurrentStep:    fmt.Sprintf("Processing round %d", eventProcessor.matchState.CurrentRound),
			StepProgress:   0,
			TotalSteps:     18 + eventProcessor.matchState.TotalRounds,
			CurrentStepNum: 3,
			StartTime:      time.Now(),
			Context: map[string]interface{}{
				"step":         "round_events_processing",
				"round":        eventProcessor.matchState.CurrentRound,
				"total_rounds": eventProcessor.matchState.TotalRounds,
			},
			IsFinal: false,
		})
	})

	parser.RegisterEventHandler(func(e events.RoundEnd) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleRoundEnd(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "ROUND_END_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.Kill) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandlePlayerKilled(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "PLAYER_KILLED_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.PlayerHurt) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandlePlayerHurt(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "PLAYER_HURT_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.GrenadeProjectileThrow) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleGrenadeProjectileThrow(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "GRENADE_THROW_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.GrenadeProjectileDestroy) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleGrenadeProjectileDestroy(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "GRENADE_DESTROY_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.FlashExplode) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleFlashExplode(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "FLASH_EXPLODE_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.PlayerFlashed) {
		if dp.progressManager.HasError() {
			return
		}

		dp.logger.WithFields(logrus.Fields{
			"player": e.Player.Name,
			"tick":   eventProcessor.currentTick,
		}).Info("PlayerFlashed event received")

		if err := eventProcessor.HandlePlayerFlashed(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "PLAYER_FLASHED_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.SmokeStart) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleSmokeStart(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "SMOKE_START_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.WeaponFire) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleWeaponFire(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "WEAPON_FIRE_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.BombPlanted) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleBombPlanted(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "BOMB_PLANTED_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.BombDefused) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleBombDefused(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "BOMB_DEFUSED_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.BombExplode) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandleBombExplode(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "BOMB_EXPLODE_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.PlayerConnect) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandlePlayerConnect(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "PLAYER_CONNECT_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.PlayerDisconnected) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandlePlayerDisconnected(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "PLAYER_DISCONNECTED_FAILED")
			}
			return
		}
	})

	parser.RegisterEventHandler(func(e events.PlayerTeamChange) {
		if dp.progressManager.HasError() {
			return
		}

		if err := eventProcessor.HandlePlayerTeamChange(e); err != nil {
			if parseErr, ok := err.(*types.ParseError); ok {
				dp.progressManager.ReportParseError(parseErr)
			} else {
				dp.progressManager.ReportError(err.Error(), "PLAYER_TEAM_CHANGE_FAILED")
			}
			return
		}
	})
}

func (dp *DemoParser) buildParsedData(matchState *types.MatchState, mapName string, playbackTicks int, eventProcessor *EventProcessor, demoParser demoinfocs.Parser) *types.ParsedDemoData {
	players := make([]types.Player, 0, len(matchState.Players))
	for _, player := range matchState.Players {
		players = append(players, *player)
	}

	if mapName == "" {
		mapName = "de_dust2"
		dp.logger.Warn("Map name not found in demo header, using default: de_dust2")
	}

	totalRounds := 0
	for _, roundEvent := range matchState.RoundEvents {
		if roundEvent.EventType == "end" {
			totalRounds++
		}
	}

	winningTeam := eventProcessor.getWinningTeam()
	teamAWins := eventProcessor.teamAWins
	teamBWins := eventProcessor.teamBWins

	winningTeamScore := 0
	losingTeamScore := 0

	if winningTeam == "A" {
		winningTeamScore = teamAWins
		losingTeamScore = teamBWins
	} else {
		winningTeamScore = teamBWins
		losingTeamScore = teamAWins
	}

	// Detect game mode
	gameMode, gameModeError := dp.gameModeDetector.DetectGameMode(demoParser)

	// Log game mode detection errors but continue parsing
	if gameModeError != nil {
		if parseErr, ok := gameModeError.(*types.ParseError); ok {
			dp.progressManager.ReportParseError(parseErr)
		} else {
			dp.progressManager.ReportError(gameModeError.Error(), "GAME_MODE_DETECTION_FAILED")
		}
	}

	// Determine match type based on player ranks
	matchType := dp.detectMatchType(demoParser)

	match := types.Match{
		Map:              mapName,
		WinningTeam:      winningTeam,
		WinningTeamScore: winningTeamScore,
		LosingTeamScore:  losingTeamScore,
		MatchType:        matchType,
		GameMode:         gameMode,
		StartTimestamp:   nil,
		EndTimestamp:     nil,
		TotalRounds:      totalRounds,
		PlaybackTicks:    playbackTicks,
	}

	now := time.Now()
	match.EndTimestamp = &now

	// Aggregate player match events before logging
	eventProcessor.playerMatchHandler.aggregatePlayerMatchEvent()

	dp.logger.WithFields(logrus.Fields{
		"map_name":            mapName,
		"total_rounds":        match.TotalRounds,
		"winning_team":        match.WinningTeam,
		"winning_team_score":  match.WinningTeamScore,
		"losing_team_score":   match.LosingTeamScore,
		"team_a_wins":         teamAWins,
		"team_b_wins":         teamBWins,
		"team_a_started_as":   eventProcessor.teamAStartedAs,
		"team_b_started_as":   eventProcessor.teamBStartedAs,
		"playback_ticks":      match.PlaybackTicks,
		"match_type":          match.MatchType,
		"game_mode":           gameMode.Mode,
		"game_mode_display":   gameMode.DisplayName,
		"gunfight_events":     len(matchState.GunfightEvents),
		"grenade_events":      len(matchState.GrenadeEvents),
		"damage_events":       len(matchState.DamageEvents),
		"round_events":        len(matchState.RoundEvents),
		"player_round_events": len(matchState.PlayerRoundEvents),
		"player_match_events": len(matchState.PlayerMatchEvents),
	}).Info("Match data built with event counts")

	return &types.ParsedDemoData{
		Match:             match,
		Players:           players,
		GunfightEvents:    matchState.GunfightEvents,
		GrenadeEvents:     matchState.GrenadeEvents,
		RoundEvents:       matchState.RoundEvents,
		DamageEvents:      matchState.DamageEvents,
		PlayerRoundEvents: matchState.PlayerRoundEvents,
		PlayerMatchEvents: matchState.PlayerMatchEvents,
	}
}

// detectMatchType determines the match type based on player ranks
func (dp *DemoParser) detectMatchType(parser demoinfocs.Parser) string {
	if parser == nil {
		dp.logger.Warn("Parser is nil, defaulting to 'other' match type")
		return types.MatchTypeOther
	}

	gameState := parser.GameState()
	if gameState == nil {
		dp.logger.Warn("Game state is nil, defaulting to 'other' match type")
		return types.MatchTypeOther
	}

	players := gameState.Participants().All()
	if len(players) == 0 {
		dp.logger.Warn("No players found, defaulting to 'other' match type")
		return types.MatchTypeOther
	}

	// Check if any player has a valid rank (not unranked)
	hasValidRank := false
	rankTypeCounts := make(map[int]int)

	for _, player := range players {
		if player != nil {
			rank := player.Rank()
			rankType := player.RankType()
			rankTypeCounts[rankType]++

			// A valid rank is one that's not 0 (unranked) and has a valid rank type
			if rank > 0 && rankType > 0 {
				hasValidRank = true
			}

			dp.logger.WithFields(logrus.Fields{
				"player_name": player.Name,
				"steam_id":    types.SteamIDToString(player.SteamID64),
				"rank":        rank,
				"rank_type":   rankType,
			}).Debug("Match type detection - player rank analysis")
		}
	}

	dp.logger.WithFields(logrus.Fields{
		"rank_type_counts": rankTypeCounts,
		"has_valid_rank":   hasValidRank,
		"total_players":    len(players),
	}).Debug("Match type detection analysis")

	// If any player has a valid rank, it's a Valve match
	if hasValidRank {
		dp.logger.Info("Valid ranks detected, match type: valve")
		return types.MatchTypeValve
	}

	// No valid ranks found, default to other
	dp.logger.Info("No valid ranks detected, match type: other")
	return types.MatchTypeOther
}
