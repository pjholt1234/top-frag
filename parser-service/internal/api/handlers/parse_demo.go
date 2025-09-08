package handlers

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	"bytes"
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
	config      *config.Config
	logger      *logrus.Logger
	demoParser  *parser.DemoParser
	batchSender *parser.BatchSender
	jobs        map[string]*types.ProcessingJob
}

func NewParseDemoHandler(cfg *config.Config, logger *logrus.Logger, demoParser *parser.DemoParser, batchSender *parser.BatchSender) *ParseDemoHandler {
	return &ParseDemoHandler{
		config:      cfg,
		logger:      logger,
		demoParser:  demoParser,
		batchSender: batchSender,
		jobs:        make(map[string]*types.ProcessingJob),
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
		h.logger.WithError(err).Error("Failed to bind file upload request")
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"error":   "Invalid file upload request format",
		})
		return
	}

	// Validate file
	if err := h.validateUploadedFile(req.DemoFile); err != nil {
		h.logger.WithError(err).Error("File validation failed")
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
		h.logger.WithError(err).Error("Failed to save uploaded file")
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
		return fmt.Errorf("demo file is required")
	}

	// Check file extension
	if !strings.HasSuffix(strings.ToLower(file.Filename), ".dem") {
		return fmt.Errorf("invalid file extension, expected .dem file")
	}

	// Check file size
	if file.Size > h.config.Parser.MaxDemoSize {
		return fmt.Errorf("demo file too large: %d bytes (max: %d)", file.Size, h.config.Parser.MaxDemoSize)
	}

	return nil
}

// cleanupTempFile safely removes a temporary file
func (h *ParseDemoHandler) cleanupTempFile(filePath string) {
	if filePath == "" {
		return
	}

	if err := os.Remove(filePath); err != nil {
		h.logger.WithError(err).WithField("temp_file", filePath).Error("Failed to clean up temporary file")
	} else {
		h.logger.WithField("temp_file", filePath).Info("Cleaned up temporary file")
	}
}

// saveUploadedFile saves the uploaded file to a temporary location
func (h *ParseDemoHandler) saveUploadedFile(file *multipart.FileHeader) (string, error) {
	// Create temp directory if it doesn't exist
	if err := os.MkdirAll(h.config.Parser.TempDir, 0755); err != nil {
		return "", fmt.Errorf("failed to create temp directory: %w", err)
	}

	// Generate unique filename
	filename := fmt.Sprintf("demo_%s_%s", uuid.New().String(), file.Filename)
	tempFilePath := filepath.Join(h.config.Parser.TempDir, filename)

	// Open the uploaded file
	src, err := file.Open()
	if err != nil {
		return "", fmt.Errorf("failed to open uploaded file: %w", err)
	}
	defer src.Close()

	// Create the destination file
	dst, err := os.Create(tempFilePath)
	if err != nil {
		return "", fmt.Errorf("failed to create temp file: %w", err)
	}
	defer dst.Close()

	// Copy the file content
	if _, err = io.Copy(dst, src); err != nil {
		return "", fmt.Errorf("failed to copy file content: %w", err)
	}

	h.logger.WithField("temp_file", tempFilePath).Info("Saved uploaded demo file")
	return tempFilePath, nil
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
			h.logger.WithFields(logrus.Fields{
				"job_id": job.JobID,
				"panic":  r,
			}).Error("Panic in demo processing")

			job.Status = types.StatusFailed
			job.ErrorMessage = "Internal processing error"

			if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
				h.logger.WithError(err).Error("Failed to send error to Laravel")
			}
		}
	}()

	h.logger.WithField("job_id", job.JobID).Info("Starting demo processing")

	// Validating
	job.Status = types.StatusValidating
	job.CurrentStep = "Validating demo file"
	job.Progress = 5
	if err := h.sendProgressUpdate(ctx, job); err != nil {
		h.logger.WithError(err).Error("Failed to send validation progress update")
	}

	// Uploading (file was already saved, but we can indicate this step)
	job.Status = types.StatusUploading
	job.CurrentStep = "File uploaded successfully"
	job.Progress = 8
	if err := h.sendProgressUpdate(ctx, job); err != nil {
		h.logger.WithError(err).Error("Failed to send upload progress update")
	}

	// Initializing
	job.Status = types.StatusInitializing
	job.CurrentStep = "Initializing parser"
	job.Progress = 10
	if err := h.sendProgressUpdate(ctx, job); err != nil {
		h.logger.WithError(err).Error("Failed to send initializing progress update")
	}

	// Parsing
	job.Status = types.StatusParsing
	job.CurrentStep = "Parsing demo file"

	if err := h.sendProgressUpdate(ctx, job); err != nil {
		h.logger.WithError(err).Error("Failed to send parsing progress update")
	}

	parsedData, err := h.demoParser.ParseDemo(ctx, job.TempFilePath, func(update types.ProgressUpdate) {
		job.Progress = update.Progress
		job.CurrentStep = update.CurrentStep

		// Update status based on progress
		if update.Progress < 20 {
			job.Status = types.StatusParsing
		} else if update.Progress < 85 {
			job.Status = types.StatusProcessingEvents
		} else {
			job.Status = types.StatusFinalizing
		}

		if err := h.sendProgressUpdate(ctx, job); err != nil {
			h.logger.WithError(err).Error("Failed to send progress update")
		}
	})

	if err != nil {
		h.logger.WithFields(logrus.Fields{
			"job_id": job.JobID,
			"error":  err,
		}).Error("Demo parsing failed")

		job.Status = types.StatusParseFailed
		job.ErrorMessage = err.Error()

		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			h.logger.WithError(err).Error("Failed to send error to Laravel")
		}

		return
	}

	job.MatchData = parsedData

	// Sending metadata via progress callback
	job.Status = types.StatusSendingMetadata
	job.CurrentStep = "Sending match metadata"
	job.Progress = 90

	// Send match and players data via progress callback
	if err := h.sendProgressUpdateWithMatchData(ctx, job, parsedData); err != nil {
		h.logger.WithError(err).Error("Failed to send progress update with match data")
		job.Status = types.StatusCallbackFailed
		job.ErrorMessage = "Failed to send match metadata"
		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			h.logger.WithError(err).Error("Failed to send error to Laravel")
		}
		return
	}

	// Sending events
	job.Status = types.StatusSendingEvents
	job.CurrentStep = "Sending event data"
	job.Progress = 95

	if err := h.sendProgressUpdate(ctx, job); err != nil {
		h.logger.WithError(err).Error("Failed to send progress update")
	}

	if err := h.sendAllEvents(ctx, job, parsedData); err != nil {
		h.logger.WithError(err).Error("Failed to send events")
		job.Status = types.StatusCallbackFailed
		job.ErrorMessage = "Failed to send events"
		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			h.logger.WithError(err).Error("Failed to send error to Laravel")
		}
		return
	}

	// Finalizing
	job.Status = types.StatusFinalizing
	job.CurrentStep = "Finalizing job"
	job.Progress = 98

	if err := h.sendProgressUpdate(ctx, job); err != nil {
		h.logger.WithError(err).Error("Failed to send progress update")
	}

	if err := h.batchSender.SendCompletion(ctx, job.JobID, job.CompletionCallbackURL); err != nil {
		h.logger.WithError(err).Error("Failed to send completion signal")
		job.Status = types.StatusCallbackFailed
		job.ErrorMessage = "Failed to send completion signal"
		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			h.logger.WithError(err).Error("Failed to send error to Laravel")
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
		JobID:       job.JobID,
		Status:      job.Status,
		Progress:    job.Progress,
		CurrentStep: job.CurrentStep,
	}

	if job.ErrorMessage != "" {
		update.ErrorMessage = &job.ErrorMessage
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
		JobID:       job.JobID,
		Status:      job.Status,
		Progress:    job.Progress,
		CurrentStep: job.CurrentStep,
	}

	if job.ErrorMessage != "" {
		update.ErrorMessage = &job.ErrorMessage
	}

	// Create payload with match and players data
	payload := map[string]interface{}{
		"job_id":       update.JobID,
		"status":       update.Status,
		"progress":     update.Progress,
		"current_step": update.CurrentStep,
		"match":        parsedData.Match,
		"players":      parsedData.Players,
	}

	if update.ErrorMessage != nil {
		payload["error_message"] = *update.ErrorMessage
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
	if err := h.batchSender.SendRoundEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.RoundEvents); err != nil {
		return fmt.Errorf("failed to send round events: %w", err)
	}

	if err := h.batchSender.SendDamageEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.DamageEvents); err != nil {
		return fmt.Errorf("failed to send damage events: %w", err)
	}

	if err := h.batchSender.SendGrenadeEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.GrenadeEvents); err != nil {
		return fmt.Errorf("failed to send grenade events: %w", err)
	}

	if err := h.batchSender.SendGunfightEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.GunfightEvents); err != nil {
		return fmt.Errorf("failed to send gunfight events: %w", err)
	}

	if err := h.batchSender.SendPlayerRoundEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.PlayerRoundEvents); err != nil {
		return fmt.Errorf("failed to send player round events: %w", err)
	}

	if err := h.batchSender.SendPlayerMatchEvents(ctx, job.JobID, job.CompletionCallbackURL, parsedData.PlayerMatchEvents); err != nil {
		return fmt.Errorf("failed to send player match events: %w", err)
	}

	return nil
}
