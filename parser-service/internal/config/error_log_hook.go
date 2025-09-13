package config

import (
	"fmt"
	"os"
	"sync"

	"github.com/sirupsen/logrus"
)

// ErrorLogHook is a logrus hook that writes error and fatal level logs to a separate file
type ErrorLogHook struct {
	file   *os.File
	writer *os.File
	mutex  sync.Mutex
}

// NewErrorLogHook creates a new ErrorLogHook that writes to the specified file
func NewErrorLogHook(filename string) (*ErrorLogHook, error) {
	file, err := os.OpenFile(filename, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err != nil {
		return nil, fmt.Errorf("failed to open error log file: %w", err)
	}

	return &ErrorLogHook{
		file:   file,
		writer: file,
	}, nil
}

// Levels returns the log levels that this hook should fire for
func (hook *ErrorLogHook) Levels() []logrus.Level {
	return []logrus.Level{
		logrus.ErrorLevel,
		logrus.FatalLevel,
		logrus.PanicLevel,
	}
}

// Fire is called when a log entry is made at one of the levels returned by Levels()
func (hook *ErrorLogHook) Fire(entry *logrus.Entry) error {
	hook.mutex.Lock()
	defer hook.mutex.Unlock()

	// Format the log entry with timestamp
	timestamp := entry.Time.Format("2006-01-02 15:04:05.000")
	level := entry.Level.String()
	message := entry.Message

	// Create a formatted log line with timestamp
	logLine := fmt.Sprintf("[%s] %s: %s", timestamp, level, message)

	// Add fields if they exist
	if len(entry.Data) > 0 {
		logLine += " | Fields:"
		for key, value := range entry.Data {
			logLine += fmt.Sprintf(" %s=%v", key, value)
		}
	}

	// Add error if it exists (check for error field in Data)
	if err, exists := entry.Data["error"]; exists {
		logLine += fmt.Sprintf(" | Error: %v", err)
	}

	logLine += "\n"

	// Write to the error log file
	_, err := hook.writer.WriteString(logLine)
	if err != nil {
		return fmt.Errorf("failed to write to error log file: %w", err)
	}

	// Flush to ensure the data is written to disk
	return hook.writer.Sync()
}

// Close closes the error log file
func (hook *ErrorLogHook) Close() error {
	hook.mutex.Lock()
	defer hook.mutex.Unlock()

	if hook.file != nil {
		return hook.file.Close()
	}
	return nil
}
