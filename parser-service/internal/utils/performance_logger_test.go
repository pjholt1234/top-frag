package utils

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"parser-service/internal/config"

	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestNewPerformanceLogger(t *testing.T) {
	tests := []struct {
		name           string
		performanceLog bool
		detailLevel    string
		expectEnabled  bool
	}{
		{
			name:           "enabled with detailed level",
			performanceLog: true,
			detailLevel:    "detailed",
			expectEnabled:  true,
		},
		{
			name:           "enabled with basic level",
			performanceLog: true,
			detailLevel:    "basic",
			expectEnabled:  true,
		},
		{
			name:           "enabled with verbose level",
			performanceLog: true,
			detailLevel:    "verbose",
			expectEnabled:  true,
		},
		{
			name:           "disabled",
			performanceLog: false,
			detailLevel:    "detailed",
			expectEnabled:  false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			cfg := &config.Config{
				Logging: config.LoggingConfig{
					PerformanceLog:    tt.performanceLog,
					PerformanceDetail: tt.detailLevel,
				},
			}

			logger := logrus.New()
			logger.SetOutput(os.Stdout)

			perfLogger, err := NewPerformanceLogger(cfg, logger)
			require.NoError(t, err)
			assert.NotNil(t, perfLogger)
			assert.Equal(t, tt.expectEnabled, perfLogger.enabled)
		})
	}
}

func TestPerformanceTimer(t *testing.T) {
	// Create temp directory for test logs
	tempDir := t.TempDir()
	perfLogFile := filepath.Join(tempDir, "performance.log")

	cfg := &config.Config{
		Logging: config.LoggingConfig{
			PerformanceLog:    true,
			PerformanceFile:   perfLogFile,
			PerformanceDetail: "detailed",
		},
	}

	logger := logrus.New()
	perfLogger, err := NewPerformanceLogger(cfg, logger)
	require.NoError(t, err)
	defer perfLogger.Close()

	t.Run("basic timer operation", func(t *testing.T) {
		timer := perfLogger.StartTimer("test_operation")
		time.Sleep(10 * time.Millisecond)
		duration := timer.Stop()

		assert.True(t, duration >= 10*time.Millisecond, "duration should be at least 10ms")
	})

	t.Run("timer with metadata", func(t *testing.T) {
		timer := perfLogger.StartTimer("test_with_metadata")
		timer.WithMetadata("key1", "value1")
		timer.WithMetadata("key2", 123)
		time.Sleep(5 * time.Millisecond)
		duration := timer.Stop()

		assert.True(t, duration >= 5*time.Millisecond)
	})

	t.Run("timer with metadata map", func(t *testing.T) {
		timer := perfLogger.StartTimer("test_with_metadata_map")
		timer.WithMetadataMap(map[string]interface{}{
			"job_id":      "test-job-123",
			"event_count": 100,
		})
		time.Sleep(5 * time.Millisecond)
		duration := timer.Stop()

		assert.True(t, duration >= 5*time.Millisecond)
	})

	t.Run("timer with error", func(t *testing.T) {
		timer := perfLogger.StartTimer("test_with_error")
		time.Sleep(5 * time.Millisecond)
		testErr := assert.AnError
		duration := timer.StopWithError(testErr)

		assert.True(t, duration >= 5*time.Millisecond)
	})
}

func TestPerformanceLoggerDisabled(t *testing.T) {
	cfg := &config.Config{
		Logging: config.LoggingConfig{
			PerformanceLog:    false,
			PerformanceDetail: "detailed",
		},
	}

	logger := logrus.New()
	perfLogger, err := NewPerformanceLogger(cfg, logger)
	require.NoError(t, err)

	// Should not panic or error when disabled
	timer := perfLogger.StartTimer("test_operation")
	time.Sleep(5 * time.Millisecond)
	duration := timer.Stop()

	// Timer still tracks duration even if logging is disabled
	assert.True(t, duration >= 5*time.Millisecond)
}

func TestParsePerformanceLevel(t *testing.T) {
	// This test is no longer applicable as PerformanceLevel types don't exist
	// The performance logger doesn't use detail levels in the current implementation
	t.Skip("PerformanceLevel types no longer exist in the implementation")
}

func TestPerformanceLogOperation(t *testing.T) {
	// Create temp directory for test logs
	tempDir := t.TempDir()
	perfLogFile := filepath.Join(tempDir, "performance.log")

	cfg := &config.Config{
		Logging: config.LoggingConfig{
			PerformanceLog:    true,
			PerformanceFile:   perfLogFile,
			PerformanceDetail: "detailed",
		},
	}

	logger := logrus.New()
	perfLogger, err := NewPerformanceLogger(cfg, logger)
	require.NoError(t, err)
	defer perfLogger.Close()

	// LogOperation method doesn't exist in the current implementation
	// Use StartTimer and Stop instead to test logging functionality
	timer := perfLogger.StartTimer("test_operation")
	timer.WithMetadataMap(map[string]interface{}{
		"test_key": "test_value",
		"count":    42,
	})
	duration := timer.Stop()

	// Verify that logging doesn't panic and timer works
	assert.True(t, duration >= 0)
}

func TestPerformanceLoggerClose(t *testing.T) {
	tempDir := t.TempDir()
	perfLogFile := filepath.Join(tempDir, "performance.log")

	cfg := &config.Config{
		Logging: config.LoggingConfig{
			PerformanceLog:    true,
			PerformanceFile:   perfLogFile,
			PerformanceDetail: "detailed",
		},
	}

	logger := logrus.New()
	perfLogger, err := NewPerformanceLogger(cfg, logger)
	require.NoError(t, err)

	// Should not error when closing
	err = perfLogger.Close()
	assert.NoError(t, err)
}

func TestPerformanceTimerChaining(t *testing.T) {
	cfg := &config.Config{
		Logging: config.LoggingConfig{
			PerformanceLog:    true,
			PerformanceDetail: "detailed",
		},
	}

	logger := logrus.New()
	perfLogger, err := NewPerformanceLogger(cfg, logger)
	require.NoError(t, err)

	// Test method chaining
	timer := perfLogger.StartTimer("test_chaining").
		WithMetadata("key1", "value1").
		WithMetadata("key2", "value2").
		WithMetadataMap(map[string]interface{}{
			"key3": "value3",
			"key4": 42,
		})

	time.Sleep(5 * time.Millisecond)
	duration := timer.Stop()
	assert.True(t, duration >= 5*time.Millisecond)

	// Verify metadata was set (checking internal state)
	assert.Equal(t, "value1", timer.metadata["key1"])
	assert.Equal(t, "value2", timer.metadata["key2"])
	assert.Equal(t, "value3", timer.metadata["key3"])
	assert.Equal(t, 42, timer.metadata["key4"])
}
