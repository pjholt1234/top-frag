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
	Message   string
	Context   map[string]interface{}
	Timestamp time.Time
	Cause     error
}

func (e *ParseError) Error() string {
	return fmt.Sprintf("[%s] %s", e.Type.String(), e.Message)
}

func NewParseError(errorType ErrorType, message string, cause error) *ParseError {
	return &ParseError{
		Type:      errorType,
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
