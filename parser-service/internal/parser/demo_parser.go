package parser

import (
	"context"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/msg"
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
		Status:      types.StatusParsing,
		Progress:    15,
		CurrentStep: "Parsing demo file",
	})

	// Variable to store map name
	var mapName string

	err := demoinfocs.ParseFile(demoPath, func(parser demoinfocs.Parser) error {
		// Register handler for demo file header to get map name
		parser.RegisterNetMessageHandler(func(m *msg.CDemoFileHeader) {
			mapName = m.GetMapName()
			dp.logger.WithField("map_name", mapName).Info("Map name extracted from demo header")
		})

		// Register event handlers for the demo parser
		dp.registerEventHandlers(parser, eventProcessor, progressCallback)
		
		// Get final game state after parsing
		gameState := parser.GameState()
		if gameState != nil {
			totalRoundsPlayed := gameState.TotalRoundsPlayed()
			dp.logger.WithFields(logrus.Fields{
				"game_state_total_rounds": totalRoundsPlayed,
				"current_round":           eventProcessor.matchState.CurrentRound,
				"round_events_count":      len(eventProcessor.matchState.RoundEvents),
			}).Info("Final game state information")
		}
		
		return nil
	})

	if err != nil {
		return nil, fmt.Errorf("failed to parse demo: %w", err)
	}

	progressCallback(types.ProgressUpdate{
		Status:      types.StatusProcessingEvents,
		Progress:    85,
		CurrentStep: "Processing final data",
	})

	parsedData := dp.buildParsedData(matchState, mapName)

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
			Status:      types.StatusProcessingEvents,
			Progress:    20 + (eventProcessor.matchState.CurrentRound*2),
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

	// Add player tracking events
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

func (dp *DemoParser) buildParsedData(matchState *types.MatchState, mapName string) *types.ParsedDemoData {
	players := make([]types.Player, 0, len(matchState.Players))
	for _, player := range matchState.Players {
		players = append(players, *player)
	}

	// Use extracted map name, fallback to de_dust2 if not available
	if mapName == "" {
		mapName = "de_dust2"
		dp.logger.Warn("Map name not found in demo header, using default: de_dust2")
	}

	// Count round end events to get actual total rounds
	totalRounds := 0
	ctWins := 0
	tWins := 0
	
	// Track team scores properly accounting for CS2's overtime system
	// Regular game: First to 13 wins
	// Overtime: 3 rounds per half, first to 16, 19, 22, etc.
	
	// Track wins by period
	regularGameCTWins := 0  // Rounds 1-24 (max)
	regularGameTWins := 0   // Rounds 1-24 (max)
	overtimeCTWins := 0     // Rounds 25+
	overtimeTWins := 0      // Rounds 25+
	
	// Track each round individually for team score calculation
	roundWinners := make(map[int]string)
	
	for _, roundEvent := range matchState.RoundEvents {
		if roundEvent.EventType == "end" {
			totalRounds++
			winner := "Unknown"
			if roundEvent.Winner != nil {
				winner = *roundEvent.Winner
				roundWinners[roundEvent.RoundNumber] = winner
				
				if winner == "CT" {
					ctWins++
					if roundEvent.RoundNumber <= 24 {
						regularGameCTWins++
					} else {
						overtimeCTWins++
					}
				} else if winner == "T" {
					tWins++
					if roundEvent.RoundNumber <= 24 {
						regularGameTWins++
					} else {
						overtimeTWins++
					}
				}
			}
		}
	}

	// Calculate actual team scores by analyzing the pattern of wins
	// In CS2, teams switch sides at halftime (round 12)
	// We need to determine which team won based on the pattern
	
	// Analyze first half (rounds 1-12) vs second half (rounds 13-21)
	firstHalfCTWins := 0
	firstHalfTWins := 0
	secondHalfCTWins := 0
	secondHalfTWins := 0
	
	for roundNum := 1; roundNum <= 21; roundNum++ {
		winner, exists := roundWinners[roundNum]
		if exists {
			if roundNum <= 12 {
				// First half
				if winner == "CT" {
					firstHalfCTWins++
				} else if winner == "T" {
					firstHalfTWins++
				}
			} else {
				// Second half
				if winner == "CT" {
					secondHalfCTWins++
				} else if winner == "T" {
					secondHalfTWins++
				}
			}
		}
	}
	
	// Determine which team is which based on the pattern
	// If a team dominates first half as CT, they likely continue winning in second half as T
	team1Score := 0
	team2Score := 0
	
	if firstHalfCTWins > firstHalfTWins {
		// CT dominated first half, so the team that was CT in first half is likely the stronger team
		// In second half, they would be T
		team1Score = firstHalfCTWins + secondHalfTWins
		team2Score = firstHalfTWins + secondHalfCTWins
	} else {
		// T dominated first half, so the team that was T in first half is likely the stronger team
		// In second half, they would be CT
		team1Score = firstHalfTWins + secondHalfCTWins
		team2Score = firstHalfCTWins + secondHalfTWins
	}
	
	// Ensure scores don't exceed total rounds
	if team1Score + team2Score > totalRounds {
		// Fallback to simple CT vs T counting
		team1Score = ctWins
		team2Score = tWins
		dp.logger.Warn("Team scores exceeded total rounds, falling back to CT vs T counting")
	}

	match := types.Match{
		Map:              mapName,
		WinningTeamScore: 0,
		LosingTeamScore:  0,
		MatchType:        types.MatchTypeOther,
		StartTimestamp:   nil,
		EndTimestamp:     nil,
		TotalRounds:      totalRounds,
	}
	
	// Set the match scores
	if team1Score > team2Score {
		match.WinningTeamScore = team1Score
		match.LosingTeamScore = team2Score
	} else {
		match.WinningTeamScore = team2Score
		match.LosingTeamScore = team1Score
	}

	now := time.Now()
	match.EndTimestamp = &now

	dp.logger.WithFields(logrus.Fields{
		"map_name":           mapName,
		"total_rounds":       match.TotalRounds,
		"winning_team_score": match.WinningTeamScore,
		"losing_team_score":  match.LosingTeamScore,
		"has_overtime":       overtimeCTWins + overtimeTWins > 0,
	}).Info("Match data built")

	return &types.ParsedDemoData{
		Match:           match,
		Players:         players,
		GunfightEvents:  matchState.GunfightEvents,
		GrenadeEvents:   matchState.GrenadeEvents,
		RoundEvents:     matchState.RoundEvents,
		DamageEvents:    matchState.DamageEvents,
	}
} 