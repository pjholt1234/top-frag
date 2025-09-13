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

	// Create a grenade handler and test error scenarios
	matchState := &types.MatchState{
		CurrentRound:  1,
		GrenadeEvents: []types.GrenadeEvent{},
		Players:       make(map[string]*types.Player),
	}
	processor := parser.NewEventProcessor(matchState, logger)
	grenadeHandler := parser.NewGrenadeHandler(processor, logger)

	// Test error scenarios
	logger.Info("Testing grenade handler error scenarios...")

	// Test 1: Nil projectile in HandleGrenadeProjectileThrow
	event1 := events.GrenadeProjectileThrow{
		Projectile: nil,
	}

	err1 := grenadeHandler.HandleGrenadeProjectileThrow(event1)
	if err1 != nil {
		logger.WithError(err1).Error("Grenade handler returned error for nil projectile in throw")
	}

	// Test 2: Nil projectile in HandleGrenadeProjectileDestroy
	event2 := events.GrenadeProjectileDestroy{
		Projectile: nil,
	}

	err2 := grenadeHandler.HandleGrenadeProjectileDestroy(event2)
	if err2 != nil {
		logger.WithError(err2).Error("Grenade handler returned error for nil projectile in destroy")
	}

	// Test 3: Nil player in HandlePlayerFlashed
	event3 := events.PlayerFlashed{
		Player: nil,
	}

	err3 := grenadeHandler.HandlePlayerFlashed(event3)
	if err3 != nil {
		logger.WithError(err3).Error("Grenade handler returned error for nil player in flashed")
	}

	// Test 4: Zero entity ID in HandleFlashExplode
	event4 := events.FlashExplode{
		GrenadeEvent: events.GrenadeEvent{
			GrenadeEntityID: 0,
		},
	}

	err4 := grenadeHandler.HandleFlashExplode(event4)
	if err4 != nil {
		logger.WithError(err4).Error("Grenade handler returned error for zero entity ID in flash explode")
	}

	logger.Info("Grenade error testing completed. Check errors.log for error entries.")
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
