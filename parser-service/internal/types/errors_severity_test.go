package types

import (
	"testing"
)

func TestErrorSeverity_String(t *testing.T) {
	tests := []struct {
		name     string
		severity ErrorSeverity
		expected string
	}{
		{
			name:     "Critical severity",
			severity: ErrorSeverityCritical,
			expected: "CRITICAL",
		},
		{
			name:     "Error severity",
			severity: ErrorSeverityError,
			expected: "ERROR",
		},
		{
			name:     "Warning severity",
			severity: ErrorSeverityWarning,
			expected: "WARNING",
		},
		{
			name:     "Info severity",
			severity: ErrorSeverityInfo,
			expected: "INFO",
		},
		{
			name:     "Unknown severity",
			severity: ErrorSeverity(999),
			expected: "UNKNOWN",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := tt.severity.String()
			if result != tt.expected {
				t.Errorf("ErrorSeverity.String() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestErrorSeverity_ShouldStopParsing(t *testing.T) {
	tests := []struct {
		name     string
		severity ErrorSeverity
		expected bool
	}{
		{
			name:     "Critical should stop parsing",
			severity: ErrorSeverityCritical,
			expected: true,
		},
		{
			name:     "Error should stop parsing",
			severity: ErrorSeverityError,
			expected: true,
		},
		{
			name:     "Warning should not stop parsing",
			severity: ErrorSeverityWarning,
			expected: false,
		},
		{
			name:     "Info should not stop parsing",
			severity: ErrorSeverityInfo,
			expected: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := tt.severity.ShouldStopParsing()
			if result != tt.expected {
				t.Errorf("ErrorSeverity.ShouldStopParsing() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestParseError_WithSeverity(t *testing.T) {
	originalError := NewParseError(ErrorTypeValidation, "test error", nil)

	// Test setting severity
	errorWithSeverity := originalError.WithSeverity(ErrorSeverityWarning)

	if errorWithSeverity.Severity != ErrorSeverityWarning {
		t.Errorf("Expected severity WARNING, got %v", errorWithSeverity.Severity)
	}

	// Test that original error is not modified
	if originalError.Severity == ErrorSeverityWarning {
		t.Error("Original error should not be modified")
	}
}

func TestParseError_IsCritical(t *testing.T) {
	tests := []struct {
		name     string
		severity ErrorSeverity
		expected bool
	}{
		{
			name:     "Critical is critical",
			severity: ErrorSeverityCritical,
			expected: true,
		},
		{
			name:     "Error is not critical",
			severity: ErrorSeverityError,
			expected: false,
		},
		{
			name:     "Warning is not critical",
			severity: ErrorSeverityWarning,
			expected: false,
		},
		{
			name:     "Info is not critical",
			severity: ErrorSeverityInfo,
			expected: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			parseError := NewParseErrorWithSeverity(ErrorTypeValidation, tt.severity, "test error", nil)
			result := parseError.IsCritical()
			if result != tt.expected {
				t.Errorf("ParseError.IsCritical() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestParseError_IsError(t *testing.T) {
	tests := []struct {
		name     string
		severity ErrorSeverity
		expected bool
	}{
		{
			name:     "Critical is not error",
			severity: ErrorSeverityCritical,
			expected: false,
		},
		{
			name:     "Error is error",
			severity: ErrorSeverityError,
			expected: true,
		},
		{
			name:     "Warning is not error",
			severity: ErrorSeverityWarning,
			expected: false,
		},
		{
			name:     "Info is not error",
			severity: ErrorSeverityInfo,
			expected: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			parseError := NewParseErrorWithSeverity(ErrorTypeValidation, tt.severity, "test error", nil)
			result := parseError.IsError()
			if result != tt.expected {
				t.Errorf("ParseError.IsError() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestParseError_IsWarning(t *testing.T) {
	tests := []struct {
		name     string
		severity ErrorSeverity
		expected bool
	}{
		{
			name:     "Critical is not warning",
			severity: ErrorSeverityCritical,
			expected: false,
		},
		{
			name:     "Error is not warning",
			severity: ErrorSeverityError,
			expected: false,
		},
		{
			name:     "Warning is warning",
			severity: ErrorSeverityWarning,
			expected: true,
		},
		{
			name:     "Info is not warning",
			severity: ErrorSeverityInfo,
			expected: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			parseError := NewParseErrorWithSeverity(ErrorTypeValidation, tt.severity, "test error", nil)
			result := parseError.IsWarning()
			if result != tt.expected {
				t.Errorf("ParseError.IsWarning() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestParseError_IsInfo(t *testing.T) {
	tests := []struct {
		name     string
		severity ErrorSeverity
		expected bool
	}{
		{
			name:     "Critical is not info",
			severity: ErrorSeverityCritical,
			expected: false,
		},
		{
			name:     "Error is not info",
			severity: ErrorSeverityError,
			expected: false,
		},
		{
			name:     "Warning is not info",
			severity: ErrorSeverityWarning,
			expected: false,
		},
		{
			name:     "Info is info",
			severity: ErrorSeverityInfo,
			expected: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			parseError := NewParseErrorWithSeverity(ErrorTypeValidation, tt.severity, "test error", nil)
			result := parseError.IsInfo()
			if result != tt.expected {
				t.Errorf("ParseError.IsInfo() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestParseError_ShouldStopParsing(t *testing.T) {
	tests := []struct {
		name     string
		severity ErrorSeverity
		expected bool
	}{
		{
			name:     "Critical should stop parsing",
			severity: ErrorSeverityCritical,
			expected: true,
		},
		{
			name:     "Error should stop parsing",
			severity: ErrorSeverityError,
			expected: true,
		},
		{
			name:     "Warning should not stop parsing",
			severity: ErrorSeverityWarning,
			expected: false,
		},
		{
			name:     "Info should not stop parsing",
			severity: ErrorSeverityInfo,
			expected: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			parseError := NewParseErrorWithSeverity(ErrorTypeValidation, tt.severity, "test error", nil)
			result := parseError.ShouldStopParsing()
			if result != tt.expected {
				t.Errorf("ParseError.ShouldStopParsing() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestParseError_Error(t *testing.T) {
	tests := []struct {
		name      string
		errorType ErrorType
		severity  ErrorSeverity
		message   string
		expected  string
	}{
		{
			name:      "Critical validation error",
			errorType: ErrorTypeValidation,
			severity:  ErrorSeverityCritical,
			message:   "test error",
			expected:  "[CRITICAL:VALIDATION_FAILED] test error",
		},
		{
			name:      "Warning event processing error",
			errorType: ErrorTypeEventProcessing,
			severity:  ErrorSeverityWarning,
			message:   "processing failed",
			expected:  "[WARNING:EVENT_PROCESSING_FAILED] processing failed",
		},
		{
			name:      "Info network error",
			errorType: ErrorTypeNetwork,
			severity:  ErrorSeverityInfo,
			message:   "connection failed",
			expected:  "[INFO:NETWORK_ERROR] connection failed",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			parseError := NewParseErrorWithSeverity(tt.errorType, tt.severity, tt.message, nil)
			result := parseError.Error()
			if result != tt.expected {
				t.Errorf("ParseError.Error() = %v, want %v", result, tt.expected)
			}
		})
	}
}

func TestNewParseErrorWithSeverity(t *testing.T) {
	errorType := ErrorTypeValidation
	severity := ErrorSeverityWarning
	message := "test error"
	cause := error(nil)

	parseError := NewParseErrorWithSeverity(errorType, severity, message, cause)

	if parseError.Type != errorType {
		t.Errorf("Expected error type %v, got %v", errorType, parseError.Type)
	}

	if parseError.Severity != severity {
		t.Errorf("Expected severity %v, got %v", severity, parseError.Severity)
	}

	if parseError.Message != message {
		t.Errorf("Expected message %v, got %v", message, parseError.Message)
	}

	if parseError.Cause != cause {
		t.Errorf("Expected cause %v, got %v", cause, parseError.Cause)
	}

	if parseError.Context == nil {
		t.Error("Expected context to be initialized")
	}

	if parseError.Timestamp.IsZero() {
		t.Error("Expected timestamp to be set")
	}
}

func TestGetDefaultSeverity(t *testing.T) {
	tests := []struct {
		name      string
		errorType ErrorType
		expected  ErrorSeverity
	}{
		{
			name:      "Validation should be critical",
			errorType: ErrorTypeValidation,
			expected:  ErrorSeverityCritical,
		},
		{
			name:      "Demo corrupted should be critical",
			errorType: ErrorTypeDemoCorrupted,
			expected:  ErrorSeverityCritical,
		},
		{
			name:      "Parsing should be critical",
			errorType: ErrorTypeParsing,
			expected:  ErrorSeverityCritical,
		},
		{
			name:      "Event processing should be error",
			errorType: ErrorTypeEventProcessing,
			expected:  ErrorSeverityError,
		},
		{
			name:      "Network should be info",
			errorType: ErrorTypeNetwork,
			expected:  ErrorSeverityInfo,
		},
		{
			name:      "Timeout should be error",
			errorType: ErrorTypeTimeout,
			expected:  ErrorSeverityError,
		},
		{
			name:      "Resource exhausted should be critical",
			errorType: ErrorTypeResourceExhausted,
			expected:  ErrorSeverityCritical,
		},
		{
			name:      "Progress update should be info",
			errorType: ErrorTypeProgressUpdate,
			expected:  ErrorSeverityInfo,
		},
		{
			name:      "Unknown should be error",
			errorType: ErrorTypeUnknown,
			expected:  ErrorSeverityError,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := getDefaultSeverity(tt.errorType)
			if result != tt.expected {
				t.Errorf("getDefaultSeverity(%v) = %v, want %v", tt.errorType, result, tt.expected)
			}
		})
	}
}
