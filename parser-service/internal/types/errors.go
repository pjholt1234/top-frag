package types

import (
	"fmt"
	"runtime/debug"
	"time"
)

type ErrorType int

const (
	ErrorTypeValidation ErrorType = iota
	ErrorTypeDemoCorrupted
	ErrorTypeParsing
	ErrorTypeEventProcessing
	ErrorTypeNetwork
	ErrorTypeTimeout
	ErrorTypeResourceExhausted
	ErrorTypeProgressUpdate
	ErrorTypeUnknown
)

// String returns the string representation of the error type
func (et ErrorType) String() string {
	switch et {
	case ErrorTypeValidation:
		return "VALIDATION_FAILED"
	case ErrorTypeDemoCorrupted:
		return "DEMO_CORRUPTED"
	case ErrorTypeParsing:
		return "PARSING_FAILED"
	case ErrorTypeEventProcessing:
		return "EVENT_PROCESSING_FAILED"
	case ErrorTypeNetwork:
		return "NETWORK_ERROR"
	case ErrorTypeTimeout:
		return "TIMEOUT"
	case ErrorTypeResourceExhausted:
		return "RESOURCE_EXHAUSTED"
	case ErrorTypeProgressUpdate:
		return "PROGRESS_UPDATE_FAILED"
	case ErrorTypeUnknown:
		return "UNKNOWN_ERROR"
	default:
		return "UNKNOWN_ERROR"
	}
}

type ParseError struct {
	Type      ErrorType
	Severity  ErrorSeverity
	Message   string
	Context   map[string]interface{}
	Timestamp time.Time
	Cause     error
}

func (e *ParseError) Error() string {
	return fmt.Sprintf("[%s:%s] %s", e.Severity.String(), e.Type.String(), e.Message)
}

func NewParseError(errorType ErrorType, message string, cause error) *ParseError {
	return &ParseError{
		Type:      errorType,
		Severity:  getDefaultSeverity(errorType),
		Message:   message,
		Context:   make(map[string]interface{}),
		Timestamp: time.Now(),
		Cause:     cause,
	}
}

func (e *ParseError) WithContext(key string, value interface{}) *ParseError {
	if e.Context == nil {
		e.Context = make(map[string]interface{})
	}
	e.Context[key] = value
	return e
}

func (e *ParseError) WithStack() *ParseError {
	e.Context["stack_trace"] = string(debug.Stack())
	return e
}

func (e *ParseError) IsValidationError() bool {
	return e.Type == ErrorTypeValidation
}

func (e *ParseError) IsNetworkError() bool {
	return e.Type == ErrorTypeNetwork
}

func (e *ParseError) IsTimeoutError() bool {
	return e.Type == ErrorTypeTimeout
}

// ErrorSeverity defines the severity level of an error
type ErrorSeverity int

const (
	ErrorSeverityCritical ErrorSeverity = iota // Stop parsing immediately - System failures
	ErrorSeverityError                         // Stop parsing immediately - Processing failures
	ErrorSeverityWarning                       // Log but continue parsing - Data quality issues
	ErrorSeverityInfo                          // Log but continue parsing - Missing data/communication issues
)

// String returns the string representation of the error severity
func (es ErrorSeverity) String() string {
	switch es {
	case ErrorSeverityCritical:
		return "CRITICAL"
	case ErrorSeverityError:
		return "ERROR"
	case ErrorSeverityWarning:
		return "WARNING"
	case ErrorSeverityInfo:
		return "INFO"
	default:
		return "UNKNOWN"
	}
}

// ShouldStopParsing returns true if the error severity should stop parsing
func (es ErrorSeverity) ShouldStopParsing() bool {
	return es == ErrorSeverityCritical || es == ErrorSeverityError
}

// NewParseErrorWithSeverity creates a new ParseError with a specific severity
func NewParseErrorWithSeverity(errorType ErrorType, severity ErrorSeverity, message string, cause error) *ParseError {
	return &ParseError{
		Type:      errorType,
		Severity:  severity,
		Message:   message,
		Context:   make(map[string]interface{}),
		Timestamp: time.Now(),
		Cause:     cause,
	}
}

func (e *ParseError) WithSeverity(severity ErrorSeverity) *ParseError {
	newError := *e
	newError.Severity = severity
	return &newError
}

// IsCritical returns true if the error is critical and should stop parsing
func (e *ParseError) IsCritical() bool {
	return e.Severity == ErrorSeverityCritical
}

// IsError returns true if the error should stop parsing
func (e *ParseError) IsError() bool {
	return e.Severity == ErrorSeverityError
}

// IsWarning returns true if the error is a warning
func (e *ParseError) IsWarning() bool {
	return e.Severity == ErrorSeverityWarning
}

// IsInfo returns true if the error is informational
func (e *ParseError) IsInfo() bool {
	return e.Severity == ErrorSeverityInfo
}

// ShouldStopParsing returns true if the error should stop parsing
func (e *ParseError) ShouldStopParsing() bool {
	return e.Severity.ShouldStopParsing()
}

// getDefaultSeverity returns the default severity for each error type
func getDefaultSeverity(errorType ErrorType) ErrorSeverity {
	switch errorType {
	case ErrorTypeValidation:
		return ErrorSeverityCritical // System validation failures
	case ErrorTypeDemoCorrupted:
		return ErrorSeverityCritical // Demo file corruption
	case ErrorTypeParsing:
		return ErrorSeverityCritical // Core parsing failures
	case ErrorTypeEventProcessing:
		return ErrorSeverityError // Processing failures
	case ErrorTypeNetwork:
		return ErrorSeverityInfo // Communication issues
	case ErrorTypeTimeout:
		return ErrorSeverityError // Timeout issues
	case ErrorTypeResourceExhausted:
		return ErrorSeverityCritical // Resource issues
	case ErrorTypeProgressUpdate:
		return ErrorSeverityInfo // Progress update failures
	case ErrorTypeUnknown:
		return ErrorSeverityError // Unknown errors
	default:
		return ErrorSeverityError
	}
}
