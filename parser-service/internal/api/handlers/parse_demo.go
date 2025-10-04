package handlers

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	"bytes"
	"compress/bzip2"
	"io"
	"mime/multipart"
	"os"
	"path/filepath"
	"strings"

	"parser-service/internal/config"
	"parser-service/internal/parser"
	"parser-service/internal/types"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/sirupsen/logrus"
)

type ParseDemoHandler struct {
	config          *config.Config
	logger          *logrus.Logger
	demoParser      *parser.DemoParser
	batchSender     *parser.BatchSender
	progressManager *parser.ProgressManager
	jobs            map[string]*types.ProcessingJob
}

func NewParseDemoHandler(cfg *config.Config, logger *logrus.Logger, demoParser *parser.DemoParser, batchSender *parser.BatchSender, progressManager *parser.ProgressManager) *ParseDemoHandler {
	return &ParseDemoHandler{
		config:          cfg,
		logger:          logger,
		demoParser:      demoParser,
		batchSender:     batchSender,
		progressManager: progressManager,
		jobs:            make(map[string]*types.ProcessingJob),
	}
}

//POST /api/parse-demo
// What this does:
// Receives demo file upload with callback URLs
// Validates the uploaded file
// Creates a job with unique ID
// Saves file to temporary location
// Starts processing in background using goroutine
// Returns immediately with job ID (non-blocking)

// gin.Context: Represents the HTTP request and response
func (h *ParseDemoHandler) HandleParseDemo(c *gin.Context) {
	var req types.ParseDemoRequest
	if err := c.ShouldBind(&req); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, "Failed to bind file upload request", err)
		h.progressManager.ReportParseError(parseError)
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"error":   "Invalid file upload request format",
		})
		return
	}

	// Validate file
	if err := h.validateUploadedFile(req.DemoFile); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, "File validation failed", err)
		h.progressManager.ReportParseError(parseError)
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"error":   err.Error(),
		})
		return
	}

	if req.JobID == "" {
		req.JobID = uuid.New().String()
	}

	if _, exists := h.jobs[req.JobID]; exists {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, "Job already exists", nil)
		parseError = parseError.WithContext("job_id", req.JobID)
		h.progressManager.ReportParseError(parseError)
		c.JSON(http.StatusConflict, gin.H{
			"success": false,
			"error":   "Job already exists",
			"job_id":  req.JobID,
		})
		return
	}

	// Save uploaded file to temporary location
	tempFilePath, err := h.saveUploadedFile(req.DemoFile)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeResourceExhausted, types.ErrorSeverityCritical, "Failed to save uploaded file", err)
		h.progressManager.ReportParseError(parseError)
		c.JSON(http.StatusInternalServerError, gin.H{
			"success": false,
			"error":   "Failed to save uploaded file",
		})
		return
	}

	job := &types.ProcessingJob{
		JobID:                 req.JobID,
		TempFilePath:          tempFilePath,
		ProgressCallbackURL:   req.ProgressCallbackURL,
		CompletionCallbackURL: req.CompletionCallbackURL,
		Status:                types.StatusQueued,
		Progress:              0,
		CurrentStep:           "Job queued",
		StartTime:             time.Now(),
	}

	h.jobs[req.JobID] = job

	// Start background processing
	go h.processDemo(context.Background(), job)

	c.JSON(http.StatusAccepted, types.ParseDemoResponse{
		Success: true,
		JobID:   req.JobID,
		Message: "Demo parsing started",
	})
}

// validateUploadedFile validates the uploaded demo file
func (h *ParseDemoHandler) validateUploadedFile(file *multipart.FileHeader) error {
	if file == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, "demo file is required", nil)
	}

	// Check file extension - support both .dem and .dem.bz2 files
	filename := strings.ToLower(file.Filename)
	if !strings.HasSuffix(filename, ".dem") && !strings.HasSuffix(filename, ".dem.bz2") {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, "invalid file extension, expected .dem or .dem.bz2 file", nil)
	}

	// Check file size
	if file.Size > h.config.Parser.MaxDemoSize {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, fmt.Sprintf("demo file too large: %d bytes (max: %d)", file.Size, h.config.Parser.MaxDemoSize), nil)
	}

	return nil
}

// cleanupTempFile safely removes a temporary file
func (h *ParseDemoHandler) cleanupTempFile(filePath string) {
	if filePath == "" {
		return
	}

	if err := os.Remove(filePath); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeResourceExhausted, types.ErrorSeverityCritical, "Failed to clean up temporary file", err)
		parseError = parseError.WithContext("temp_file", filePath)
		h.progressManager.ReportParseError(parseError)
	} else {
		h.logger.WithField("temp_file", filePath).Info("Cleaned up temporary file")
	}
}

// saveUploadedFile saves the uploaded file to a temporary location
// If the file is a .dem.bz2 file, it will be decompressed to a .dem file
func (h *ParseDemoHandler) saveUploadedFile(file *multipart.FileHeader) (string, error) {
	// Create temp directory if it doesn't exist
	if err := os.MkdirAll(h.config.Parser.TempDir, 0755); err != nil {
		return "", types.NewParseErrorWithSeverity(types.ErrorTypeResourceExhausted, types.ErrorSeverityCritical, "failed to create temp directory", err)
	}

	// Determine if this is a compressed file
	isCompressed := strings.HasSuffix(strings.ToLower(file.Filename), ".dem.bz2")

	// Generate unique filename - always save as .dem for the parser
	var filename string
	if isCompressed {
		// Remove .bz2 extension and add .dem
		baseFilename := strings.TrimSuffix(file.Filename, ".bz2")
		filename = fmt.Sprintf("demo_%s_%s", uuid.New().String(), baseFilename)
	} else {
		filename = fmt.Sprintf("demo_%s_%s", uuid.New().String(), file.Filename)
	}
	tempFilePath := filepath.Join(h.config.Parser.TempDir, filename)

	// Open the uploaded file
	src, err := file.Open()
	if err != nil {
		return "", types.NewParseErrorWithSeverity(types.ErrorTypeResourceExhausted, types.ErrorSeverityCritical, "failed to open uploaded file", err)
	}
	defer src.Close()

	// Create the destination file
	dst, err := os.Create(tempFilePath)
	if err != nil {
		return "", types.NewParseErrorWithSeverity(types.ErrorTypeResourceExhausted, types.ErrorSeverityCritical, "failed to create temp file", err)
	}
	defer dst.Close()

	if isCompressed {
		// Decompress bz2 file
		h.logger.WithField("temp_file", tempFilePath).Info("Decompressing bz2 demo file")
		if err := h.decompressBz2File(src, dst); err != nil {
			return "", types.NewParseErrorWithSeverity(types.ErrorTypeResourceExhausted, types.ErrorSeverityCritical, "failed to decompress bz2 file", err)
		}
		h.logger.WithField("temp_file", tempFilePath).Info("Successfully decompressed bz2 demo file")
	} else {
		// Copy the file content directly
		if _, err = io.Copy(dst, src); err != nil {
			return "", types.NewParseErrorWithSeverity(types.ErrorTypeResourceExhausted, types.ErrorSeverityCritical, "failed to copy file content", err)
		}
		h.logger.WithField("temp_file", tempFilePath).Info("Saved uploaded demo file")
	}

	return tempFilePath, nil
}

// decompressBz2File decompresses a bz2 file from src to dst
func (h *ParseDemoHandler) decompressBz2File(src io.Reader, dst io.Writer) error {
	// Create a bzip2 reader
	bz2Reader := bzip2.NewReader(src)

	// Copy the decompressed content to the destination
	_, err := io.Copy(dst, bz2Reader)
	if err != nil {
		return fmt.Errorf("failed to decompress bz2 content: %w", err)
	}

	return nil
}

// Handles the main demo parsing logic
// Parses the demo file and sends the data to the batch sender
// Sends progress updates to the callback URLs
// Handles errors and sends error messages to the callback URLs
// Ensures temporary files are cleaned up in all scenarios
func (h *ParseDemoHandler) processDemo(ctx context.Context, job *types.ProcessingJob) {
	defer func() {
		// Clean up temporary file if it exists
		h.cleanupTempFile(job.TempFilePath)

		if r := recover(); r != nil {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeUnknown, types.ErrorSeverityCritical, "Panic in demo processing", nil)
			parseError = parseError.WithContext("job_id", job.JobID)
			parseError = parseError.WithContext("panic", r)
			h.progressManager.ReportParseError(parseError)

			job.Status = types.StatusFailed
			job.ErrorMessage = "Internal processing error"

			if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
				parseError = types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityCritical, "Failed to send error to Laravel", err)
				h.progressManager.ReportParseError(parseError)
			}
		}
	}()

	h.logger.WithField("job_id", job.JobID).Info("Starting demo processing")

	// Validating
	job.Status = types.StatusValidating
	job.CurrentStep = "Validating demo file"
	job.Progress = 5
	if err := h.sendProgressUpdate(ctx, job); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeProgressUpdate, types.ErrorSeverityInfo, "Failed to send validation progress update", err)
		h.progressManager.ReportParseError(parseError)
	}

	// Uploading (file was already saved, but we can indicate this step)
	job.Status = types.StatusUploading
	job.CurrentStep = "File uploaded successfully"
	job.Progress = 8
	if err := h.sendProgressUpdate(ctx, job); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeProgressUpdate, types.ErrorSeverityInfo, "Failed to send upload progress update", err)
		h.progressManager.ReportParseError(parseError)
	}

	// Initializing
	job.Status = types.StatusInitializing
	job.CurrentStep = "Initializing parser"
	job.Progress = 10
	if err := h.sendProgressUpdate(ctx, job); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeProgressUpdate, types.ErrorSeverityInfo, "Failed to send initializing progress update", err)
		h.progressManager.ReportParseError(parseError)
	}

	// Parsing
	// Initialize step manager (we'll update total steps once we know the round count)
	stepManager := types.NewStepManager(0) // Will be updated during parsing

	// Initialize job with step manager data
	job.StepProgress = stepManager.StepProgress
	job.TotalSteps = stepManager.TotalSteps
	job.CurrentStepNum = stepManager.CurrentStepNum
	job.Context = stepManager.Context
	job.LastUpdateTime = stepManager.LastUpdateTime

	job.Status = types.StatusParsing
	job.CurrentStep = "Parsing demo file"

	if err := h.sendProgressUpdate(ctx, job); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeProgressUpdate, types.ErrorSeverityInfo, "Failed to send parsing progress update", err)
		h.progressManager.ReportParseError(parseError)
	}

	parsedData, err := h.demoParser.ParseDemo(ctx, job.TempFilePath, func(update types.ProgressUpdate) {
		// Update job with new progress data
		job.Progress = update.Progress
		job.CurrentStep = update.CurrentStep
		job.StepProgress = update.StepProgress
		job.TotalSteps = update.TotalSteps
		job.CurrentStepNum = update.CurrentStepNum
		job.Context = update.Context
		job.LastUpdateTime = update.LastUpdateTime

		// Update status based on progress
		if update.Progress < 20 {
			job.Status = types.StatusParsing
		} else if update.Progress < 85 {
			job.Status = types.StatusProcessingEvents
		} else {
			job.Status = types.StatusFinalizing
		}

		if err := h.sendProgressUpdate(ctx, job); err != nil {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeProgressUpdate, types.ErrorSeverityInfo, "Failed to send progress update", err)
			h.progressManager.ReportParseError(parseError)
		}
	})

	if err != nil {
		// Check if it's a ParseError with severity information
		if parseErr, ok := err.(*types.ParseError); ok {
			h.progressManager.ReportParseError(parseErr)
		} else {
			// Convert generic error to ParseError with CRITICAL severity
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeParsing, types.ErrorSeverityCritical, "Demo parsing failed", err)
			parseError = parseError.WithContext("job_id", job.JobID)
			h.progressManager.ReportParseError(parseError)
		}

		job.Status = types.StatusParseFailed
		job.ErrorMessage = err.Error()

		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityCritical, "Failed to send error to Laravel", err)
			h.progressManager.ReportParseError(parseError)
		}

		return
	}

	job.MatchData = parsedData

	// Sending metadata via progress callback
	job.Status = types.StatusSendingMetadata
	job.CurrentStep = "Sending match metadata"
	job.Progress = 90
	job.CurrentStepNum = 12 // Sending match metadata step
	job.StepProgress = 0
	job.LastUpdateTime = time.Now()
	job.Context["step"] = "sending_metadata"

	// Send match and players data via progress callback
	if err := h.sendProgressUpdateWithMatchData(ctx, job, parsedData); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeProgressUpdate, types.ErrorSeverityInfo, "Failed to send progress update with match data", err)
		h.progressManager.ReportParseError(parseError)
		// Don't fail the job for progress update failures
	}

	// Sending events
	job.Status = types.StatusSendingEvents
	job.CurrentStep = "Sending event data"
	job.Progress = 95
	job.CurrentStepNum = 13 // Sending events step
	job.StepProgress = 0
	job.LastUpdateTime = time.Now()
	job.Context["step"] = "sending_events"

	if err := h.sendProgressUpdate(ctx, job); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeProgressUpdate, types.ErrorSeverityInfo, "Failed to send progress update", err)
		h.progressManager.ReportParseError(parseError)
	}

	if err := h.sendAllEvents(ctx, job, parsedData); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "Failed to send events", err)
		parseError = parseError.WithContext("job_id", job.JobID)
		h.progressManager.ReportParseError(parseError)

		job.Status = types.StatusCallbackFailed
		job.ErrorMessage = "Failed to send events"
		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			parseError = types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityCritical, "Failed to send error to Laravel", err)
			h.progressManager.ReportParseError(parseError)
		}
		return
	}

	// Finalizing
	job.Status = types.StatusFinalizing
	job.CurrentStep = "Finalizing job"
	job.Progress = 98
	job.CurrentStepNum = 18 // Final step
	job.StepProgress = 100
	job.IsFinal = true
	job.LastUpdateTime = time.Now()
	job.Context["step"] = "finalization"

	if err := h.sendProgressUpdate(ctx, job); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeProgressUpdate, types.ErrorSeverityInfo, "Failed to send progress update", err)
		h.progressManager.ReportParseError(parseError)
	}

	if err := h.batchSender.SendCompletion(ctx, job.JobID, job.CompletionCallbackURL); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "Failed to send completion signal", err)
		parseError = parseError.WithContext("job_id", job.JobID)
		h.progressManager.ReportParseError(parseError)

		job.Status = types.StatusCallbackFailed
		job.ErrorMessage = "Failed to send completion signal"
		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			parseError = types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityCritical, "Failed to send error to Laravel", err)
			h.progressManager.ReportParseError(parseError)
		}
		return
	}

	job.Status = types.StatusCompleted
	job.Progress = 100
	job.CurrentStep = "Completed"

	h.logger.WithField("job_id", job.JobID).Info("Demo processing completed successfully")
}

// Sends progress updates to the callback URLs
// Creates a progress update struct with current job status
// Marshals the struct to JSON
// Sends the JSON data to the progress callback URL

func (h *ParseDemoHandler) sendProgressUpdate(ctx context.Context, job *types.ProcessingJob) error {
	update := types.ProgressUpdate{
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
		update.ErrorMessage = &job.ErrorMessage
	}

	if job.ErrorCode != "" {
		update.ErrorCode = &job.ErrorCode
	}

	jsonData, err := json.Marshal(update)
	if err != nil {
		return fmt.Errorf("failed to marshal progress update: %w", err)
	}

	req, err := http.NewRequestWithContext(ctx, "POST", job.ProgressCallbackURL, bytes.NewBuffer(jsonData))
	if err != nil {
		return fmt.Errorf("failed to create progress update request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")

	// Add API key for Laravel callback endpoints
	if h.config.Server.APIKey != "" {
		req.Header.Set("X-API-Key", h.config.Server.APIKey)
	}

	client := &http.Client{Timeout: h.config.Batch.HTTPTimeout}
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("failed to send progress update: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("progress update failed with status %d", resp.StatusCode)
	}

	return nil
}

// sendProgressUpdateWithMatchData sends progress updates with match and players data
func (h *ParseDemoHandler) sendProgressUpdateWithMatchData(ctx context.Context, job *types.ProcessingJob, parsedData *types.ParsedDemoData) error {
	update := types.ProgressUpdate{
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
		update.ErrorMessage = &job.ErrorMessage
	}

	if job.ErrorCode != "" {
		update.ErrorCode = &job.ErrorCode
	}

	// Create payload with match and players data
	payload := map[string]interface{}{
		"job_id":           update.JobID,
		"status":           update.Status,
		"progress":         update.Progress,
		"current_step":     update.CurrentStep,
		"step_progress":    update.StepProgress,
		"total_steps":      update.TotalSteps,
		"current_step_num": update.CurrentStepNum,
		"start_time":       update.StartTime,
		"last_update_time": update.LastUpdateTime,
		"context":          update.Context,
		"is_final":         update.IsFinal,
		"match":            parsedData.Match,
		"players":          parsedData.Players,
	}

	if update.ErrorMessage != nil {
		payload["error_message"] = *update.ErrorMessage
	}

	if update.ErrorCode != nil {
		payload["error_code"] = *update.ErrorCode
	}

	jsonData, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal progress update with match data: %w", err)
	}

	req, err := http.NewRequestWithContext(ctx, "POST", job.ProgressCallbackURL, bytes.NewBuffer(jsonData))
	if err != nil {
		return fmt.Errorf("failed to create progress update request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")

	// Add API key for Laravel callback endpoints
	if h.config.Server.APIKey != "" {
		req.Header.Set("X-API-Key", h.config.Server.APIKey)
	}

	client := &http.Client{Timeout: h.config.Batch.HTTPTimeout}
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("failed to send progress update with match data: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("progress update with match data failed with status %d", resp.StatusCode)
	}

	return nil
}

func (h *ParseDemoHandler) sendAllEvents(ctx context.Context, job *types.ProcessingJob, parsedData *types.ParsedDemoData) error {
	// Send match data first (includes game mode and match type)
	if err := h.batchSender.SendMatchData(ctx, job.JobID, job.CompletionCallbackURL, parsedData.Match); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send match data", err)
	}

	if err := h.batchSender.SendRoundEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.RoundEvents); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send round events", err)
	}

	if err := h.batchSender.SendDamageEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.DamageEvents); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send damage events", err)
	}

	if err := h.batchSender.SendGrenadeEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.GrenadeEvents); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send grenade events", err)
	}

	if err := h.batchSender.SendGunfightEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.GunfightEvents); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send gunfight events", err)
	}

	if err := h.batchSender.SendPlayerRoundEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.PlayerRoundEvents); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send player round events", err)
	}

	if err := h.batchSender.SendPlayerMatchEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.PlayerMatchEvents); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send player match events", err)
	}

	// Send aim tracking events
	if err := h.batchSender.SendAimEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.AimEvents); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send aim events", err)
	}

	if err := h.batchSender.SendAimWeaponEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.AimWeaponEvents); err != nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send aim weapon events", err)
	}

	return nil
}
