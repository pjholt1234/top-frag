package utils

import (
	"context"
	"fmt"
	"io"
	"os"
	"time"

	"parser-service/internal/config"

	"github.com/sirupsen/logrus"
)

// PerformanceLevel defines the detail level for performance logging
type PerformanceLevel int

const (
	PerformanceLevelBasic PerformanceLevel = iota
	PerformanceLevelDetailed
	PerformanceLevelVerbose
)

// PerformanceLogger handles performance logging with configurable detail levels
type PerformanceLogger struct {
	logger        *logrus.Logger
	enabled       bool
	detailLevel   PerformanceLevel
	performanceIO io.Writer
}

// PerformanceTimer tracks the duration of an operation
type PerformanceTimer struct {
	logger    *PerformanceLogger
	operation string
	startTime time.Time
	metadata  map[string]interface{}
	ctx       context.Context
}

// NewPerformanceLogger creates a new performance logger
func NewPerformanceLogger(cfg *config.Config, logger *logrus.Logger) (*PerformanceLogger, error) {
	perfLogger := &PerformanceLogger{
		logger:      logger,
		enabled:     cfg.Logging.PerformanceLog,
		detailLevel: parsePerformanceLevel(cfg.Logging.PerformanceDetail),
	}

	// Setup performance log file if enabled
	if cfg.Logging.PerformanceLog && cfg.Logging.PerformanceFile != "" {
		file, err := os.OpenFile(cfg.Logging.PerformanceFile, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
		if err != nil {
			logger.WithError(err).Warn("Failed to open performance log file, performance logs will go to main log")
			perfLogger.performanceIO = nil
		} else {
			perfLogger.performanceIO = file
		}
	}

	return perfLogger, nil
}

// parsePerformanceLevel converts string to PerformanceLevel
func parsePerformanceLevel(level string) PerformanceLevel {
	switch level {
	case "basic":
		return PerformanceLevelBasic
	case "detailed":
		return PerformanceLevelDetailed
	case "verbose":
		return PerformanceLevelVerbose
	default:
		return PerformanceLevelDetailed
	}
}

// StartTimer starts a new performance timer
func (pl *PerformanceLogger) StartTimer(operation string) *PerformanceTimer {
	return &PerformanceTimer{
		logger:    pl,
		operation: operation,
		startTime: time.Now(),
		metadata:  make(map[string]interface{}),
		ctx:       context.Background(),
	}
}

// StartTimerWithContext starts a new performance timer with context
func (pl *PerformanceLogger) StartTimerWithContext(ctx context.Context, operation string) *PerformanceTimer {
	return &PerformanceTimer{
		logger:    pl,
		operation: operation,
		startTime: time.Now(),
		metadata:  make(map[string]interface{}),
		ctx:       ctx,
	}
}

// WithMetadata adds metadata to the timer
func (pt *PerformanceTimer) WithMetadata(key string, value interface{}) *PerformanceTimer {
	pt.metadata[key] = value
	return pt
}

// WithMetadataMap adds multiple metadata entries
func (pt *PerformanceTimer) WithMetadataMap(metadata map[string]interface{}) *PerformanceTimer {
	for k, v := range metadata {
		pt.metadata[k] = v
	}
	return pt
}

// Stop stops the timer and logs the performance
func (pt *PerformanceTimer) Stop() time.Duration {
	elapsed := time.Since(pt.startTime)
	pt.logger.logPerformance(pt.operation, pt.startTime, elapsed, pt.metadata)
	return elapsed
}

// StopWithError stops the timer and logs with error information
func (pt *PerformanceTimer) StopWithError(err error) time.Duration {
	elapsed := time.Since(pt.startTime)
	pt.metadata["error"] = err.Error()
	pt.metadata["has_error"] = true
	pt.logger.logPerformance(pt.operation, pt.startTime, elapsed, pt.metadata)
	return elapsed
}

// logPerformance logs the performance metrics
func (pl *PerformanceLogger) logPerformance(operation string, startTime time.Time, duration time.Duration, metadata map[string]interface{}) {
	if !pl.enabled {
		return
	}

	endTime := startTime.Add(duration)

	fields := logrus.Fields{
		"type":        "performance",
		"operation":   operation,
		"start_time":  startTime.Format(time.RFC3339Nano),
		"end_time":    endTime.Format(time.RFC3339Nano),
		"duration_ms": duration.Milliseconds(),
		"duration_ns": duration.Nanoseconds(),
	}

	// Add metadata based on detail level
	if pl.detailLevel >= PerformanceLevelDetailed {
		for k, v := range metadata {
			fields[k] = v
		}
	} else if pl.detailLevel == PerformanceLevelBasic {
		// Only include essential metadata in basic mode
		if jobID, ok := metadata["job_id"]; ok {
			fields["job_id"] = jobID
		}
		if hasError, ok := metadata["has_error"]; ok && hasError == true {
			fields["has_error"] = hasError
			if errorMsg, ok := metadata["error"]; ok {
				fields["error"] = errorMsg
			}
		}
	}

	// Log to performance file if available, otherwise to main logger
	if pl.performanceIO != nil {
		// Write as JSON to performance file
		entry := pl.logger.WithFields(fields)
		jsonData, err := entry.String()
		if err == nil {
			fmt.Fprintf(pl.performanceIO, "%s\n", jsonData)
		}
	}

	// Also log to main logger at debug level for detailed/verbose modes
	if pl.detailLevel >= PerformanceLevelDetailed {
		pl.logger.WithFields(fields).Debug("Performance metric")
	}
}

// LogOperation logs a simple operation without timing
func (pl *PerformanceLogger) LogOperation(operation string, metadata map[string]interface{}) {
	if !pl.enabled {
		return
	}

	fields := logrus.Fields{
		"type":      "performance",
		"operation": operation,
		"timestamp": time.Now().Format(time.RFC3339Nano),
	}

	for k, v := range metadata {
		fields[k] = v
	}

	if pl.performanceIO != nil {
		entry := pl.logger.WithFields(fields)
		jsonData, err := entry.String()
		if err == nil {
			fmt.Fprintf(pl.performanceIO, "%s\n", jsonData)
		}
	}

	if pl.detailLevel >= PerformanceLevelVerbose {
		pl.logger.WithFields(fields).Debug("Performance operation")
	}
}

// Close closes the performance logger
func (pl *PerformanceLogger) Close() error {
	if closer, ok := pl.performanceIO.(io.Closer); ok {
		return closer.Close()
	}
	return nil
}
