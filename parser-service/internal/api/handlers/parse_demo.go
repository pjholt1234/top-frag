package handlers

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	"bytes"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/sirupsen/logrus"
	"parser-service/internal/config"
	"parser-service/internal/parser"
	"parser-service/internal/types"
)

type ParseDemoHandler struct {
	config     *config.Config
	logger     *logrus.Logger
	demoParser *parser.DemoParser
	batchSender *parser.BatchSender
	jobs       map[string]*types.ProcessingJob
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
// Receives demo parsing request with demo path and callback URLs
// Validates the request (required fields, file existence)
// Creates a job with unique ID
// Starts processing in background using goroutine
// Returns immediately with job ID (non-blocking)

// gin.Context: Represents the HTTP request and response
func (h *ParseDemoHandler) HandleParseDemo(c *gin.Context) {
	var req types.ParseDemoRequest
	//c.ShouldBindJSON(): Binds JSON request body to struct
	if err := c.ShouldBindJSON(&req); err != nil {
		h.logger.WithError(err).Error("Failed to bind request")
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"error":   "Invalid request format",
		})
		return
	}

	if err := h.validateRequest(&req); err != nil {
		h.logger.WithError(err).Error("Request validation failed")
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

	job := &types.ProcessingJob{
		JobID:               req.JobID,
		DemoPath:            req.DemoPath,
		ProgressCallbackURL: req.ProgressCallbackURL,
		CompletionCallbackURL: req.CompletionCallbackURL,
		Status:              types.StatusPending,
		Progress:            0,
		CurrentStep:         "Initializing",
		StartTime:           time.Now(),
	}

	h.jobs[req.JobID] = job
    
	// go h.processDemo(): Starts background processing
	go h.processDemo(context.Background(), job)

	c.JSON(http.StatusAccepted, types.ParseDemoResponse{
		Success: true,
		JobID:   req.JobID,
		Message: "Demo parsing started",
	})
}

func (h *ParseDemoHandler) validateRequest(req *types.ParseDemoRequest) error {
	if req.DemoPath == "" {
		return fmt.Errorf("demo_path is required")
	}

	if req.ProgressCallbackURL == "" {
		return fmt.Errorf("progress_callback_url is required")
	}

	if req.CompletionCallbackURL == "" {
		return fmt.Errorf("completion_callback_url is required")
	}

	return nil
}

// Handles the main demo parsing logic
// Parses the demo file and sends the data to the batch sender
// Sends progress updates to the callback URLs
// Handles errors and sends error messages to the callback URLs
func (h *ParseDemoHandler) processDemo(ctx context.Context, job *types.ProcessingJob) {
	defer func() {
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

	job.Status = types.StatusProcessing
	job.CurrentStep = "Parsing demo file"

	if err := h.sendProgressUpdate(ctx, job); err != nil {
		h.logger.WithError(err).Error("Failed to send initial progress update")
	}

	parsedData, err := h.demoParser.ParseDemo(ctx, job.DemoPath, func(update types.ProgressUpdate) {
		job.Progress = update.Progress
		job.CurrentStep = update.CurrentStep

		if err := h.sendProgressUpdate(ctx, job); err != nil {
			h.logger.WithError(err).Error("Failed to send progress update")
		}
	})

	if err != nil {
		h.logger.WithFields(logrus.Fields{
			"job_id": job.JobID,
			"error":  err,
		}).Error("Demo parsing failed")

		job.Status = types.StatusFailed
		job.ErrorMessage = err.Error()

		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			h.logger.WithError(err).Error("Failed to send error to Laravel")
		}

		return
	}

	job.MatchData = parsedData

	job.Status = types.StatusProcessing
	job.CurrentStep = "Sending data to Laravel"
	job.Progress = 95

	if err := h.sendProgressUpdate(ctx, job); err != nil {
		h.logger.WithError(err).Error("Failed to send progress update")
	}

	if err := h.batchSender.SendMatchMetadata(ctx, job.JobID, job.CompletionCallbackURL, parsedData); err != nil {
		h.logger.WithError(err).Error("Failed to send match metadata")
		job.Status = types.StatusFailed
		job.ErrorMessage = "Failed to send match metadata"
		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			h.logger.WithError(err).Error("Failed to send error to Laravel")
		}
		return
	}

	if err := h.sendAllEvents(ctx, job, parsedData); err != nil {
		h.logger.WithError(err).Error("Failed to send events")
		job.Status = types.StatusFailed
		job.ErrorMessage = "Failed to send events"
		if err := h.batchSender.SendError(ctx, job.JobID, job.CompletionCallbackURL, job.ErrorMessage); err != nil {
			h.logger.WithError(err).Error("Failed to send error to Laravel")
		}
		return
	}

	if err := h.batchSender.SendCompletion(ctx, job.JobID, job.CompletionCallbackURL); err != nil {
		h.logger.WithError(err).Error("Failed to send completion signal")
		job.Status = types.StatusFailed
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

func (h *ParseDemoHandler) sendAllEvents(ctx context.Context, job *types.ProcessingJob, parsedData *types.ParsedDemoData) error {
	if err := h.batchSender.SendRoundEvents(ctx, job.JobID, parsedData.RoundEvents); err != nil {
		return fmt.Errorf("failed to send round events: %w", err)
	}

	if err := h.batchSender.SendDamageEvents(ctx, job.JobID, parsedData.DamageEvents); err != nil {
		return fmt.Errorf("failed to send damage events: %w", err)
	}

	if err := h.batchSender.SendGrenadeEvents(ctx, job.JobID, parsedData.GrenadeEvents); err != nil {
		return fmt.Errorf("failed to send grenade events: %w", err)
	}

	if err := h.batchSender.SendGunfightEvents(ctx, job.JobID, parsedData.GunfightEvents); err != nil {
		return fmt.Errorf("failed to send gunfight events: %w", err)
	}

	return nil
}

// GET /api/job/:job_id
// What this does:
// Retrieves the status of a specific job by its ID
// Returns job details including status, progress, and error message
// Useful for tracking the status of a demo parsing job

func (h *ParseDemoHandler) GetJobStatus(c *gin.Context) {
	jobID := c.Param("job_id")
	if jobID == "" {
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"error":   "Job ID is required",
		})
		return
	}

	job, exists := h.jobs[jobID]
	if !exists {
		c.JSON(http.StatusNotFound, gin.H{
			"success": false,
			"error":   "Job not found",
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"job": gin.H{
			"job_id":        job.JobID,
			"status":        job.Status,
			"progress":      job.Progress,
			"current_step":  job.CurrentStep,
			"error_message": job.ErrorMessage,
			"start_time":    job.StartTime,
		},
	})
} 