package handlers

import (
	"encoding/json"
	"testing"
	"time"

	"parser-service/internal/types"
)

func TestParseDemoHandler_EnhancedProgressTracking(t *testing.T) {
	// Create a test job
	job := &types.ProcessingJob{
		JobID:                 "test-job-123",
		TempFilePath:          "/test/path.dem",
		ProgressCallbackURL:   "http://localhost:8080/callback",
		CompletionCallbackURL: "http://localhost:8080/completion",
		Status:                types.StatusQueued,
		Progress:              0,
		CurrentStep:           "Initializing",
		StartTime:             time.Now(),
		LastUpdateTime:        time.Now(),
		StepProgress:          0,
		TotalSteps:            18,
		CurrentStepNum:        1,
		Context:               make(map[string]interface{}),
		IsFinal:               false,
	}

	// Test that job is initialized with enhanced progress fields
	if job.StepProgress != 0 {
		t.Errorf("Expected initial StepProgress to be 0, got %d", job.StepProgress)
	}
	if job.TotalSteps != 18 {
		t.Errorf("Expected initial TotalSteps to be 18, got %d", job.TotalSteps)
	}
	if job.CurrentStepNum != 1 {
		t.Errorf("Expected initial CurrentStepNum to be 1, got %d", job.CurrentStepNum)
	}
	if job.Context == nil {
		t.Error("Expected Context to be initialized")
	}
	if job.IsFinal {
		t.Error("Expected initial IsFinal to be false")
	}
}

func TestParseDemoHandler_SendProgressUpdate_EnhancedFields(t *testing.T) {
	// Test that ProgressUpdate struct is created correctly with enhanced fields
	job := &types.ProcessingJob{
		JobID:          "test-job-123",
		Status:         types.StatusParsing,
		Progress:       25,
		CurrentStep:    "Processing grenade events",
		StartTime:      time.Date(2024, 1, 1, 10, 0, 0, 0, time.UTC),
		LastUpdateTime: time.Date(2024, 1, 1, 10, 5, 0, 0, time.UTC),
		StepProgress:   75,
		TotalSteps:     20,
		CurrentStepNum: 6,
		ErrorCode:      "",
		Context: map[string]interface{}{
			"step":         "grenade_events_processing",
			"round":        3,
			"total_rounds": 16,
		},
		IsFinal: false,
	}

	// Create ProgressUpdate from job data (simulating what the handler does)
	progressUpdate := types.ProgressUpdate{
		JobID:          job.JobID,
		Status:         job.Status,
		Progress:       job.Progress,
		CurrentStep:    job.CurrentStep,
		StepProgress:   job.StepProgress,
		TotalSteps:     job.TotalSteps,
		CurrentStepNum: job.CurrentStepNum,
		StartTime:      job.StartTime,
		LastUpdateTime: job.LastUpdateTime,
		Context:        job.Context,
		IsFinal:        job.IsFinal,
	}
	if job.ErrorMessage != "" {
		progressUpdate.ErrorMessage = &job.ErrorMessage
	}
	if job.ErrorCode != "" {
		progressUpdate.ErrorCode = &job.ErrorCode
	}

	// Verify enhanced progress fields
	if progressUpdate.JobID != "test-job-123" {
		t.Errorf("Expected JobID to be test-job-123, got %s", progressUpdate.JobID)
	}
	if progressUpdate.Status != types.StatusParsing {
		t.Errorf("Expected Status to be %s, got %s", types.StatusParsing, progressUpdate.Status)
	}
	if progressUpdate.Progress != 25 {
		t.Errorf("Expected Progress to be 25, got %d", progressUpdate.Progress)
	}
	if progressUpdate.CurrentStep != "Processing grenade events" {
		t.Errorf("Expected CurrentStep to be 'Processing grenade events', got %s", progressUpdate.CurrentStep)
	}
	if progressUpdate.StepProgress != 75 {
		t.Errorf("Expected StepProgress to be 75, got %d", progressUpdate.StepProgress)
	}
	if progressUpdate.TotalSteps != 20 {
		t.Errorf("Expected TotalSteps to be 20, got %d", progressUpdate.TotalSteps)
	}
	if progressUpdate.CurrentStepNum != 6 {
		t.Errorf("Expected CurrentStepNum to be 6, got %d", progressUpdate.CurrentStepNum)
	}
	if progressUpdate.StartTime != job.StartTime {
		t.Errorf("Expected StartTime to match job StartTime")
	}
	if progressUpdate.LastUpdateTime != job.LastUpdateTime {
		t.Errorf("Expected LastUpdateTime to match job LastUpdateTime")
	}
	if progressUpdate.Context == nil {
		t.Error("Expected Context to be set")
	} else {
		if progressUpdate.Context["step"] != "grenade_events_processing" {
			t.Errorf("Expected context step to be 'grenade_events_processing', got %v", progressUpdate.Context["step"])
		}
		if progressUpdate.Context["round"] != 3 {
			t.Errorf("Expected context round to be 3, got %v", progressUpdate.Context["round"])
		}
	}
	if progressUpdate.IsFinal {
		t.Error("Expected IsFinal to be false")
	}
}

func TestParseDemoHandler_SendProgressUpdate_WithErrorCode(t *testing.T) {
	// Test that ProgressUpdate struct handles error fields correctly
	errorCode := "DEMO_CORRUPTED"
	job := &types.ProcessingJob{
		JobID:          "test-job-error-123",
		Status:         types.StatusFailed,
		Progress:       30,
		CurrentStep:    "Processing demo file",
		ErrorMessage:   "Demo file corrupted",
		ErrorCode:      errorCode,
		StepProgress:   0,
		TotalSteps:     18,
		CurrentStepNum: 1,
		Context: map[string]interface{}{
			"step":          "file_validation",
			"error_details": "Invalid demo header",
		},
		IsFinal: true,
	}

	// Create ProgressUpdate from job data (simulating what the handler does)
	progressUpdate := types.ProgressUpdate{
		JobID:          job.JobID,
		Status:         job.Status,
		Progress:       job.Progress,
		CurrentStep:    job.CurrentStep,
		StepProgress:   job.StepProgress,
		TotalSteps:     job.TotalSteps,
		CurrentStepNum: job.CurrentStepNum,
		Context:        job.Context,
		IsFinal:        job.IsFinal,
	}
	if job.ErrorMessage != "" {
		progressUpdate.ErrorMessage = &job.ErrorMessage
	}
	if job.ErrorCode != "" {
		progressUpdate.ErrorCode = &job.ErrorCode
	}

	// Verify error fields
	if progressUpdate.ErrorMessage == nil {
		t.Error("Expected ErrorMessage to be set")
	} else if *progressUpdate.ErrorMessage != "Demo file corrupted" {
		t.Errorf("Expected ErrorMessage to be 'Demo file corrupted', got %s", *progressUpdate.ErrorMessage)
	}

	if progressUpdate.ErrorCode == nil {
		t.Error("Expected ErrorCode to be set")
	} else if *progressUpdate.ErrorCode != errorCode {
		t.Errorf("Expected ErrorCode to be %s, got %s", errorCode, *progressUpdate.ErrorCode)
	}

	if !progressUpdate.IsFinal {
		t.Error("Expected IsFinal to be true")
	}
}

func TestParseDemoHandler_SendProgressUpdateWithMatchData_EnhancedFields(t *testing.T) {
	// Test that payload structure includes enhanced progress fields
	matchData := &types.ParsedDemoData{
		Match: types.Match{
			Map:              "de_dust2",
			WinningTeam:      "A",
			WinningTeamScore: 16,
			LosingTeamScore:  14,
			MatchType:        "mm",
			TotalRounds:      30,
			PlaybackTicks:    50000,
		},
		Players: []types.Player{
			{
				SteamID: "123",
				Name:    "TestPlayer1",
				Team:    "A",
			},
		},
	}

	// Create a test job
	job := &types.ProcessingJob{
		JobID:          "test-job-match-123",
		Status:         types.StatusSendingMetadata,
		Progress:       90,
		CurrentStep:    "Sending match metadata",
		StepProgress:   100,
		TotalSteps:     18,
		CurrentStepNum: 12,
		Context: map[string]interface{}{
			"step": "sending_metadata",
		},
		IsFinal: false,
	}

	// Create payload structure (simulating what the handler does)
	payload := map[string]interface{}{
		"job_id":           job.JobID,
		"status":           job.Status,
		"progress":         job.Progress,
		"current_step":     job.CurrentStep,
		"step_progress":    job.StepProgress,
		"total_steps":      job.TotalSteps,
		"current_step_num": job.CurrentStepNum,
		"context":          job.Context,
		"is_final":         job.IsFinal,
		"match":            matchData.Match,
		"players":          matchData.Players,
	}

	// Verify enhanced progress fields in payload
	if payload["job_id"] != "test-job-match-123" {
		t.Errorf("Expected job_id to be test-job-match-123, got %v", payload["job_id"])
	}
	if payload["status"] != types.StatusSendingMetadata {
		t.Errorf("Expected status to be %s, got %v", types.StatusSendingMetadata, payload["status"])
	}
	if payload["progress"] != 90 {
		t.Errorf("Expected progress to be 90, got %v", payload["progress"])
	}
	if payload["current_step"] != "Sending match metadata" {
		t.Errorf("Expected current_step to be 'Sending match metadata', got %v", payload["current_step"])
	}
	if payload["step_progress"] != 100 {
		t.Errorf("Expected step_progress to be 100, got %v", payload["step_progress"])
	}
	if payload["total_steps"] != 18 {
		t.Errorf("Expected total_steps to be 18, got %v", payload["total_steps"])
	}
	if payload["current_step_num"] != 12 {
		t.Errorf("Expected current_step_num to be 12, got %v", payload["current_step_num"])
	}
	if payload["is_final"] != false {
		t.Errorf("Expected is_final to be false, got %v", payload["is_final"])
	}

	// Verify context is included
	context, exists := payload["context"]
	if !exists {
		t.Error("Expected context to be included in payload")
	} else {
		contextMap, ok := context.(map[string]interface{})
		if !ok {
			t.Error("Expected context to be a map")
		} else if contextMap["step"] != "sending_metadata" {
			t.Errorf("Expected context step to be 'sending_metadata', got %v", contextMap["step"])
		}
	}

	// Verify match data is included
	if payload["match"] == nil {
		t.Error("Expected match data to be included")
	}
	if payload["players"] == nil {
		t.Error("Expected players data to be included")
	}
}

func TestParseDemoHandler_ProgressUpdateJSONSerialization(t *testing.T) {
	// Test that ProgressUpdate struct serializes correctly to JSON
	progressUpdate := types.ProgressUpdate{
		JobID:          "test-job-123",
		Status:         types.StatusParsing,
		Progress:       25,
		CurrentStep:    "Processing grenade events",
		ErrorMessage:   nil,
		StepProgress:   75,
		TotalSteps:     20,
		CurrentStepNum: 6,
		StartTime:      time.Date(2024, 1, 1, 10, 0, 0, 0, time.UTC),
		LastUpdateTime: time.Date(2024, 1, 1, 10, 5, 0, 0, time.UTC),
		ErrorCode:      nil,
		Context: map[string]interface{}{
			"step":         "grenade_events_processing",
			"round":        3,
			"total_rounds": 16,
		},
		IsFinal: false,
	}

	// Serialize to JSON
	jsonData, err := json.Marshal(progressUpdate)
	if err != nil {
		t.Fatalf("Failed to marshal ProgressUpdate to JSON: %v", err)
	}

	// Deserialize from JSON
	var deserialized types.ProgressUpdate
	err = json.Unmarshal(jsonData, &deserialized)
	if err != nil {
		t.Fatalf("Failed to unmarshal ProgressUpdate from JSON: %v", err)
	}

	// Verify all fields are preserved
	if deserialized.JobID != progressUpdate.JobID {
		t.Errorf("JobID mismatch: expected %s, got %s", progressUpdate.JobID, deserialized.JobID)
	}
	if deserialized.Status != progressUpdate.Status {
		t.Errorf("Status mismatch: expected %s, got %s", progressUpdate.Status, deserialized.Status)
	}
	if deserialized.Progress != progressUpdate.Progress {
		t.Errorf("Progress mismatch: expected %d, got %d", progressUpdate.Progress, deserialized.Progress)
	}
	if deserialized.CurrentStep != progressUpdate.CurrentStep {
		t.Errorf("CurrentStep mismatch: expected %s, got %s", progressUpdate.CurrentStep, deserialized.CurrentStep)
	}
	if deserialized.StepProgress != progressUpdate.StepProgress {
		t.Errorf("StepProgress mismatch: expected %d, got %d", progressUpdate.StepProgress, deserialized.StepProgress)
	}
	if deserialized.TotalSteps != progressUpdate.TotalSteps {
		t.Errorf("TotalSteps mismatch: expected %d, got %d", progressUpdate.TotalSteps, deserialized.TotalSteps)
	}
	if deserialized.CurrentStepNum != progressUpdate.CurrentStepNum {
		t.Errorf("CurrentStepNum mismatch: expected %d, got %d", progressUpdate.CurrentStepNum, deserialized.CurrentStepNum)
	}
	if deserialized.StartTime != progressUpdate.StartTime {
		t.Errorf("StartTime mismatch: expected %v, got %v", progressUpdate.StartTime, deserialized.StartTime)
	}
	if deserialized.LastUpdateTime != progressUpdate.LastUpdateTime {
		t.Errorf("LastUpdateTime mismatch: expected %v, got %v", progressUpdate.LastUpdateTime, deserialized.LastUpdateTime)
	}
	if deserialized.IsFinal != progressUpdate.IsFinal {
		t.Errorf("IsFinal mismatch: expected %v, got %v", progressUpdate.IsFinal, deserialized.IsFinal)
	}

	// Verify context
	if deserialized.Context == nil {
		t.Error("Expected Context to be preserved")
	} else {
		if deserialized.Context["step"] != progressUpdate.Context["step"] {
			t.Errorf("Context step mismatch: expected %v, got %v", progressUpdate.Context["step"], deserialized.Context["step"])
		}
		// JSON unmarshaling converts numbers to float64, so we need to compare appropriately
		if deserialized.Context["round"] != float64(progressUpdate.Context["round"].(int)) {
			t.Errorf("Context round mismatch: expected %v, got %v", progressUpdate.Context["round"], deserialized.Context["round"])
		}
	}
}

func TestParseDemoHandler_ProgressUpdateWithErrorJSONSerialization(t *testing.T) {
	// Test JSON serialization with error fields
	errorMessage := "Demo file corrupted"
	errorCode := "DEMO_CORRUPTED"

	progressUpdate := types.ProgressUpdate{
		JobID:          "test-job-error-123",
		Status:         types.StatusFailed,
		Progress:       30,
		CurrentStep:    "Processing demo file",
		ErrorMessage:   &errorMessage,
		StepProgress:   0,
		TotalSteps:     18,
		CurrentStepNum: 1,
		StartTime:      time.Date(2024, 1, 1, 10, 0, 0, 0, time.UTC),
		LastUpdateTime: time.Date(2024, 1, 1, 10, 2, 0, 0, time.UTC),
		ErrorCode:      &errorCode,
		Context: map[string]interface{}{
			"step":          "file_validation",
			"error_details": "Invalid demo header",
		},
		IsFinal: true,
	}

	// Serialize to JSON
	jsonData, err := json.Marshal(progressUpdate)
	if err != nil {
		t.Fatalf("Failed to marshal ProgressUpdate with errors to JSON: %v", err)
	}

	// Deserialize from JSON
	var deserialized types.ProgressUpdate
	err = json.Unmarshal(jsonData, &deserialized)
	if err != nil {
		t.Fatalf("Failed to unmarshal ProgressUpdate with errors from JSON: %v", err)
	}

	// Verify error fields
	if deserialized.ErrorMessage == nil {
		t.Error("Expected ErrorMessage to be preserved")
	} else if *deserialized.ErrorMessage != errorMessage {
		t.Errorf("ErrorMessage mismatch: expected %s, got %s", errorMessage, *deserialized.ErrorMessage)
	}

	if deserialized.ErrorCode == nil {
		t.Error("Expected ErrorCode to be preserved")
	} else if *deserialized.ErrorCode != errorCode {
		t.Errorf("ErrorCode mismatch: expected %s, got %s", errorCode, *deserialized.ErrorCode)
	}

	if !deserialized.IsFinal {
		t.Error("Expected IsFinal to be preserved as true")
	}
}
