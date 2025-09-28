package middleware

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/gin-gonic/gin"
	"github.com/stretchr/testify/assert"
)

func TestAPIKeyAuth_ValidAPIKey(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test with valid API key in X-API-Key header
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/test", nil)
	req.Header.Set("X-API-Key", apiKey)
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	assert.Contains(t, w.Body.String(), "success")
}

func TestAPIKeyAuth_ValidAPIKeyInAuthorizationHeader(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test with valid API key in Authorization header
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/test", nil)
	req.Header.Set("Authorization", apiKey)
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	assert.Contains(t, w.Body.String(), "success")
}

func TestAPIKeyAuth_ValidAPIKeyWithBearerPrefix(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test with valid API key in Authorization header with Bearer prefix
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/test", nil)
	req.Header.Set("Authorization", "Bearer "+apiKey)
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	assert.Contains(t, w.Body.String(), "success")
}

func TestAPIKeyAuth_InvalidAPIKey(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test with invalid API key
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/test", nil)
	req.Header.Set("X-API-Key", "invalid-key")
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
	assert.Contains(t, w.Body.String(), "Invalid API key")
	assert.Contains(t, w.Body.String(), "The provided API key is not valid")
}

func TestAPIKeyAuth_MissingAPIKey(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test without API key
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/test", nil)
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
	assert.Contains(t, w.Body.String(), "API key is required")
	assert.Contains(t, w.Body.String(), "Please provide a valid API key")
}

func TestAPIKeyAuth_EmptyAPIKey(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with empty API key
	middleware := APIKeyAuth("")

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test with any API key (should fail because server API key is empty)
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/test", nil)
	req.Header.Set("X-API-Key", "any-key")
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusInternalServerError, w.Code)
	assert.Contains(t, w.Body.String(), "API authentication not configured")
	assert.Contains(t, w.Body.String(), "Please configure API key")
}

func TestAPIKeyAuth_HealthEndpointBypass(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"status": "healthy"})
	})
	router.GET("/ready", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"status": "ready"})
	})

	// Test health endpoint without API key (should work)
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/health", nil)
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	assert.Contains(t, w.Body.String(), "healthy")

	// Test ready endpoint without API key (should work)
	w = httptest.NewRecorder()
	req, _ = http.NewRequest("GET", "/ready", nil)
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	assert.Contains(t, w.Body.String(), "ready")
}

func TestAPIKeyAuth_ProtectedEndpointRequiresAuth(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/api/parse-demo", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test protected endpoint without API key (should fail)
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/parse-demo", nil)
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
	assert.Contains(t, w.Body.String(), "API key is required")
}

func TestAPIKeyAuth_PriorityXAPIKeyOverAuthorization(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test with both headers - X-API-Key should take priority
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/test", nil)
	req.Header.Set("X-API-Key", apiKey)
	req.Header.Set("Authorization", "invalid-key")
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	assert.Contains(t, w.Body.String(), "success")
}

func TestAPIKeyAuth_CaseInsensitiveHeaders(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test with lowercase header names
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/test", nil)
	req.Header.Set("x-api-key", apiKey)
	router.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	assert.Contains(t, w.Body.String(), "success")
}

func TestAPIKeyAuth_ConcurrentAccess(t *testing.T) {
	// Set Gin to test mode
	gin.SetMode(gin.TestMode)

	// Create middleware with test API key
	apiKey := "test-api-key-123"
	middleware := APIKeyAuth(apiKey)

	// Create test router
	router := gin.New()
	router.Use(middleware)
	router.GET("/test", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "success"})
	})

	// Test concurrent access
	done := make(chan bool, 10)
	results := make(chan int, 10)

	for i := 0; i < 10; i++ {
		go func() {
			w := httptest.NewRecorder()
			req, _ := http.NewRequest("GET", "/test", nil)
			req.Header.Set("X-API-Key", apiKey)
			router.ServeHTTP(w, req)

			results <- w.Code
			done <- true
		}()
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}

	// Verify all responses
	close(results)
	for statusCode := range results {
		assert.Equal(t, http.StatusOK, statusCode)
	}
}
