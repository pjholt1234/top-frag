package main

import (
	"parser-service/internal/config"

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

	// Test different log levels
	logger.Info("This is an info message - should not appear in errors.log")
	logger.Warn("This is a warning message - should not appear in errors.log")
	logger.Error("This is an error message - should appear in errors.log")
	logger.WithField("user_id", "12345").Error("This is an error with fields - should appear in errors.log")
	logger.WithError(err).Error("This is an error with error field - should appear in errors.log")
	logger.Fatal("This is a fatal message - should appear in errors.log")
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
