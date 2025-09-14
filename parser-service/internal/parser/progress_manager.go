package parser

import (
	"sync"
	"time"

	"github.com/sirupsen/logrus"

	"parser-service/internal/types"
)

type ProgressManager struct {
	logger                *logrus.Logger
	progressCallback      func(types.ProgressUpdate)
	mu                    sync.RWMutex
	lastUpdate            time.Time
	updateInterval        time.Duration
	criticalErrorOccurred bool // Only critical/error level errors stop parsing
	errorMessage          string
	errorCode             string
}

func NewProgressManager(logger *logrus.Logger, progressCallback func(types.ProgressUpdate), updateInterval time.Duration) *ProgressManager {
	if updateInterval == 0 {
		updateInterval = 100 * time.Millisecond
	}

	return &ProgressManager{
		logger:           logger,
		progressCallback: progressCallback,
		updateInterval:   updateInterval,
		lastUpdate:       time.Now(),
	}
}

func (pm *ProgressManager) UpdateProgress(update types.ProgressUpdate) {
	pm.mu.Lock()
	defer pm.mu.Unlock()

	if pm.criticalErrorOccurred {
		return
	}

	now := time.Now()
	if now.Sub(pm.lastUpdate) < pm.updateInterval && !update.IsFinal {
		return
	}

	pm.lastUpdate = now
	update.LastUpdateTime = now

	if pm.progressCallback != nil {
		pm.progressCallback(update)
	}
}

// ReportError reports a critical error that stops parsing (CRITICAL/ERROR severity)
func (pm *ProgressManager) ReportError(errorMessage, errorCode string) {
	pm.mu.Lock()
	defer pm.mu.Unlock()

	pm.criticalErrorOccurred = true
	pm.errorMessage = errorMessage
	pm.errorCode = errorCode

	errorUpdate := types.ProgressUpdate{
		Status:       types.StatusFailed,
		Progress:     0,
		CurrentStep:  "Error occurred",
		ErrorMessage: &errorMessage,
		ErrorCode:    &errorCode,
		StartTime:    time.Now(),
		IsFinal:      true,
		Context: map[string]interface{}{
			"critical_error_occurred": true,
		},
	}

	if pm.progressCallback != nil {
		pm.progressCallback(errorUpdate)
	}

	pm.logger.WithFields(logrus.Fields{
		"error_message": errorMessage,
		"error_code":    errorCode,
	}).Error("Critical error reported - stopping parsing")
}

// ReportParseError reports an error with severity handling
func (pm *ProgressManager) ReportParseError(parseError *types.ParseError) {
	if parseError.ShouldStopParsing() {
		pm.ReportError(parseError.Message, parseError.Type.String())
	} else {
		// Log warning/info errors but continue parsing
		logLevel := logrus.WarnLevel
		if parseError.IsInfo() {
			logLevel = logrus.InfoLevel
		}

		fields := logrus.Fields{
			"error_type":     parseError.Type.String(),
			"error_severity": parseError.Severity.String(),
			"error_message":  parseError.Message,
		}

		// Add context fields if available
		for key, value := range parseError.Context {
			fields[key] = value
		}

		pm.logger.WithFields(fields).Log(logLevel, "Non-critical error occurred - continuing parsing")
	}
}

func (pm *ProgressManager) HasError() bool {
	pm.mu.RLock()
	defer pm.mu.RUnlock()
	return pm.criticalErrorOccurred
}

func (pm *ProgressManager) GetError() (string, string) {
	pm.mu.RLock()
	defer pm.mu.RUnlock()
	return pm.errorMessage, pm.errorCode
}

func (pm *ProgressManager) ReportCompletion(update types.ProgressUpdate) {
	pm.mu.Lock()
	defer pm.mu.Unlock()

	if pm.criticalErrorOccurred {
		return
	}

	update.Status = types.StatusCompleted
	update.Progress = 100
	update.IsFinal = true
	update.LastUpdateTime = time.Now()

	if pm.progressCallback != nil {
		pm.progressCallback(update)
	}
}
