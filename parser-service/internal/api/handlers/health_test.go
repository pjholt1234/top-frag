package handlers

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
)

func TestNewHealthHandler(t *testing.T) {
	logger := logrus.New()
	handler := NewHealthHandler(logger)

	assert.NotNil(t, handler)
	assert.Equal(t, logger, handler.logger)
	assert.True(t, time.Since(handler.startTime) < time.Second)
}

func TestHealthHandler_HandleHealth(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create a test logger
	logger := logrus.New()
	handler := NewHealthHandler(logger)

	// Create a test request
	w := httptest.NewRecorder()
	c, _ := gin.CreateTestContext(w)

	// Call the handler
	handler.HandleHealth(c)

	// Assert response
	assert.Equal(t, http.StatusOK, w.Code)

	// Parse response body
	var response map[string]interface{}
	err := json.Unmarshal(w.Body.Bytes(), &response)
	assert.NoError(t, err)

	// Check required fields
	assert.Equal(t, "healthy", response["status"])
	assert.Contains(t, response, "timestamp")
	assert.Contains(t, response, "uptime")
	assert.Contains(t, response, "memory")
	assert.Contains(t, response, "goroutines")

	// Check memory fields
	memory, ok := response["memory"].(map[string]interface{})
	assert.True(t, ok)
	assert.Contains(t, memory, "alloc")
	assert.Contains(t, memory, "total_alloc")
	assert.Contains(t, memory, "sys")
	assert.Contains(t, memory, "num_gc")

	// Check uptime is reasonable (should be very small for test)
	uptime, ok := response["uptime"].(string)
	assert.True(t, ok)
	assert.NotEmpty(t, uptime)

	// Check goroutines is a number
	goroutines, ok := response["goroutines"].(float64)
	assert.True(t, ok)
	assert.Greater(t, goroutines, float64(0))
}

func TestHealthHandler_HandleReadiness(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create a test logger
	logger := logrus.New()
	handler := NewHealthHandler(logger)

	// Create a test request
	w := httptest.NewRecorder()
	c, _ := gin.CreateTestContext(w)

	// Call the handler
	handler.HandleReadiness(c)

	// Assert response
	assert.Equal(t, http.StatusOK, w.Code)

	// Parse response body
	var response map[string]interface{}
	err := json.Unmarshal(w.Body.Bytes(), &response)
	assert.NoError(t, err)

	// Check required fields
	assert.Equal(t, "ready", response["status"])
	assert.Contains(t, response, "timestamp")
	assert.Contains(t, response, "checks")

	// Check checks field
	checks, ok := response["checks"].(map[string]interface{})
	assert.True(t, ok)
	assert.Equal(t, "ok", checks["demo_parser"])
	assert.Equal(t, "ok", checks["batch_sender"])
}

func TestHealthHandler_HandleHealth_MultipleCalls(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create a test logger
	logger := logrus.New()
	handler := NewHealthHandler(logger)

	// Make multiple calls and verify uptime increases
	var responses []map[string]interface{}

	for i := 0; i < 3; i++ {
		w := httptest.NewRecorder()
		c, _ := gin.CreateTestContext(w)

		handler.HandleHealth(c)

		assert.Equal(t, http.StatusOK, w.Code)

		var response map[string]interface{}
		err := json.Unmarshal(w.Body.Bytes(), &response)
		assert.NoError(t, err)

		responses = append(responses, response)

		// Small delay to ensure uptime increases
		time.Sleep(10 * time.Millisecond)
	}

	// Verify all responses have the same structure
	for i, response := range responses {
		assert.Equal(t, "healthy", response["status"], "Response %d should have healthy status", i)
		assert.Contains(t, response, "timestamp", "Response %d should have timestamp", i)
		assert.Contains(t, response, "uptime", "Response %d should have uptime", i)
		assert.Contains(t, response, "memory", "Response %d should have memory", i)
		assert.Contains(t, response, "goroutines", "Response %d should have goroutines", i)
	}
}

func TestHealthHandler_HandleReadiness_MultipleCalls(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create a test logger
	logger := logrus.New()
	handler := NewHealthHandler(logger)

	// Make multiple calls
	for i := 0; i < 3; i++ {
		w := httptest.NewRecorder()
		c, _ := gin.CreateTestContext(w)

		handler.HandleReadiness(c)

		assert.Equal(t, http.StatusOK, w.Code)

		var response map[string]interface{}
		err := json.Unmarshal(w.Body.Bytes(), &response)
		assert.NoError(t, err)

		assert.Equal(t, "ready", response["status"])
		assert.Contains(t, response, "timestamp")
		assert.Contains(t, response, "checks")

		checks, ok := response["checks"].(map[string]interface{})
		assert.True(t, ok)
		assert.Equal(t, "ok", checks["demo_parser"])
		assert.Equal(t, "ok", checks["batch_sender"])
	}
}

func TestHealthHandler_ConcurrentAccess(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create a test logger
	logger := logrus.New()
	handler := NewHealthHandler(logger)

	// Test concurrent access
	done := make(chan bool, 10)
	results := make(chan map[string]interface{}, 10)

	for i := 0; i < 10; i++ {
		go func() {
			w := httptest.NewRecorder()
			c, _ := gin.CreateTestContext(w)

			handler.HandleHealth(c)

			assert.Equal(t, http.StatusOK, w.Code)

			var response map[string]interface{}
			err := json.Unmarshal(w.Body.Bytes(), &response)
			assert.NoError(t, err)

			results <- response
			done <- true
		}()
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}

	// Verify all responses
	close(results)
	for response := range results {
		assert.Equal(t, "healthy", response["status"])
		assert.Contains(t, response, "timestamp")
		assert.Contains(t, response, "uptime")
		assert.Contains(t, response, "memory")
		assert.Contains(t, response, "goroutines")
	}
}
