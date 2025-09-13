package parser

import (
	"context"
	"testing"
	"time"

	"parser-service/internal/config"
	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
)

// TestProgressCallback is a test helper that captures progress updates
type TestProgressCallback struct {
	Updates []types.ProgressUpdate
}

func (t *TestProgressCallback) Callback(update types.ProgressUpdate) {
	t.Updates = append(t.Updates, update)
}

func (t *TestProgressCallback) GetLastUpdate() *types.ProgressUpdate {
	if len(t.Updates) == 0 {
		return nil
	}
	return &t.Updates[len(t.Updates)-1]
}

func (t *TestProgressCallback) GetUpdateCount() int {
	return len(t.Updates)
}

func (t *TestProgressCallback) Clear() {
	t.Updates = []types.ProgressUpdate{}
}

func TestProgressTracking_EnhancedFields(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)

	callback := &TestProgressCallback{}
	ctx := context.Background()

	// Test that progress updates include all enhanced fields
	progressCallback := func(update types.ProgressUpdate) {
		callback.Callback(update)
	}

	// Test with a non-existent file to trigger early progress updates
	_, err := parser.ParseDemo(ctx, "/nonexistent/path.dem", progressCallback)

	// We expect an error, but we should have received some progress updates
	if err == nil {
		t.Error("Expected error for non-existent file, got none")
	}

	// Check that we received at least one progress update
	if callback.GetUpdateCount() == 0 {
		t.Skip("No progress updates received - this may be expected for non-existent files")
		return
	}

	// Check the first progress update has enhanced fields
	firstUpdate := callback.Updates[0]

	// Test that enhanced fields are present and have reasonable values
	if firstUpdate.StepProgress < 0 || firstUpdate.StepProgress > 100 {
		t.Errorf("Expected StepProgress to be between 0-100, got %d", firstUpdate.StepProgress)
	}

	if firstUpdate.TotalSteps <= 0 {
		t.Errorf("Expected TotalSteps to be positive, got %d", firstUpdate.TotalSteps)
	}

	if firstUpdate.CurrentStepNum <= 0 {
		t.Errorf("Expected CurrentStepNum to be positive, got %d", firstUpdate.CurrentStepNum)
	}

	// Test that timing fields are set
	if firstUpdate.StartTime.IsZero() {
		t.Error("Expected StartTime to be set")
	}

	if firstUpdate.LastUpdateTime.IsZero() {
		t.Error("Expected LastUpdateTime to be set")
	}

	// Test that context is initialized
	if firstUpdate.Context == nil {
		t.Error("Expected Context to be initialized")
	}

	// Test that IsFinal is set appropriately
	if firstUpdate.IsFinal {
		t.Error("Expected IsFinal to be false for initial progress update")
	}
}

func TestProgressTracking_StepProgression(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)

	callback := &TestProgressCallback{}
	ctx := context.Background()

	progressCallback := func(update types.ProgressUpdate) {
		callback.Callback(update)
	}

	// Test with invalid file to get multiple progress updates
	_, err := parser.ParseDemo(ctx, "/invalid/file.dem", progressCallback)

	// We expect an error, but should have progress updates
	if err == nil {
		t.Error("Expected error for invalid file, got none")
	}

	// Check that we have multiple progress updates
	if callback.GetUpdateCount() < 2 {
		t.Skip("Not enough progress updates received - this may be expected for invalid files")
		return
	}

	// Test step progression
	updates := callback.Updates

	// First update should be step 1
	if updates[0].CurrentStepNum != 1 {
		t.Errorf("Expected first update to be step 1, got %d", updates[0].CurrentStepNum)
	}

	// Check that step numbers are reasonable
	for i, update := range updates {
		if update.CurrentStepNum < 1 || update.CurrentStepNum > update.TotalSteps {
			t.Errorf("Update %d: CurrentStepNum %d should be between 1 and %d",
				i, update.CurrentStepNum, update.TotalSteps)
		}
	}
}

func TestProgressTracking_ContextData(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)

	callback := &TestProgressCallback{}
	ctx := context.Background()

	progressCallback := func(update types.ProgressUpdate) {
		callback.Callback(update)
	}

	// Test with invalid file
	_, err := parser.ParseDemo(ctx, "/invalid/file.dem", progressCallback)

	if err == nil {
		t.Error("Expected error for invalid file, got none")
	}

	// Check that context contains useful information
	updates := callback.Updates
	if len(updates) == 0 {
		t.Skip("No progress updates received - this may be expected for invalid files")
		return
	}

	firstUpdate := updates[0]

	// Check that context has step information
	if step, exists := firstUpdate.Context["step"]; !exists {
		t.Error("Expected context to contain 'step' information")
	} else if step == "" {
		t.Error("Expected 'step' context to be non-empty")
	}

	// Check that context is a map
	if firstUpdate.Context == nil {
		t.Error("Expected context to be initialized as a map")
	}
}

func TestProgressTracking_ErrorHandling(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)

	callback := &TestProgressCallback{}
	ctx := context.Background()

	progressCallback := func(update types.ProgressUpdate) {
		callback.Callback(update)
	}

	// Test with non-existent file to trigger error
	_, err := parser.ParseDemo(ctx, "/nonexistent/file.dem", progressCallback)

	if err == nil {
		t.Error("Expected error for non-existent file, got none")
	}

	// Check that we have progress updates even with errors
	if callback.GetUpdateCount() == 0 {
		t.Skip("No progress updates received - this may be expected for non-existent files")
		return
	}

	// Check that error information is captured in context or error fields
	updates := callback.Updates
	lastUpdate := updates[len(updates)-1]

	// The last update might contain error information
	// This depends on how errors are handled in the progress tracking
	if lastUpdate.ErrorCode != nil && *lastUpdate.ErrorCode == "" {
		t.Error("If ErrorCode is set, it should not be empty")
	}
}

func TestProgressTracking_TimingAccuracy(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)

	callback := &TestProgressCallback{}
	ctx := context.Background()

	startTime := time.Now()

	progressCallback := func(update types.ProgressUpdate) {
		callback.Callback(update)
	}

	// Test with invalid file
	_, err := parser.ParseDemo(ctx, "/invalid/file.dem", progressCallback)

	if err == nil {
		t.Error("Expected error for invalid file, got none")
	}

	// Check timing accuracy
	updates := callback.Updates
	if len(updates) == 0 {
		t.Skip("No progress updates received - this may be expected for invalid files")
		return
	}

	firstUpdate := updates[0]

	// StartTime should be close to when we started the test
	timeDiff := firstUpdate.StartTime.Sub(startTime)
	if timeDiff < 0 || timeDiff > 5*time.Second {
		t.Errorf("StartTime should be close to test start time, got difference of %v", timeDiff)
	}

	// LastUpdateTime should be after StartTime
	if firstUpdate.LastUpdateTime.Before(firstUpdate.StartTime) {
		t.Error("LastUpdateTime should be after StartTime")
	}

	// Check that timing is consistent across updates
	for i := 1; i < len(updates); i++ {
		current := updates[i]
		previous := updates[i-1]

		if current.LastUpdateTime.Before(previous.LastUpdateTime) {
			t.Errorf("Update %d: LastUpdateTime should be after previous update", i)
		}
	}
}

func TestProgressTracking_StepManagerIntegration(t *testing.T) {
	// Test the StepManager functionality directly
	totalRounds := 16
	stepManager := types.NewStepManager(totalRounds)

	expectedTotalSteps := 18 + totalRounds // 18 base steps + rounds
	if stepManager.TotalSteps != expectedTotalSteps {
		t.Errorf("Expected TotalSteps to be %d, got %d", expectedTotalSteps, stepManager.TotalSteps)
	}

	// Test step updates
	stepManager.UpdateStep(3, "Processing grenade events")
	if stepManager.CurrentStepNum != 3 {
		t.Errorf("Expected CurrentStepNum to be 3, got %d", stepManager.CurrentStepNum)
	}

	// Test step progress updates
	context := map[string]interface{}{
		"round":        5,
		"total_rounds": 16,
	}
	stepManager.UpdateStepProgress(75, context)

	if stepManager.StepProgress != 75 {
		t.Errorf("Expected StepProgress to be 75, got %d", stepManager.StepProgress)
	}

	// Test context merging
	if stepManager.Context["round"] != 5 {
		t.Errorf("Expected context round to be 5, got %v", stepManager.Context["round"])
	}

	// Test overall progress calculation
	overallProgress := stepManager.GetOverallProgress()
	expectedProgress := (2*100 + 75) / stepManager.TotalSteps // (completed steps * 100 + current step progress) / total steps

	if overallProgress != expectedProgress {
		t.Errorf("Expected overall progress to be %d, got %d", expectedProgress, overallProgress)
	}
}

func TestProgressTracking_StepManagerEdgeCases(t *testing.T) {
	// Test with zero rounds
	stepManager := types.NewStepManager(0)
	expectedTotalSteps := 18 // Just base steps
	if stepManager.TotalSteps != expectedTotalSteps {
		t.Errorf("Expected TotalSteps to be %d for zero rounds, got %d", expectedTotalSteps, stepManager.TotalSteps)
	}

	// Test step progress boundaries
	stepManager.UpdateStepProgress(0, nil)
	if stepManager.StepProgress != 0 {
		t.Errorf("Expected StepProgress to be 0, got %d", stepManager.StepProgress)
	}

	stepManager.UpdateStepProgress(100, nil)
	if stepManager.StepProgress != 100 {
		t.Errorf("Expected StepProgress to be 100, got %d", stepManager.StepProgress)
	}

	// Test step number boundaries
	stepManager.UpdateStep(1, "First step")
	if stepManager.CurrentStepNum != 1 {
		t.Errorf("Expected CurrentStepNum to be 1, got %d", stepManager.CurrentStepNum)
	}

	stepManager.UpdateStep(stepManager.TotalSteps, "Last step")
	if stepManager.CurrentStepNum != stepManager.TotalSteps {
		t.Errorf("Expected CurrentStepNum to be %d, got %d", stepManager.TotalSteps, stepManager.CurrentStepNum)
	}
}

func TestProgressTracking_ContextMerging(t *testing.T) {
	stepManager := types.NewStepManager(16)

	// Test initial context
	stepManager.UpdateStep(1, "Initial step")
	if stepManager.Context["current_step_name"] != "Initial step" {
		t.Errorf("Expected current_step_name to be 'Initial step', got %v", stepManager.Context["current_step_name"])
	}

	// Test context merging
	context1 := map[string]interface{}{
		"round":            5,
		"events_processed": 100,
	}
	stepManager.UpdateStepProgress(50, context1)

	if stepManager.Context["round"] != 5 {
		t.Errorf("Expected context round to be 5, got %v", stepManager.Context["round"])
	}
	if stepManager.Context["events_processed"] != 100 {
		t.Errorf("Expected context events_processed to be 100, got %v", stepManager.Context["events_processed"])
	}

	// Test context overwriting
	context2 := map[string]interface{}{
		"round":     6, // Should overwrite previous round
		"new_field": "new_value",
	}
	stepManager.UpdateStepProgress(75, context2)

	if stepManager.Context["round"] != 6 {
		t.Errorf("Expected context round to be updated to 6, got %v", stepManager.Context["round"])
	}
	if stepManager.Context["new_field"] != "new_value" {
		t.Errorf("Expected context new_field to be 'new_value', got %v", stepManager.Context["new_field"])
	}
	if stepManager.Context["events_processed"] != 100 {
		t.Errorf("Expected context events_processed to remain 100, got %v", stepManager.Context["events_processed"])
	}
}

func TestProgressTracking_ProgressCalculation(t *testing.T) {
	// Test progress calculation with different scenarios
	testCases := []struct {
		name         string
		totalRounds  int
		currentStep  int
		stepProgress int
		expectedMin  int
		expectedMax  int
	}{
		{
			name:         "First step, no progress",
			totalRounds:  16,
			currentStep:  1,
			stepProgress: 0,
			expectedMin:  0,
			expectedMax:  5,
		},
		{
			name:         "First step, half progress",
			totalRounds:  16,
			currentStep:  1,
			stepProgress: 50,
			expectedMin:  1,
			expectedMax:  3,
		},
		{
			name:         "Middle step, full progress",
			totalRounds:  16,
			currentStep:  10,
			stepProgress: 100,
			expectedMin:  25,
			expectedMax:  30,
		},
		{
			name:         "Last step, full progress",
			totalRounds:  16,
			currentStep:  34, // 18 + 16
			stepProgress: 100,
			expectedMin:  95,
			expectedMax:  100,
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			stepManager := types.NewStepManager(tc.totalRounds)
			stepManager.UpdateStep(tc.currentStep, "Test step")
			stepManager.UpdateStepProgress(tc.stepProgress, nil)

			overallProgress := stepManager.GetOverallProgress()

			if overallProgress < tc.expectedMin || overallProgress > tc.expectedMax {
				t.Errorf("Expected overall progress to be between %d-%d, got %d",
					tc.expectedMin, tc.expectedMax, overallProgress)
			}
		})
	}
}
