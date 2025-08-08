package parser

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
	"parser-service/internal/config"
	"parser-service/internal/types"
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

// Parses the demo file and sends the data to the batch sender
// Sends progress updates via the progress callback function
// Handles errors and sends error messages to the callback URLs

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
		Players:        make(map[string]*types.Player),
		RoundEvents:    make([]types.RoundEvent, 0),
		GunfightEvents: make([]types.GunfightEvent, 0),
		GrenadeEvents:  make([]types.GrenadeEvent, 0),
		DamageEvents:   make([]types.DamageEvent, 0),
	}

	eventProcessor := NewEventProcessor(matchState, dp.logger)

	progressCallback(types.ProgressUpdate{
		Status:      types.StatusProcessing,
		Progress:    10,
		CurrentStep: "Parsing demo file",
	})

	err := demoinfocs.ParseFile(demoPath, func(parser demoinfocs.Parser) error {
		// Register event handlers for the demo parser
		dp.registerEventHandlers(parser, eventProcessor, progressCallback)
		return nil
	})

	if err != nil {
		return nil, fmt.Errorf("failed to parse demo: %w", err)
	}

	progressCallback(types.ProgressUpdate{
		Status:      types.StatusProcessing,
		Progress:    90,
		CurrentStep: "Processing final data",
	})

	parsedData := dp.buildParsedData(matchState)

	dp.logger.WithField("total_events", len(matchState.GunfightEvents)+len(matchState.GrenadeEvents)+len(matchState.DamageEvents)).
		Info("Demo parsing completed")

	return parsedData, nil
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

// Registers event handlers for the demo parser
func (dp *DemoParser) registerEventHandlers(parser demoinfocs.Parser, eventProcessor *EventProcessor, progressCallback func(types.ProgressUpdate)) {
	parser.RegisterEventHandler(func(e events.RoundStart) {
		eventProcessor.HandleRoundStart(e)
		progressCallback(types.ProgressUpdate{
			Status:      types.StatusProcessing,
			Progress:    30 + (eventProcessor.matchState.CurrentRound*2),
			CurrentStep: fmt.Sprintf("Processing round %d", eventProcessor.matchState.CurrentRound),
		})
	})

	parser.RegisterEventHandler(func(e events.RoundEnd) {
		eventProcessor.HandleRoundEnd(e)
	})

	parser.RegisterEventHandler(func(e events.Kill) {
		eventProcessor.HandlePlayerKilled(e)
	})

	parser.RegisterEventHandler(func(e events.PlayerHurt) {
		eventProcessor.HandlePlayerHurt(e)
	})

	parser.RegisterEventHandler(func(e events.GrenadeProjectileDestroy) {
		eventProcessor.HandleGrenadeProjectileDestroy(e)
	})

	parser.RegisterEventHandler(func(e events.FlashExplode) {
		eventProcessor.HandleFlashExplode(e)
	})

	parser.RegisterEventHandler(func(e events.HeExplode) {
		eventProcessor.HandleHeExplode(e)
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
}

func (dp *DemoParser) buildParsedData(matchState *types.MatchState) *types.ParsedDemoData {
	players := make([]types.Player, 0, len(matchState.Players))
	for _, player := range matchState.Players {
		players = append(players, *player)
	}

	match := types.Match{
		Map:              "de_dust2",
		WinningTeamScore: 0,
		LosingTeamScore:  0,
		MatchType:        types.MatchTypeOther,
		StartTimestamp:   nil,
		EndTimestamp:     nil,
		TotalRounds:      matchState.TotalRounds,
	}

	ctWins := 0
	tWins := 0
	for _, roundEvent := range matchState.RoundEvents {
		if roundEvent.EventType == "end" && roundEvent.Winner != nil {
			if *roundEvent.Winner == "CT" {
				ctWins++
			} else if *roundEvent.Winner == "T" {
				tWins++
			}
		}
	}

	if ctWins > tWins {
		match.WinningTeamScore = ctWins
		match.LosingTeamScore = tWins
	} else {
		match.WinningTeamScore = tWins
		match.LosingTeamScore = ctWins
	}

	now := time.Now()
	match.EndTimestamp = &now

	return &types.ParsedDemoData{
		Match:           match,
		Players:         players,
		GunfightEvents:  matchState.GunfightEvents,
		GrenadeEvents:   matchState.GrenadeEvents,
		RoundEvents:     matchState.RoundEvents,
		DamageEvents:    matchState.DamageEvents,
	}
} 