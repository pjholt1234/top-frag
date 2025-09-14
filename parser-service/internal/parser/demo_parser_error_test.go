package parser

import (
	"context"
	"testing"

	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"

	"parser-service/internal/config"
	"parser-service/internal/types"
)

func TestDemoParser_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *DemoParser
		demoPath    string
		expectError bool
		errorType   types.ErrorType
	}{
		{
			name: "nonexistent_file_should_return_error",
			setup: func() *DemoParser {
				cfg := &config.Config{
					Parser: config.ParserConfig{
						MaxDemoSize: 100 * 1024 * 1024, // 100MB
					},
				}
				logger := logrus.New()
				return NewDemoParser(cfg, logger)
			},
			demoPath:    "/nonexistent/path.dem",
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
		{
			name: "invalid_file_extension_should_return_error",
			setup: func() *DemoParser {
				cfg := &config.Config{
					Parser: config.ParserConfig{
						MaxDemoSize: 100 * 1024 * 1024, // 100MB
					},
				}
				logger := logrus.New()
				return NewDemoParser(cfg, logger)
			},
			demoPath:    "/tmp/test.txt",
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
		{
			name: "nil_config_should_handle_gracefully",
			setup: func() *DemoParser {
				logger := logrus.New()
				return &DemoParser{
					config: nil,
					logger: logger,
				}
			},
			demoPath:    "/tmp/test.dem",
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
		{
			name: "nil_logger_should_handle_gracefully",
			setup: func() *DemoParser {
				cfg := &config.Config{
					Parser: config.ParserConfig{
						MaxDemoSize: 100 * 1024 * 1024, // 100MB
					},
				}
				return &DemoParser{
					config: cfg,
					logger: nil,
				}
			},
			demoPath:    "/tmp/test.dem",
			expectError: true,
			errorType:   types.ErrorTypeValidation,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			parser := tt.setup()
			ctx := context.Background()

			progressCallback := func(update types.ProgressUpdate) {
				// Test callback - should not panic
			}

			_, err := parser.ParseDemoFromFile(ctx, tt.demoPath, progressCallback)

			if tt.expectError {
				assert.Error(t, err)
				if parseErr, ok := err.(*types.ParseError); ok {
					assert.Equal(t, tt.errorType, parseErr.Type)
				}
			} else {
				assert.NoError(t, err)
			}
		})
	}
}

func TestProgressManager_ErrorScenarios(t *testing.T) {
	tests := []struct {
		name        string
		setup       func() *ProgressManager
		expectError bool
	}{
		{
			name: "nil_logger_should_handle_gracefully",
			setup: func() *ProgressManager {
				return &ProgressManager{
					logger: nil,
				}
			},
			expectError: false,
		},
		{
			name: "nil_progress_callback_should_handle_gracefully",
			setup: func() *ProgressManager {
				logger := logrus.New()
				return &ProgressManager{
					logger:           logger,
					progressCallback: nil,
				}
			},
			expectError: false,
		},
		{
			name: "valid_setup_should_work",
			setup: func() *ProgressManager {
				logger := logrus.New()
				callback := func(update types.ProgressUpdate) {
					// Test callback
				}
				return NewProgressManager(logger, callback, 100)
			},
			expectError: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			pm := tt.setup()

			// Test UpdateProgress
			pm.UpdateProgress(types.ProgressUpdate{
				Status:      types.StatusParsing,
				Progress:    50,
				CurrentStep: "Test step",
				IsFinal:     false,
			})

			// Test ReportError
			pm.ReportError("test error", "TEST_ERROR")

			// Test HasError
			hasError := pm.HasError()
			if tt.expectError {
				assert.True(t, hasError)
			} else {
				// Should have error from ReportError call
				assert.True(t, hasError)
			}

			// Test GetError
			errorMsg, errorCode := pm.GetError()
			assert.Equal(t, "test error", errorMsg)
			assert.Equal(t, "TEST_ERROR", errorCode)
		})
	}
}

func TestProgressManager_UpdateInterval(t *testing.T) {
	logger := logrus.New()
	updateCount := 0
	callback := func(update types.ProgressUpdate) {
		updateCount++
	}

	pm := NewProgressManager(logger, callback, 50) // 50ms interval

	// Send multiple updates quickly
	for i := 0; i < 10; i++ {
		pm.UpdateProgress(types.ProgressUpdate{
			Status:      types.StatusParsing,
			Progress:    i * 10,
			CurrentStep: "Test step",
			IsFinal:     false,
		})
	}

	// Should only have received a few updates due to throttling
	assert.Less(t, updateCount, 10)
}

func TestProgressManager_ReportCompletion(t *testing.T) {
	logger := logrus.New()
	callback := func(update types.ProgressUpdate) {
		assert.Equal(t, types.StatusCompleted, update.Status)
		assert.Equal(t, 100, update.Progress)
		assert.True(t, update.IsFinal)
	}

	pm := NewProgressManager(logger, callback, 0)
	pm.ReportCompletion(types.ProgressUpdate{
		Status:      types.StatusProcessingEvents,
		Progress:    50,
		CurrentStep: "Test step",
		IsFinal:     false,
	})
}

func TestDemoParser_ProgressManagerIntegration(t *testing.T) {
	cfg := &config.Config{
		Parser: config.ParserConfig{
			MaxDemoSize: 100 * 1024 * 1024, // 100MB
		},
	}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)

	// Test that progress manager is initialized
	assert.Nil(t, parser.progressManager)

	// Test that progress manager gets initialized during parsing
	ctx := context.Background()
	progressCallback := func(update types.ProgressUpdate) {
		// Test callback
	}

	_, err := parser.ParseDemoFromFile(ctx, "/nonexistent/path.dem", progressCallback)

	// Should have error but progress manager should be initialized
	assert.Error(t, err)
	assert.NotNil(t, parser.progressManager)
}
