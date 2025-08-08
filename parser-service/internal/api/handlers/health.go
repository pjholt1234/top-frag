package handlers

import (
	"net/http"
	"runtime"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/sirupsen/logrus"
)

type HealthHandler struct {
	logger *logrus.Logger
	startTime time.Time
}

func NewHealthHandler(logger *logrus.Logger) *HealthHandler {
	return &HealthHandler{
		logger:    logger,
		startTime: time.Now(),
	}
}

// GET /api/health
// What this does:
// Returns basic health status including uptime and memory usage
// Useful for monitoring the server's health

func (h *HealthHandler) HandleHealth(c *gin.Context) {
	var m runtime.MemStats
	runtime.ReadMemStats(&m)

	uptime := time.Since(h.startTime)

	health := gin.H{
		"status": "healthy",
		"timestamp": time.Now().UTC().Format(time.RFC3339),
		"uptime": uptime.String(),
		"memory": gin.H{
			"alloc":     m.Alloc,
			"total_alloc": m.TotalAlloc,
			"sys":        m.Sys,
			"num_gc":     m.NumGC,
		},
		"goroutines": runtime.NumGoroutine(),
	}

	c.JSON(http.StatusOK, health)
}

func (h *HealthHandler) HandleReadiness(c *gin.Context) {
	readiness := gin.H{
		"status": "ready",
		"timestamp": time.Now().UTC().Format(time.RFC3339),
		"checks": gin.H{
			"demo_parser": "ok",
			"batch_sender": "ok",
		},
	}

	c.JSON(http.StatusOK, readiness)
} 