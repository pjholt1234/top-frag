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

	// Create a damage handler and test error scenarios
	matchState := &types.MatchState{
		CurrentRound: 1,
		DamageEvents: []types.DamageEvent{},
		Players:      make(map[string]*types.Player),
	}
	processor := parser.NewEventProcessor(matchState, logger)
	damageHandler := parser.NewDamageHandler(processor, logger)

	// Test error scenarios
	logger.Info("Testing damage handler error scenarios...")

	// Test 1: Nil attacker
	event1 := events.PlayerHurt{
		Attacker:     nil,
		Player:       nil,
		HealthDamage: 25,
		ArmorDamage:  10,
		Weapon:       nil,
	}

	err1 := damageHandler.HandlePlayerHurt(event1)
	if err1 != nil {
		logger.WithError(err1).Error("Damage handler returned error for nil attacker")
	}

	// Test 2: Negative health damage
	event2 := events.PlayerHurt{
		Attacker:     nil,
		Player:       nil,
		HealthDamage: -5,
		ArmorDamage:  10,
		Weapon:       nil,
	}

	err2 := damageHandler.HandlePlayerHurt(event2)
	if err2 != nil {
		logger.WithError(err2).Error("Damage handler returned error for negative health damage")
	}

	logger.Info("Error testing completed. Check errors.log for error entries.")
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
