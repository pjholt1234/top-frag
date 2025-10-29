package utils

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"time"

	"parser-service/internal/config"

	"github.com/sirupsen/logrus"
)

// PerformanceLogger handles performance logging for each parser run
// Each run creates a new log file with the format: [timestamp]-[JobID].log
type PerformanceLogger struct {
	logger    *logrus.Logger
	enabled   bool
	config    *config.Config
	logFile   *os.File
	logDir    string
	jobID     string
	startTime time.Time
	fileName  string
	fileSize  int64
}

// PerformanceTimer tracks the duration of an operation
type PerformanceTimer struct {
	logger      *PerformanceLogger
	sectionName string
	startTime   time.Time
	metadata    map[string]interface{}
	ctx         context.Context
}

// PerformanceEntry represents a single performance log entry
type PerformanceEntry struct {
	SectionName string                 `json:"section_name"`
	StartTime   string                 `json:"start_time"`
	EndTime     string                 `json:"end_time"`
	DurationMs  float64                `json:"duration_ms"`
	Meta        map[string]interface{} `json:"meta,omitempty"`
}

// NewPerformanceLogger creates a base performance logger without a specific run
func NewPerformanceLogger(cfg *config.Config, logger *logrus.Logger) (*PerformanceLogger, error) {
	return &PerformanceLogger{
		logger:  logger,
		enabled: cfg.Logging.PerformanceLog,
		config:  cfg,
		logDir:  "logs/performance",
	}, nil
}

// InitializeRun starts a new performance logging run with a specific job ID
// This creates a new log file and writes the initial configuration
func (pl *PerformanceLogger) InitializeRun(jobID string, fileName string, fileSize int64) error {
	if !pl.enabled {
		return nil
	}

	pl.jobID = jobID
	pl.startTime = time.Now()
	pl.fileName = fileName
	pl.fileSize = fileSize

	// Create performance log directory if it doesn't exist
	if err := os.MkdirAll(pl.logDir, 0755); err != nil {
		pl.logger.WithError(err).Error("Failed to create performance log directory")
		return fmt.Errorf("failed to create performance log directory: %w", err)
	}

	// Create log file with format: [timestamp]-[JobID].log
	timestamp := pl.startTime.Format("20060102-150405")
	logFileName := fmt.Sprintf("%s-%s.log", timestamp, jobID)
	logFilePath := filepath.Join(pl.logDir, logFileName)

	file, err := os.Create(logFilePath)
	if err != nil {
		pl.logger.WithError(err).Error("Failed to create performance log file")
		return fmt.Errorf("failed to create performance log file: %w", err)
	}
	pl.logFile = file

	// Write initial header with configuration
	if err := pl.writeInitialLog(); err != nil {
		pl.logger.WithError(err).Error("Failed to write initial log")
		return err
	}

	return nil
}

// writeInitialLog writes the configuration and file details at the start
func (pl *PerformanceLogger) writeInitialLog() error {
	if pl.logFile == nil {
		return nil
	}

	// Write separator
	pl.writeLine("=" + repeatString("=", 79))
	pl.writeLine("PERFORMANCE LOG - PARSER RUN")
	pl.writeLine("=" + repeatString("=", 79))
	pl.writeLine("")

	// Write start time
	pl.writeLine(fmt.Sprintf("Start Time: %s", pl.startTime.Format(time.RFC3339)))
	pl.writeLine("")

	// Write configuration (non-secret entries)
	pl.writeLine("CONFIGURATION:")
	pl.writeLine(fmt.Sprintf("  Environment: %s", pl.config.Environment))
	pl.writeLine(fmt.Sprintf("  Server Port: %s", pl.config.Server.Port))
	pl.writeLine(fmt.Sprintf("  Server Read Timeout: %s", pl.config.Server.ReadTimeout))
	pl.writeLine(fmt.Sprintf("  Server Write Timeout: %s", pl.config.Server.WriteTimeout))
	pl.writeLine(fmt.Sprintf("  Server Idle Timeout: %s", pl.config.Server.IdleTimeout))
	pl.writeLine("")
	pl.writeLine(fmt.Sprintf("  Parser Max Concurrent Jobs: %d", pl.config.Parser.MaxConcurrentJobs))
	pl.writeLine(fmt.Sprintf("  Parser Progress Interval: %s", pl.config.Parser.ProgressInterval))
	pl.writeLine(fmt.Sprintf("  Parser Max Demo Size: %d bytes", pl.config.Parser.MaxDemoSize))
	pl.writeLine(fmt.Sprintf("  Parser Temp Dir: %s", pl.config.Parser.TempDir))
	pl.writeLine(fmt.Sprintf("  Parser Tick Sample Rate: %d", pl.config.Parser.TickSampleRate))
	pl.writeLine("")
	pl.writeLine(fmt.Sprintf("  Batch Gunfight Events Size: %d", pl.config.Batch.GunfightEventsSize))
	pl.writeLine(fmt.Sprintf("  Batch Grenade Events Size: %d", pl.config.Batch.GrenadeEventsSize))
	pl.writeLine(fmt.Sprintf("  Batch Damage Events Size: %d", pl.config.Batch.DamageEventsSize))
	pl.writeLine(fmt.Sprintf("  Batch Round Events Size: %d", pl.config.Batch.RoundEventsSize))
	pl.writeLine(fmt.Sprintf("  Batch Retry Attempts: %d", pl.config.Batch.RetryAttempts))
	pl.writeLine(fmt.Sprintf("  Batch Retry Delay: %s", pl.config.Batch.RetryDelay))
	pl.writeLine(fmt.Sprintf("  Batch HTTP Timeout: %s", pl.config.Batch.HTTPTimeout))
	pl.writeLine("")
	pl.writeLine(fmt.Sprintf("  Logging Level: %s", pl.config.Logging.Level))
	pl.writeLine(fmt.Sprintf("  Logging Format: %s", pl.config.Logging.Format))
	pl.writeLine(fmt.Sprintf("  Logging Performance Detail: %s", pl.config.Logging.PerformanceDetail))
	pl.writeLine("")
	pl.writeLine(fmt.Sprintf("  Database Host: %s", pl.config.Database.Host))
	pl.writeLine(fmt.Sprintf("  Database Port: %d", pl.config.Database.Port))
	pl.writeLine(fmt.Sprintf("  Database User: %s", pl.config.Database.User))
	pl.writeLine(fmt.Sprintf("  Database Name: %s", pl.config.Database.DBName))
	pl.writeLine(fmt.Sprintf("  Database Charset: %s", pl.config.Database.Charset))
	pl.writeLine(fmt.Sprintf("  Database Max Idle: %d", pl.config.Database.MaxIdle))
	pl.writeLine(fmt.Sprintf("  Database Max Open: %d", pl.config.Database.MaxOpen))
	pl.writeLine(fmt.Sprintf("  Database Cleanup On Finish: %t", pl.config.Database.CleanupOnFinish))
	pl.writeLine("")

	// Write file details
	pl.writeLine("FILE DETAILS:")
	pl.writeLine(fmt.Sprintf("  Job ID: %s", pl.jobID))
	pl.writeLine(fmt.Sprintf("  File Name: %s", pl.fileName))
	pl.writeLine(fmt.Sprintf("  File Size: %d bytes (%.2f MB)", pl.fileSize, float64(pl.fileSize)/1024/1024))
	pl.writeLine("")

	// Write performance entries header
	pl.writeLine("-" + repeatString("-", 79))
	pl.writeLine("PERFORMANCE METRICS")
	pl.writeLine("-" + repeatString("-", 79))
	pl.writeLine("")

	return nil
}

// StartTimer starts a new performance timer for a specific section
func (pl *PerformanceLogger) StartTimer(sectionName string) *PerformanceTimer {
	return &PerformanceTimer{
		logger:      pl,
		sectionName: sectionName,
		startTime:   time.Now(),
		metadata:    make(map[string]interface{}),
		ctx:         context.Background(),
	}
}

// StartTimerWithContext starts a new performance timer with context
func (pl *PerformanceLogger) StartTimerWithContext(ctx context.Context, sectionName string) *PerformanceTimer {
	return &PerformanceTimer{
		logger:      pl,
		sectionName: sectionName,
		startTime:   time.Now(),
		metadata:    make(map[string]interface{}),
		ctx:         ctx,
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
	pt.logger.logPerformance(pt.sectionName, pt.startTime, elapsed, pt.metadata)
	return elapsed
}

// StopWithError stops the timer and logs with error information
func (pt *PerformanceTimer) StopWithError(err error) time.Duration {
	elapsed := time.Since(pt.startTime)
	pt.metadata["error"] = err.Error()
	pt.metadata["has_error"] = true
	pt.logger.logPerformance(pt.sectionName, pt.startTime, elapsed, pt.metadata)
	return elapsed
}

// logPerformance logs the performance metrics in the new format with decimal precision
func (pl *PerformanceLogger) logPerformance(sectionName string, startTime time.Time, duration time.Duration, metadata map[string]interface{}) {
	if !pl.enabled || pl.logFile == nil {
		return
	}

	endTime := startTime.Add(duration)

	// Convert duration to milliseconds with decimal precision
	durationMs := float64(duration.Nanoseconds()) / 1e6

	entry := PerformanceEntry{
		SectionName: sectionName,
		StartTime:   startTime.Format(time.RFC3339),
		EndTime:     endTime.Format(time.RFC3339),
		DurationMs:  durationMs,
		Meta:        metadata,
	}

	// Convert to JSON
	jsonData, err := json.Marshal(entry)
	if err != nil {
		pl.logger.WithError(err).Error("Failed to marshal performance entry")
		return
	}

	// Write to log file
	pl.writeLine(string(jsonData))
}

// FinalizeRun writes the final summary and closes the log file
func (pl *PerformanceLogger) FinalizeRun() {
	if !pl.enabled || pl.logFile == nil {
		return
	}

	endTime := time.Now()
	totalDuration := endTime.Sub(pl.startTime)

	pl.writeLine("")
	pl.writeLine("=" + repeatString("=", 79))
	pl.writeLine("PARSE FINISHED")
	pl.writeLine("=" + repeatString("=", 79))
	pl.writeLine(fmt.Sprintf("End Time: %s", endTime.Format(time.RFC3339)))
	pl.writeLine(fmt.Sprintf("Total Duration: %.2f ms (%.2f seconds)", float64(totalDuration.Nanoseconds())/1e6, totalDuration.Seconds()))
	pl.writeLine("=" + repeatString("=", 79))
}

// writeLine writes a line to the log file
func (pl *PerformanceLogger) writeLine(line string) {
	if pl.logFile != nil {
		fmt.Fprintln(pl.logFile, line)
	}
}

// Close closes the performance logger and its file
func (pl *PerformanceLogger) Close() error {
	if pl.logFile != nil {
		if err := pl.logFile.Close(); err != nil {
			return err
		}
		pl.logFile = nil
	}
	return nil
}

// repeatString repeats a string n times
func repeatString(s string, n int) string {
	result := ""
	for i := 0; i < n; i++ {
		result += s
	}
	return result
}
