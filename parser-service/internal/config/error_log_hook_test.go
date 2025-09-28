package config

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
)

func TestNewErrorLogHook(t *testing.T) {
	// Create a temporary directory for test files
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-error.log")

	// Test successful creation
	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	assert.NotNil(t, hook)
	assert.NotNil(t, hook.file)
	assert.NotNil(t, hook.writer)

	// Clean up
	err = hook.Close()
	assert.NoError(t, err)
}

func TestNewErrorLogHook_InvalidPath(t *testing.T) {
	// Test with invalid path (directory that doesn't exist)
	invalidPath := "/nonexistent/directory/error.log"

	hook, err := NewErrorLogHook(invalidPath)
	assert.Error(t, err)
	assert.Nil(t, hook)
	assert.Contains(t, err.Error(), "failed to open error log file")
}

func TestErrorLogHook_Levels(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-error.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	levels := hook.Levels()
	expectedLevels := []logrus.Level{
		logrus.ErrorLevel,
		logrus.FatalLevel,
		logrus.PanicLevel,
	}

	assert.Equal(t, expectedLevels, levels)
}

func TestErrorLogHook_Fire_ErrorLevel(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-error.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	// Create a log entry
	entry := &logrus.Entry{
		Level:   logrus.ErrorLevel,
		Time:    time.Now(),
		Message: "Test error message",
		Data:    logrus.Fields{"key": "value"},
	}

	// Fire the hook
	err = hook.Fire(entry)
	assert.NoError(t, err)

	// Read the log file and verify content
	content, err := os.ReadFile(logFile)
	assert.NoError(t, err)

	logContent := string(content)
	assert.Contains(t, logContent, "error: Test error message")
	assert.Contains(t, logContent, "key=value")
	assert.Contains(t, logContent, "Fields:")
}

func TestErrorLogHook_Fire_FatalLevel(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-fatal.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	// Create a log entry
	entry := &logrus.Entry{
		Level:   logrus.FatalLevel,
		Time:    time.Now(),
		Message: "Fatal error occurred",
		Data:    logrus.Fields{"component": "database"},
	}

	// Fire the hook
	err = hook.Fire(entry)
	assert.NoError(t, err)

	// Read the log file and verify content
	content, err := os.ReadFile(logFile)
	assert.NoError(t, err)

	logContent := string(content)
	assert.Contains(t, logContent, "fatal: Fatal error occurred")
	assert.Contains(t, logContent, "component=database")
}

func TestErrorLogHook_Fire_PanicLevel(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-panic.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	// Create a log entry
	entry := &logrus.Entry{
		Level:   logrus.PanicLevel,
		Time:    time.Now(),
		Message: "Panic occurred",
		Data:    logrus.Fields{"stack": "trace"},
	}

	// Fire the hook
	err = hook.Fire(entry)
	assert.NoError(t, err)

	// Read the log file and verify content
	content, err := os.ReadFile(logFile)
	assert.NoError(t, err)

	logContent := string(content)
	assert.Contains(t, logContent, "panic: Panic occurred")
	assert.Contains(t, logContent, "stack=trace")
}

func TestErrorLogHook_Fire_WithErrorField(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-error-field.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	// Create a log entry with error field
	entry := &logrus.Entry{
		Level:   logrus.ErrorLevel,
		Time:    time.Now(),
		Message: "Database connection failed",
		Data: logrus.Fields{
			"error": "connection timeout",
			"host":  "localhost:3306",
		},
	}

	// Fire the hook
	err = hook.Fire(entry)
	assert.NoError(t, err)

	// Read the log file and verify content
	content, err := os.ReadFile(logFile)
	assert.NoError(t, err)

	logContent := string(content)
	assert.Contains(t, logContent, "error: Database connection failed")
	assert.Contains(t, logContent, "host=localhost:3306")
	assert.Contains(t, logContent, "Error: connection timeout")
}

func TestErrorLogHook_Fire_EmptyData(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-empty-data.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	// Create a log entry with no additional data
	entry := &logrus.Entry{
		Level:   logrus.ErrorLevel,
		Time:    time.Now(),
		Message: "Simple error message",
		Data:    logrus.Fields{},
	}

	// Fire the hook
	err = hook.Fire(entry)
	assert.NoError(t, err)

	// Read the log file and verify content
	content, err := os.ReadFile(logFile)
	assert.NoError(t, err)

	logContent := string(content)
	assert.Contains(t, logContent, "error: Simple error message")
	assert.NotContains(t, logContent, "Fields:")
	assert.NotContains(t, logContent, "Error:")
}

func TestErrorLogHook_Fire_ConcurrentAccess(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-concurrent.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	// Test concurrent access
	done := make(chan bool, 10)
	for i := 0; i < 10; i++ {
		go func(id int) {
			entry := &logrus.Entry{
				Level:   logrus.ErrorLevel,
				Time:    time.Now(),
				Message: "Concurrent error message",
				Data:    logrus.Fields{"goroutine": id},
			}

			err := hook.Fire(entry)
			assert.NoError(t, err)
			done <- true
		}(i)
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}

	// Read the log file and verify content
	content, err := os.ReadFile(logFile)
	assert.NoError(t, err)

	logContent := string(content)
	lines := strings.Split(strings.TrimSpace(logContent), "\n")
	assert.Len(t, lines, 10) // Should have 10 log entries

	// Each line should contain the error message
	for _, line := range lines {
		assert.Contains(t, line, "error: Concurrent error message")
	}
}

func TestErrorLogHook_Fire_WriteError(t *testing.T) {
	// Create a hook with a closed file to simulate write error
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-write-error.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)

	// Close the file to simulate write error
	err = hook.Close()
	assert.NoError(t, err)

	// Try to fire the hook with closed file
	entry := &logrus.Entry{
		Level:   logrus.ErrorLevel,
		Time:    time.Now(),
		Message: "Test message",
		Data:    logrus.Fields{},
	}

	err = hook.Fire(entry)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "failed to write to error log file")
}

func TestErrorLogHook_Close(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-close.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)

	// Close the hook
	err = hook.Close()
	assert.NoError(t, err)

	// Try to close again (should not error, but may return an error for already closed file)
	// This is expected behavior
	err = hook.Close()
	// We don't assert on this since it may or may not error depending on implementation
}

func TestErrorLogHook_Close_NilFile(t *testing.T) {
	// Create a hook with nil file
	hook := &ErrorLogHook{
		file:   nil,
		writer: nil,
	}

	// Close should not error with nil file
	err := hook.Close()
	assert.NoError(t, err)
}

func TestErrorLogHook_TimestampFormat(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-timestamp.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	// Create a log entry with specific time
	fixedTime := time.Date(2024, 1, 15, 14, 30, 45, 123456789, time.UTC)
	entry := &logrus.Entry{
		Level:   logrus.ErrorLevel,
		Time:    fixedTime,
		Message: "Timestamp test",
		Data:    logrus.Fields{},
	}

	// Fire the hook
	err = hook.Fire(entry)
	assert.NoError(t, err)

	// Read the log file and verify timestamp format
	content, err := os.ReadFile(logFile)
	assert.NoError(t, err)

	logContent := string(content)
	// Should contain timestamp in format "2006-01-02 15:04:05.000"
	assert.Contains(t, logContent, "[2024-01-15 14:30:45.123]")
}

func TestErrorLogHook_MultipleEntries(t *testing.T) {
	tempDir := t.TempDir()
	logFile := filepath.Join(tempDir, "test-multiple.log")

	hook, err := NewErrorLogHook(logFile)
	assert.NoError(t, err)
	defer hook.Close()

	// Fire multiple entries
	entries := []*logrus.Entry{
		{
			Level:   logrus.ErrorLevel,
			Time:    time.Now(),
			Message: "First error",
			Data:    logrus.Fields{"id": 1},
		},
		{
			Level:   logrus.FatalLevel,
			Time:    time.Now(),
			Message: "Second fatal",
			Data:    logrus.Fields{"id": 2},
		},
		{
			Level:   logrus.PanicLevel,
			Time:    time.Now(),
			Message: "Third panic",
			Data:    logrus.Fields{"id": 3},
		},
	}

	for _, entry := range entries {
		err = hook.Fire(entry)
		assert.NoError(t, err)
	}

	// Read the log file and verify all entries
	content, err := os.ReadFile(logFile)
	assert.NoError(t, err)

	logContent := string(content)
	lines := strings.Split(strings.TrimSpace(logContent), "\n")
	assert.Len(t, lines, 3)

	assert.Contains(t, lines[0], "error: First error")
	assert.Contains(t, lines[0], "id=1")
	assert.Contains(t, lines[1], "fatal: Second fatal")
	assert.Contains(t, lines[1], "id=2")
	assert.Contains(t, lines[2], "panic: Third panic")
	assert.Contains(t, lines[2], "id=3")
}
