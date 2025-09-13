package main

import (
	"parser-service/internal/config"
	"parser-service/internal/parser"
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
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

	// Create a gunfight handler and test error scenarios
	matchState := &types.MatchState{
		CurrentRound:   1,
		GunfightEvents: []types.GunfightEvent{},
		Players:        make(map[string]*types.Player),
	}
	processor := parser.NewEventProcessor(matchState, logger)
	gunfightHandler := parser.NewGunfightHandler(processor, logger)

	// Test error scenarios
	logger.Info("Testing gunfight handler error scenarios...")

	// Test 1: Nil killer
	event1 := events.Kill{
		Killer:            nil,
		Victim:            nil,
		IsHeadshot:        false,
		PenetratedObjects: 0,
	}

	err1 := gunfightHandler.HandlePlayerKilled(event1)
	if err1 != nil {
		logger.WithError(err1).Error("Gunfight handler returned error for nil killer")
	}

	// Test 2: Negative penetrated objects
	event2 := events.Kill{
		Killer:            nil,
		Victim:            nil,
		IsHeadshot:        false,
		PenetratedObjects: -5,
	}

	err2 := gunfightHandler.HandlePlayerKilled(event2)
	if err2 != nil {
		logger.WithError(err2).Error("Gunfight handler returned error for negative penetrated objects")
	}

	logger.Info("Gunfight error testing completed. Check errors.log for error entries.")
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
