package parser

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"parser-service/internal/config"
	"parser-service/internal/database"
	"parser-service/internal/types"

	"github.com/google/uuid"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/msg"
	"github.com/sirupsen/logrus"
)

type DemoParser struct {
	config            *config.Config
	logger            *logrus.Logger
	progressManager   *ProgressManager
	gameModeDetector  *GameModeDetector
	db                *database.Database
	playerTickService *database.PlayerTickService
	matchID           string
}

func NewDemoParser(cfg *config.Config, logger *logrus.Logger) (*DemoParser, error) {
	// Initialize database connection
	db, err := database.NewDatabase(&cfg.Database, logger)
	if err != nil {
		return nil, fmt.Errorf("failed to initialize database: %w", err)
	}

	// Run migrations
	if err := db.AutoMigrate(); err != nil {
		return nil, fmt.Errorf("failed to run database migrations: %w", err)
	}

	// Initialize player tick service
	playerTickService := database.NewPlayerTickService(db.DB, logger)

	return &DemoParser{
		config:            cfg,
		logger:            logger,
		gameModeDetector:  NewGameModeDetector(logger),
		db:                db,
		playerTickService: playerTickService,
	}, nil
}

func (dp *DemoParser) ParseDemo(ctx context.Context, demoPath string, progressCallback func(types.ProgressUpdate)) (*types.ParsedDemoData, error) {
	return dp.ParseDemoFromFile(ctx, demoPath, progressCallback)
}

// ParseDemoFromFile parses a demo file from a file path
func (dp *DemoParser) ParseDemoFromFile(ctx context.Context, demoPath string, progressCallback func(types.ProgressUpdate)) (*types.ParsedDemoData, error) {
	// Generate unique match ID for this parsing session
	dp.matchID = uuid.New().String()

	// Initialize progress manager
	dp.progressManager = NewProgressManager(dp.logger, progressCallback, 100*time.Millisecond)

	// Pointer to eventProcessor for cleanup (will be set once created)
	var eventProcessor *EventProcessor

	// Enhanced panic recovery with cleanup
	defer func() {
		if r := recover(); r != nil {
			errorMsg := fmt.Sprintf("panic during demo parsing: %v", r)
			dp.progressManager.ReportError(errorMsg, "PARSING_PANIC")
			dp.logger.WithField("panic", r).Error("Panic occurred during demo parsing")

			// Cleanup match data on panic if configured
			dp.cleanupMatchData(ctx, eventProcessor)
		}
	}()

	if err := dp.validateDemoFile(demoPath); err != nil {
		parseError := types.NewParseError(types.ErrorTypeValidation, "demo file validation failed", err).
			WithContext("demo_path", demoPath)
		dp.progressManager.ReportParseError(parseError)

		// Cleanup match data on validation error if configured
		dp.cleanupMatchData(ctx, eventProcessor)
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

	eventProcessor = NewEventProcessor(matchState, dp.logger)

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
		eventProcessor.SetPlayerTickService(dp.playerTickService)
		eventProcessor.SetMatchID(dp.matchID)

		parser.RegisterNetMessageHandler(func(m *msg.CDemoFileHeader) {
			mapName = m.GetMapName()
		})

		dp.registerEventHandlers(parser, eventProcessor)

		parser.RegisterEventHandler(func(e events.FrameDone) {
			eventProcessor.UpdateCurrentTickAndPlayers(int64(parser.GameState().IngameTick()), parser.GameState())

			// Track player positions and aim for each tick
			dp.trackPlayerTickData(ctx, parser, eventProcessor)
		})

		gameState := parser.GameState()
		if gameState == nil {
			return nil
		}

		// Game state information available for debugging if needed

		return nil
	})

	if err != nil {
		parseError := types.NewParseError(types.ErrorTypeParsing, "failed to parse demo", err).
			WithContext("demo_path", demoPath)
		dp.progressManager.ReportParseError(parseError)

		// Cleanup match data on parsing error if configured
		dp.cleanupMatchData(ctx, eventProcessor)
		return nil, parseError
	}

	// Check if critical error occurred during parsing
	if dp.progressManager.HasError() {
		errorMsg, errorCode := dp.progressManager.GetError()
		parseError := types.NewParseError(types.ErrorTypeEventProcessing, errorMsg, nil).
			WithContext("demo_path", demoPath).
			WithContext("error_code", errorCode)

		// Cleanup match data on critical error if configured
		dp.cleanupMatchData(ctx, eventProcessor)
		return nil, parseError
	}

	playbackTicks := 0
	if demoParser != nil {
		playbackTicks = demoParser.CurrentFrame()
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

	{
		start := time.Now()
		dp.postProcessGrenadeMovement(eventProcessor)
		elapsed := time.Since(start)
		dp.logger.WithFields(logrus.Fields{
			"label":       "post_process_grenade_movement",
			"start_time":  start,
			"end_time":    start.Add(elapsed),
			"duration_ms": elapsed.Milliseconds(),
		}).Info("performance")
	}
	{
		start := time.Now()
		dp.postProcessDamageAssists(eventProcessor)
		elapsed := time.Since(start)
		dp.logger.WithFields(logrus.Fields{
			"label":       "post_process_damage_assists",
			"start_time":  start,
			"end_time":    start.Add(elapsed),
			"duration_ms": elapsed.Milliseconds(),
		}).Info("performance")
	}

	buildStart := time.Now()
	parsedData := dp.buildParsedData(matchState, mapName, playbackTicks, eventProcessor, demoParser)
	buildElapsed := time.Since(buildStart)
	dp.logger.WithFields(logrus.Fields{
		"label":       "match_aggregation",
		"start_time":  buildStart,
		"end_time":    buildStart.Add(buildElapsed),
		"duration_ms": buildElapsed.Milliseconds(),
	}).Info("performance")

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

	// Cleanup match data on successful completion if configured
	dp.cleanupMatchData(ctx, eventProcessor)

	return parsedData, nil
}

func (dp *DemoParser) postProcessGrenadeMovement(eventProcessor *EventProcessor) {
	// Starting grenade movement post-processing

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

	// Completed grenade movement post-processing
}

func (dp *DemoParser) postProcessDamageAssists(eventProcessor *EventProcessor) {
	// Starting damage assist post-processing

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

			// Updated damage assist
		}
	}

	// Completed damage assist post-processing
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

		// PlayerFlashed event received

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

	// Match data built with event counts

	return &types.ParsedDemoData{
		Match:             match,
		Players:           players,
		GunfightEvents:    matchState.GunfightEvents,
		GrenadeEvents:     matchState.GrenadeEvents,
		RoundEvents:       matchState.RoundEvents,
		DamageEvents:      matchState.DamageEvents,
		PlayerRoundEvents: matchState.PlayerRoundEvents,
		PlayerMatchEvents: matchState.PlayerMatchEvents,
		AimEvents:         eventProcessor.GetAimEvents(),
		AimWeaponEvents:   eventProcessor.GetAimWeaponEvents(),
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

			// Match type detection - player rank analysis
		}
	}

	dp.logger.WithFields(logrus.Fields{
		"rank_type_counts": rankTypeCounts,
		"has_valid_rank":   hasValidRank,
	})
	// Match type detection analysis

	// If any player has a valid rank, it's a Valve match
	if hasValidRank {
		return types.MatchTypeValve
	}

	// No valid ranks found, default to other
	return types.MatchTypeOther
}

// trackPlayerTickData tracks player positions and aim for each tick
func (dp *DemoParser) trackPlayerTickData(ctx context.Context, parser demoinfocs.Parser, eventProcessor *EventProcessor) {
	gameState := parser.GameState()
	if gameState == nil {
		return
	}

	currentTick := int64(gameState.IngameTick())
	participants := gameState.Participants().All()

	var tickData []*types.PlayerTickData

	for _, participant := range participants {
		if participant == nil || !participant.IsAlive() {
			continue
		}

		// Get player position
		position := participant.Position()

		// Get player view angles (aim direction)
		viewAngles := participant.ViewDirectionX()
		viewAnglesY := participant.ViewDirectionY()

		// Get player team assignment
		playerTeam := "A" // Default fallback
		if eventProcessor != nil {
			playerTeam = eventProcessor.getAssignedTeam(types.SteamIDToString(participant.SteamID64))
		}

		// Create player tick data
		playerTickData := &types.PlayerTickData{
			MatchID:   dp.matchID,
			Tick:      currentTick,
			PlayerID:  types.SteamIDToString(participant.SteamID64),
			Team:      playerTeam,
			PositionX: position.X,
			PositionY: position.Y,
			PositionZ: position.Z,
			AimX:      float64(viewAngles),
			AimY:      float64(viewAnglesY),
		}

		tickData = append(tickData, playerTickData)
	}

	// Save tick data in batch for performance
	if len(tickData) > 0 {
		if err := dp.playerTickService.SavePlayerTickDataBatch(ctx, tickData); err != nil {
			dp.logger.WithFields(logrus.Fields{
				"match_id":     dp.matchID,
				"tick":         currentTick,
				"player_count": len(tickData),
				"error":        err,
			}).Error("Failed to save player tick data")
		}
	}
}

// cleanupMatchData deletes match data if cleanup is enabled in configuration
func (dp *DemoParser) cleanupMatchData(ctx context.Context, eventProcessor *EventProcessor) {
	if !dp.config.Database.CleanupOnFinish {
		return
	}

	if dp.matchID == "" {
		dp.logger.Warn("No match ID available for cleanup")
		return
	}

	// Cleaning up match data

	// Clean up player tick data from database
	if err := dp.playerTickService.DeletePlayerTickDataByMatch(ctx, dp.matchID); err != nil {
		dp.logger.WithFields(logrus.Fields{
			"match_id": dp.matchID,
			"error":    err,
		}).Error("Failed to cleanup player tick data")
	} else {
		dp.logger.WithFields(logrus.Fields{
			"match_id": dp.matchID,
		}).Info("Successfully cleaned up player tick data")
	}

	// Clean up in-memory shooting data
	if eventProcessor != nil && eventProcessor.aimTrackingHandler != nil {
		shootingDataCount := len(eventProcessor.aimTrackingHandler.GetShootingData())
		eventProcessor.aimTrackingHandler.ClearShootingData()
		dp.logger.WithFields(logrus.Fields{
			"match_id":            dp.matchID,
			"shooting_data_count": shootingDataCount,
		}).Info("Successfully cleaned up player shooting data")
	}
}
