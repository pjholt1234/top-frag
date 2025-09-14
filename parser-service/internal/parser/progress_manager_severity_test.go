package parser

import (
	"testing"
	"time"

	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
)

func TestProgressManager_ReportParseError_Critical(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Create a critical error
	criticalError := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityCritical,
		"critical system failure",
		nil,
	)

	// Report the error
	progressManager.ReportParseError(criticalError)

	// Check that parsing should stop
	if !progressManager.HasError() {
		t.Error("Expected HasError() to return true for critical error")
	}

	errorMsg, errorCode := progressManager.GetError()
	if errorMsg != "critical system failure" {
		t.Errorf("Expected error message 'critical system failure', got '%s'", errorMsg)
	}

	if errorCode != "VALIDATION_FAILED" {
		t.Errorf("Expected error code 'VALIDATION_FAILED', got '%s'", errorCode)
	}
}

func TestProgressManager_ReportParseError_Error(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Create an error level error
	errorLevelError := types.NewParseErrorWithSeverity(
		types.ErrorTypeEventProcessing,
		types.ErrorSeverityError,
		"processing failure",
		nil,
	)

	// Report the error
	progressManager.ReportParseError(errorLevelError)

	// Check that parsing should stop
	if !progressManager.HasError() {
		t.Error("Expected HasError() to return true for error level error")
	}

	errorMsg, errorCode := progressManager.GetError()
	if errorMsg != "processing failure" {
		t.Errorf("Expected error message 'processing failure', got '%s'", errorMsg)
	}

	if errorCode != "EVENT_PROCESSING_FAILED" {
		t.Errorf("Expected error code 'EVENT_PROCESSING_FAILED', got '%s'", errorCode)
	}
}

func TestProgressManager_ReportParseError_Warning(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Create a warning error
	warningError := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityWarning,
		"data quality issue",
		nil,
	)

	// Report the error
	progressManager.ReportParseError(warningError)

	// Check that parsing should continue
	if progressManager.HasError() {
		t.Error("Expected HasError() to return false for warning error")
	}

	errorMsg, errorCode := progressManager.GetError()
	if errorMsg != "" {
		t.Errorf("Expected empty error message for warning, got '%s'", errorMsg)
	}

	if errorCode != "" {
		t.Errorf("Expected empty error code for warning, got '%s'", errorCode)
	}
}

func TestProgressManager_ReportParseError_Info(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Create an info error
	infoError := types.NewParseErrorWithSeverity(
		types.ErrorTypeNetwork,
		types.ErrorSeverityInfo,
		"communication issue",
		nil,
	)

	// Report the error
	progressManager.ReportParseError(infoError)

	// Check that parsing should continue
	if progressManager.HasError() {
		t.Error("Expected HasError() to return false for info error")
	}

	errorMsg, errorCode := progressManager.GetError()
	if errorMsg != "" {
		t.Errorf("Expected empty error message for info, got '%s'", errorMsg)
	}

	if errorCode != "" {
		t.Errorf("Expected empty error code for info, got '%s'", errorCode)
	}
}

func TestProgressManager_UpdateProgress_AfterCriticalError(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Report a critical error first
	criticalError := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityCritical,
		"critical error",
		nil,
	)
	progressManager.ReportParseError(criticalError)

	// Try to update progress after critical error
	progressUpdate := types.ProgressUpdate{
		Status:      types.StatusParsing,
		Progress:    50,
		CurrentStep: "Processing",
		IsFinal:     false,
	}

	// This should not cause any issues, but progress updates should be ignored
	progressManager.UpdateProgress(progressUpdate)

	// Verify error state is still maintained
	if !progressManager.HasError() {
		t.Error("Expected HasError() to still return true after progress update")
	}
}

func TestProgressManager_UpdateProgress_AfterWarningError(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Report a warning error first
	warningError := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityWarning,
		"warning",
		nil,
	)
	progressManager.ReportParseError(warningError)

	// Try to update progress after warning error
	progressUpdate := types.ProgressUpdate{
		Status:      types.StatusParsing,
		Progress:    50,
		CurrentStep: "Processing",
		IsFinal:     false,
	}

	// This should work fine since warning doesn't stop parsing
	progressManager.UpdateProgress(progressUpdate)

	// Verify error state is not set
	if progressManager.HasError() {
		t.Error("Expected HasError() to return false after warning error")
	}
}

func TestProgressManager_ReportCompletion_AfterCriticalError(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Report a critical error first
	criticalError := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityCritical,
		"critical error",
		nil,
	)
	progressManager.ReportParseError(criticalError)

	// Try to report completion after critical error
	completionUpdate := types.ProgressUpdate{
		Status:      types.StatusCompleted,
		Progress:    100,
		CurrentStep: "Completed",
		IsFinal:     true,
	}

	// This should not cause any issues, but completion should be ignored
	progressManager.ReportCompletion(completionUpdate)

	// Verify error state is still maintained
	if !progressManager.HasError() {
		t.Error("Expected HasError() to still return true after completion report")
	}
}

func TestProgressManager_ReportCompletion_AfterWarningError(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Report a warning error first
	warningError := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityWarning,
		"warning",
		nil,
	)
	progressManager.ReportParseError(warningError)

	// Try to report completion after warning error
	completionUpdate := types.ProgressUpdate{
		Status:      types.StatusCompleted,
		Progress:    100,
		CurrentStep: "Completed",
		IsFinal:     true,
	}

	// This should work fine since warning doesn't stop parsing
	progressManager.ReportCompletion(completionUpdate)

	// Verify error state is not set
	if progressManager.HasError() {
		t.Error("Expected HasError() to return false after warning error")
	}
}

func TestProgressManager_ReportParseError_WithContext(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Create an error with context
	errorWithContext := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityWarning,
		"validation failed",
		nil,
	).WithContext("player_id", "12345").
		WithContext("round", 5)

	// Report the error
	progressManager.ReportParseError(errorWithContext)

	// Check that parsing continues (warning level)
	if progressManager.HasError() {
		t.Error("Expected HasError() to return false for warning error with context")
	}
}

func TestProgressManager_MultipleErrors(t *testing.T) {
	logger := logrus.New()
	progressManager := NewProgressManager(logger, nil, 100*time.Millisecond)

	// Report multiple non-critical errors
	warningError := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityWarning,
		"warning 1",
		nil,
	)
	infoError := types.NewParseErrorWithSeverity(
		types.ErrorTypeNetwork,
		types.ErrorSeverityInfo,
		"info 1",
		nil,
	)

	progressManager.ReportParseError(warningError)
	progressManager.ReportParseError(infoError)

	// Check that parsing continues
	if progressManager.HasError() {
		t.Error("Expected HasError() to return false after multiple non-critical errors")
	}

	// Now report a critical error
	criticalError := types.NewParseErrorWithSeverity(
		types.ErrorTypeValidation,
		types.ErrorSeverityCritical,
		"critical error",
		nil,
	)
	progressManager.ReportParseError(criticalError)

	// Check that parsing stops
	if !progressManager.HasError() {
		t.Error("Expected HasError() to return true after critical error")
	}
}
