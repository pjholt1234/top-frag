package server

import (
	"fmt"
	"net/http"
	"sync"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/sirupsen/logrus"
)

// MockServer acts as a Laravel backend to receive parser service callbacks
type MockServer struct {
	logger *logrus.Logger
	server *http.Server
	router *gin.Engine
	port   int

	// Storage for received data
	jobs      map[string]*JobData
	eventData map[string]map[string][]interface{} // jobID -> eventType -> events
	mutex     sync.RWMutex

	// Channels for signaling
	completedJobs chan string
}

type JobData struct {
	JobID        string      `json:"job_id"`
	Status       string      `json:"status"`
	Progress     int         `json:"progress"`
	CurrentStep  string      `json:"current_step"`
	Match        interface{} `json:"match,omitempty"`
	Players      interface{} `json:"players,omitempty"`
	ErrorMessage *string     `json:"error_message,omitempty"`
}

type EventPayload struct {
	Data []interface{} `json:"data"`
}

// NewMockServer creates a new mock server instance
func NewMockServer(logger *logrus.Logger, port int) *MockServer {
	gin.SetMode(gin.ReleaseMode)

	ms := &MockServer{
		logger:        logger,
		port:          port,
		jobs:          make(map[string]*JobData),
		eventData:     make(map[string]map[string][]interface{}),
		mutex:         sync.RWMutex{},
		completedJobs: make(chan string, 100),
	}

	ms.setupRoutes()
	return ms
}

func (ms *MockServer) setupRoutes() {
	ms.router = gin.New()
	ms.router.Use(gin.Recovery())

	// Laravel-compatible endpoints that parser service will POST to
	api := ms.router.Group("/api")
	{
		// Progress callback - receives job status updates (job_id in payload)
		api.POST("/demo-parser/progress", ms.handleProgressCallback)

		// Completion callback - receives final job completion (job_id in payload)
		api.POST("/demo-parser/completion", ms.handleCompletionCallback)

		// Event data endpoints - receives parsed event data (job_id in URL)
		api.POST("/job/:jobID/event/:eventType", ms.handleEventData)
	}

	// Test endpoints for our integration test to query data
	test := ms.router.Group("/test")
	{
		test.GET("/job/:jobID/status", ms.getJobStatus)
		test.GET("/job/:jobID/event/:eventType", ms.getEventData)
		test.GET("/job/:jobID/completed", ms.waitForCompletion)
	}
}

// Start starts the mock server
func (ms *MockServer) Start() error {
	ms.server = &http.Server{
		Addr:         fmt.Sprintf(":%d", ms.port),
		Handler:      ms.router,
		ReadTimeout:  30 * time.Second,
		WriteTimeout: 30 * time.Second,
	}

	ms.logger.WithField("port", ms.port).Info("Starting mock Laravel server")

	go func() {
		if err := ms.server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			ms.logger.WithError(err).Error("Mock server failed to start")
		}
	}()

	// Wait a moment for server to start
	time.Sleep(100 * time.Millisecond)
	return nil
}

// Stop stops the mock server
func (ms *MockServer) Stop() error {
	if ms.server != nil {
		return ms.server.Close()
	}
	return nil
}

// GetBaseURL returns the base URL for callback endpoints
func (ms *MockServer) GetBaseURL() string {
	return fmt.Sprintf("http://localhost:%d", ms.port)
}

// Progress callback handler - receives job progress updates from parser service
func (ms *MockServer) handleProgressCallback(c *gin.Context) {
	var jobData JobData
	if err := c.ShouldBindJSON(&jobData); err != nil {
		ms.logger.WithError(err).Error("Failed to parse progress callback")
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}

	// Job ID comes from the JSON payload
	jobID := jobData.JobID

	ms.mutex.Lock()
	ms.jobs[jobID] = &jobData
	ms.mutex.Unlock()

	ms.logger.WithFields(logrus.Fields{
		"job_id":       jobID,
		"status":       jobData.Status,
		"progress":     jobData.Progress,
		"current_step": jobData.CurrentStep,
	}).Debug("Received progress callback")

	c.JSON(http.StatusOK, gin.H{"success": true})
}

// Completion callback handler - receives final job completion from parser service
func (ms *MockServer) handleCompletionCallback(c *gin.Context) {
	var completionData map[string]interface{}
	if err := c.ShouldBindJSON(&completionData); err != nil {
		ms.logger.WithError(err).Error("Failed to parse completion callback")
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}

	// Job ID comes from the JSON payload
	jobID, ok := completionData["job_id"].(string)
	if !ok {
		ms.logger.Error("Missing or invalid job_id in completion callback")
		c.JSON(http.StatusBadRequest, gin.H{"error": "Missing job_id"})
		return
	}

	ms.mutex.Lock()
	if job, exists := ms.jobs[jobID]; exists {
		job.Status = "completed"
		job.Progress = 100
	} else {
		ms.jobs[jobID] = &JobData{
			JobID:    jobID,
			Status:   "completed",
			Progress: 100,
		}
	}
	ms.mutex.Unlock()

	// Signal that job is completed
	select {
	case ms.completedJobs <- jobID:
	default:
		// Channel is full, but that's okay
	}

	ms.logger.WithField("job_id", jobID).Info("Job completed")
	c.JSON(http.StatusOK, gin.H{"success": true})
}

// Event data handler - receives parsed event data from parser service
func (ms *MockServer) handleEventData(c *gin.Context) {
	jobID := c.Param("jobID")
	eventType := c.Param("eventType")

	var payload EventPayload
	if err := c.ShouldBindJSON(&payload); err != nil {
		ms.logger.WithError(err).Error("Failed to parse event data")
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}

	ms.mutex.Lock()
	if ms.eventData[jobID] == nil {
		ms.eventData[jobID] = make(map[string][]interface{})
	}

	// Append batch data instead of overwriting
	if ms.eventData[jobID][eventType] == nil {
		ms.eventData[jobID][eventType] = make([]interface{}, 0)
	}
	ms.eventData[jobID][eventType] = append(ms.eventData[jobID][eventType], payload.Data...)
	ms.mutex.Unlock()

	totalCount := len(ms.eventData[jobID][eventType])

	ms.logger.WithFields(logrus.Fields{
		"job_id":      jobID,
		"event_type":  eventType,
		"batch_count": len(payload.Data),
		"total_count": totalCount,
	}).Info("Received event data batch")

	// Log sample data for damage events to help debug
	if eventType == "damage" && len(payload.Data) > 0 {
		ms.logger.WithFields(logrus.Fields{
			"job_id":      jobID,
			"event_type":  eventType,
			"sample_data": payload.Data[0],
		}).Info("Sample damage event data")

		// Log first few events with specific fields we're looking for
		for i, event := range payload.Data {
			if i < 3 { // Log first 3 events
				if eventMap, ok := event.(map[string]interface{}); ok {
					ms.logger.WithFields(logrus.Fields{
						"job_id":            jobID,
						"event_index":       i,
						"attacker_steam_id": eventMap["attacker_steam_id"],
						"round_number":      eventMap["round_number"],
						"damage":            eventMap["damage"],
						"victim_steam_id":   eventMap["victim_steam_id"],
					}).Info("Damage event details")
				}
			}
		}
	}

	c.JSON(http.StatusOK, gin.H{"success": true})
}

// Test endpoint to get job status
func (ms *MockServer) getJobStatus(c *gin.Context) {
	jobID := c.Param("jobID")

	ms.mutex.RLock()
	job, exists := ms.jobs[jobID]
	ms.mutex.RUnlock()

	if !exists {
		c.JSON(http.StatusNotFound, gin.H{"error": "Job not found"})
		return
	}

	c.JSON(http.StatusOK, job)
}

// Test endpoint to get event data
func (ms *MockServer) getEventData(c *gin.Context) {
	jobID := c.Param("jobID")
	eventType := c.Param("eventType")

	ms.mutex.RLock()
	jobEvents, jobExists := ms.eventData[jobID]
	if !jobExists {
		ms.mutex.RUnlock()
		c.JSON(http.StatusNotFound, gin.H{"error": "Job not found"})
		return
	}

	events, eventExists := jobEvents[eventType]
	ms.mutex.RUnlock()

	if !eventExists {
		c.JSON(http.StatusNotFound, gin.H{"error": "Event type not found"})
		return
	}

	response := gin.H{
		"job_id":     jobID,
		"event_name": eventType,
		"data":       events,
	}

	c.JSON(http.StatusOK, response)
}

// Test endpoint to wait for job completion
func (ms *MockServer) waitForCompletion(c *gin.Context) {
	jobID := c.Param("jobID")

	// Check if already completed
	ms.mutex.RLock()
	if job, exists := ms.jobs[jobID]; exists && job.Status == "completed" {
		ms.mutex.RUnlock()
		c.JSON(http.StatusOK, gin.H{"completed": true})
		return
	}
	ms.mutex.RUnlock()

	// Wait for completion signal with timeout
	timeout := time.After(60 * time.Second)

	for {
		select {
		case completedJobID := <-ms.completedJobs:
			if completedJobID == jobID {
				c.JSON(http.StatusOK, gin.H{"completed": true})
				return
			}
			// Put it back for other waiters
			select {
			case ms.completedJobs <- completedJobID:
			default:
			}
		case <-timeout:
			c.JSON(http.StatusRequestTimeout, gin.H{"error": "Timeout waiting for completion"})
			return
		case <-c.Request.Context().Done():
			c.JSON(http.StatusRequestTimeout, gin.H{"error": "Request cancelled"})
			return
		}
	}
}
