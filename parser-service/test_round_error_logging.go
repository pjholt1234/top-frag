package main

import (
	"parser-service/internal/config"
	"parser-service/internal/parser"
	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
)

func main() {
	// Load configuration
	cfg, err := config.Load()
	if err != nil {
		panic(err)
	}

	// Setup logger with error logging
	logger := setupTestLogger(cfg)

	// Test error scenarios
	logger.Info("Testing round handler error scenarios...")

	// Test 1: Invalid round number (round 0)
	matchState1 := &types.MatchState{
		CurrentRound: 0,
		Players:      make(map[string]*types.Player),
	}
	processor1 := parser.NewEventProcessor(matchState1, logger)
	roundHandler1 := parser.NewRoundHandler(processor1, logger)

	err1 := roundHandler1.ProcessRoundEnd()
	if err1 != nil {
		logger.WithError(err1).Error("Round handler returned error for invalid round number")
	}

	// Test 2: No players in round
	matchState2 := &types.MatchState{
		CurrentRound: 1,
		Players:      make(map[string]*types.Player), // Empty players map
	}
	processor2 := parser.NewEventProcessor(matchState2, logger)
	roundHandler2 := parser.NewRoundHandler(processor2, logger)

	err2 := roundHandler2.ProcessRoundEnd()
	if err2 != nil {
		logger.WithError(err2).Error("Round handler returned error for no players in round")
	}

	// Test 3: Valid round should succeed
	matchState3 := &types.MatchState{
		CurrentRound: 1,
		Players: map[string]*types.Player{
			"player1": {},
			"player2": {},
		},
		PlayerRoundEvents: []types.PlayerRoundEvent{},
	}
	processor3 := parser.NewEventProcessor(matchState3, logger)
	roundHandler3 := parser.NewRoundHandler(processor3, logger)

	err3 := roundHandler3.ProcessRoundEnd()
	if err3 != nil {
		logger.WithError(err3).Error("Round handler returned error for valid round")
	} else {
		logger.Info("Valid round processed successfully")
	}

	logger.Info("Round error testing completed. Check errors.log for error entries.")
}

func setupTestLogger(cfg *config.Config) *logrus.Logger {
	logger := logrus.New()
	logger.SetLevel(logrus.DebugLevel)
	logger.SetFormatter(&logrus.TextFormatter{
		FullTimestamp: true,
	})

	// Setup error log file hook
	if cfg.Logging.ErrorFile != "" {
		errorHook, err := config.NewErrorLogHook(cfg.Logging.ErrorFile)
		if err != nil {
			logger.WithError(err).Warn("Failed to setup error log hook")
		} else {
			logger.AddHook(errorHook)
		}
	}

	return logger
}
