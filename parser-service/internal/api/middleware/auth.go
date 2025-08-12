package middleware

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
)

// APIKeyAuth middleware validates the API key in the request headers
func APIKeyAuth(apiKey string) gin.HandlerFunc {
	return func(c *gin.Context) {
		// Skip authentication for health endpoints
		if c.Request.URL.Path == "/health" || c.Request.URL.Path == "/ready" {
			c.Next()
			return
		}

		// Get API key from headers
		authHeader := c.GetHeader("X-API-Key")
		if authHeader == "" {
			authHeader = c.GetHeader("Authorization")
			// Remove "Bearer " prefix if present
			if strings.HasPrefix(authHeader, "Bearer ") {
				authHeader = strings.TrimPrefix(authHeader, "Bearer ")
			}
		}

		// Check if API key is provided
		if authHeader == "" {
			c.JSON(http.StatusUnauthorized, gin.H{
				"error":   "API key is required",
				"message": "Please provide a valid API key in the X-API-Key or Authorization header",
			})
			c.Abort()
			return
		}

		// Validate API key
		if apiKey == "" {
			c.JSON(http.StatusInternalServerError, gin.H{
				"error":   "API authentication not configured",
				"message": "Please configure API key in your environment",
			})
			c.Abort()
			return
		}

		if authHeader != apiKey {
			c.JSON(http.StatusUnauthorized, gin.H{
				"error":   "Invalid API key",
				"message": "The provided API key is not valid",
			})
			c.Abort()
			return
		}

		c.Next()
	}
}
