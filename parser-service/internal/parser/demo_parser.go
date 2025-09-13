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
	config *config.Config
	logger *logrus.Logger
}

func NewDemoParser(cfg *config.Config, logger *logrus.Logger) *DemoParser {
	return &DemoParser{
		config: cfg,
		logger: logger,
	}
}

func (dp *DemoParser) ParseDemo(ctx context.Context, demoPath string, progressCallback func(types.ProgressUpdate)) (*types.ParsedDemoData, error) {
	return dp.ParseDemoFromFile(ctx, demoPath, progressCallback)
}

// ParseDemoFromFile parses a demo file from a file path
func (dp *DemoParser) ParseDemoFromFile(ctx context.Context, demoPath string, progressCallback func(types.ProgressUpdate)) (*types.ParsedDemoData, error) {
	dp.logger.WithField("demo_path", demoPath).Info("Starting demo parsing")

	if err := dp.validateDemoFile(demoPath); err != nil {
		return nil, fmt.Errorf("demo file validation failed: %w", err)
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

	progressCallback(types.ProgressUpdate{
		Status:         types.StatusParsing,
		Progress:       15,
		CurrentStep:    "Parsing demo file",
		StepProgress:   0,
		TotalSteps:     18, // Will be updated when we know round count
		CurrentStepNum: 1,
		StartTime:      time.Now(),
		LastUpdateTime: time.Now(),
		Context:        map[string]interface{}{"step": "file_validation"},
		IsFinal:        false,
	})

	var mapName string
	var demoParser demoinfocs.Parser

	err := demoinfocs.ParseFile(demoPath, func(parser demoinfocs.Parser) error {
		demoParser = parser
		eventProcessor.SetDemoParser(parser)

		parser.RegisterNetMessageHandler(func(m *msg.CDemoFileHeader) {
			mapName = m.GetMapName()
			dp.logger.WithField("map_name", mapName).Info("Map name extracted from demo header")
		})

		dp.registerEventHandlers(parser, eventProcessor, progressCallback)

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
		return nil, fmt.Errorf("failed to parse demo: %w", err)
	}

	playbackTicks := 0
	if demoParser != nil {
		playbackTicks = demoParser.CurrentFrame()
		dp.logger.WithField("playback_ticks", playbackTicks).Info("Extracted playback ticks from demo parser")
	}

	progressCallback(types.ProgressUpdate{
		Status:         types.StatusProcessingEvents,
		Progress:       85,
		CurrentStep:    "Processing final data",
		StepProgress:   0,
		TotalSteps:     18, // Will be updated when we know round count
		CurrentStepNum: 11, // Final data assembly step
		StartTime:      time.Now(),
		LastUpdateTime: time.Now(),
		Context:        map[string]interface{}{"step": "final_data_assembly"},
		IsFinal:        false,
	})

	dp.postProcessGrenadeMovement(eventProcessor)
	dp.postProcessDamageAssists(eventProcessor)

	parsedData := dp.buildParsedData(matchState, mapName, playbackTicks, eventProcessor)

	dp.logger.WithField("total_events", len(matchState.GunfightEvents)+len(matchState.GrenadeEvents)+len(matchState.DamageEvents)).
		Info("Demo parsing completed")

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

func (dp *DemoParser) registerEventHandlers(parser demoinfocs.Parser, eventProcessor *EventProcessor, progressCallback func(types.ProgressUpdate)) {
	parser.RegisterEventHandler(func(e events.RoundStart) {
		eventProcessor.HandleRoundStart(e)
		progressCallback(types.ProgressUpdate{
			Status:         types.StatusProcessingEvents,
			Progress:       20 + (eventProcessor.matchState.CurrentRound * 2),
			CurrentStep:    fmt.Sprintf("Processing round %d", eventProcessor.matchState.CurrentRound),
			StepProgress:   0,
			TotalSteps:     18 + eventProcessor.matchState.TotalRounds, // Update with actual round count
			CurrentStepNum: 3,                                          // Round events processing step
			StartTime:      time.Now(),
			LastUpdateTime: time.Now(),
			Context: map[string]interface{}{
				"step":         "round_events_processing",
				"round":        eventProcessor.matchState.CurrentRound,
				"total_rounds": eventProcessor.matchState.TotalRounds,
			},
			IsFinal: false,
		})
	})

	parser.RegisterEventHandler(func(e events.RoundEnd) {
		eventProcessor.HandleRoundEnd(e)
	})

	parser.RegisterEventHandler(func(e events.Kill) {
		if err := eventProcessor.HandlePlayerKilled(e); err != nil {
			dp.logger.WithFields(logrus.Fields{
				"error": err,
				"tick":  eventProcessor.currentTick,
			}).Error("Failed to handle player killed event")
		}
	})

	parser.RegisterEventHandler(func(e events.PlayerHurt) {
		if err := eventProcessor.HandlePlayerHurt(e); err != nil {
			dp.logger.WithFields(logrus.Fields{
				"error": err,
				"tick":  eventProcessor.currentTick,
			}).Error("Failed to handle player hurt event")
		}
	})

	parser.RegisterEventHandler(func(e events.GrenadeProjectileThrow) {
		eventProcessor.HandleGrenadeProjectileThrow(e)
	})

	parser.RegisterEventHandler(func(e events.GrenadeProjectileDestroy) {
		eventProcessor.HandleGrenadeProjectileDestroy(e)
	})

	parser.RegisterEventHandler(func(e events.FlashExplode) {
		eventProcessor.HandleFlashExplode(e)
	})

	parser.RegisterEventHandler(func(e events.PlayerFlashed) {
		dp.logger.WithFields(logrus.Fields{
			"player": e.Player.Name,
			"tick":   eventProcessor.currentTick,
		}).Info("PlayerFlashed event received")
		eventProcessor.HandlePlayerFlashed(e)
	})

	parser.RegisterEventHandler(func(e events.SmokeStart) {
		eventProcessor.HandleSmokeStart(e)
	})

	parser.RegisterEventHandler(func(e events.WeaponFire) {
		eventProcessor.HandleWeaponFire(e)
	})

	parser.RegisterEventHandler(func(e events.BombPlanted) {
		eventProcessor.HandleBombPlanted(e)
	})

	parser.RegisterEventHandler(func(e events.BombDefused) {
		eventProcessor.HandleBombDefused(e)
	})

	parser.RegisterEventHandler(func(e events.BombExplode) {
		eventProcessor.HandleBombExplode(e)
	})

	parser.RegisterEventHandler(func(e events.PlayerConnect) {
		eventProcessor.HandlePlayerConnect(e)
	})

	parser.RegisterEventHandler(func(e events.PlayerDisconnected) {
		eventProcessor.HandlePlayerDisconnected(e)
	})

	parser.RegisterEventHandler(func(e events.PlayerTeamChange) {
		eventProcessor.HandlePlayerTeamChange(e)
	})
}

func (dp *DemoParser) buildParsedData(matchState *types.MatchState, mapName string, playbackTicks int, eventProcessor *EventProcessor) *types.ParsedDemoData {
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

	match := types.Match{
		Map:              mapName,
		WinningTeam:      winningTeam,
		WinningTeamScore: winningTeamScore,
		LosingTeamScore:  losingTeamScore,
		MatchType:        types.MatchTypeOther,
		StartTimestamp:   nil,
		EndTimestamp:     nil,
		TotalRounds:      totalRounds,
		PlaybackTicks:    playbackTicks,
	}

	now := time.Now()
	match.EndTimestamp = &now

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
		"gunfight_events":     len(matchState.GunfightEvents),
		"grenade_events":      len(matchState.GrenadeEvents),
		"damage_events":       len(matchState.DamageEvents),
		"round_events":        len(matchState.RoundEvents),
		"player_round_events": len(matchState.PlayerRoundEvents),
		"player_match_events": len(matchState.PlayerMatchEvents),
	}).Info("Match data built with event counts")

	eventProcessor.playerMatchHandler.aggregatePlayerMatchEvent()

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
