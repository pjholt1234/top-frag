package parser

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/sirupsen/logrus"
	"parser-service/internal/config"
	"parser-service/internal/types"
)

func TestNewBatchSender(t *testing.T) {
	cfg := &config.Config{
		Batch: config.BatchConfig{
			HTTPTimeout: 30 * time.Second,
		},
	}
	logger := logrus.New()
	
	sender := NewBatchSender(cfg, logger)
	
	if sender == nil {
		t.Fatal("Expected BatchSender to be created, got nil")
	}
	
	if sender.config != cfg {
		t.Error("Expected config to be set correctly")
	}
	
	if sender.logger != logger {
		t.Error("Expected logger to be set correctly")
	}
	
	if sender.client == nil {
		t.Error("Expected HTTP client to be initialized")
	}
	
	if sender.client.Timeout != 30*time.Second {
		t.Errorf("Expected timeout 30s, got %v", sender.client.Timeout)
	}
}

func TestBatchSender_ExtractBaseURL(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	tests := []struct {
		name           string
		completionURL  string
		expectedBase   string
		expectError    bool
	}{
		{
			name:          "valid HTTPS URL",
			completionURL: "https://api.example.com/callback/complete",
			expectedBase:  "https://api.example.com",
			expectError:   false,
		},
		{
			name:          "valid HTTP URL",
			completionURL: "http://localhost:8080/callback/complete",
			expectedBase:  "http://localhost:8080",
			expectError:   false,
		},
		{
			name:          "URL with path",
			completionURL: "https://api.example.com/v1/jobs/123/complete",
			expectedBase:  "https://api.example.com",
			expectError:   false,
		},
		{
			name:          "URL with query parameters",
			completionURL: "https://api.example.com/callback?job_id=123",
			expectedBase:  "https://api.example.com",
			expectError:   false,
		},
		{
			name:          "invalid URL",
			completionURL: "not-a-url",
			expectedBase:  "",
			expectError:   true,
		},
		{
			name:          "empty URL",
			completionURL: "",
			expectedBase:  "",
			expectError:   true,
		},
	}
	
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			baseURL, err := sender.extractBaseURL(tt.completionURL)
			
			if tt.expectError && err == nil {
				t.Error("Expected error but got none")
			}
			
			if !tt.expectError && err != nil {
				t.Errorf("Expected no error but got: %v", err)
			}
			
			if !tt.expectError && baseURL != tt.expectedBase {
				t.Errorf("Expected base URL '%s', got '%s'", tt.expectedBase, baseURL)
			}
		})
	}
}

func TestBatchSender_SendGunfightEvents(t *testing.T) {
	// Create a test server that handles the specific routes
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Check if the request is for the gunfight events endpoint
		if r.Method == "POST" && strings.Contains(r.URL.Path, "/api/job/") && strings.Contains(r.URL.Path, "/event/gunfight") {
			w.WriteHeader(http.StatusOK)
			w.Write([]byte(`{"success": true}`))
		} else {
			w.WriteHeader(http.StatusNotFound)
		}
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			GunfightEventsSize: 2,
			HTTPTimeout:        30 * time.Second,
			RetryAttempts:      3,
			RetryDelay:         1 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	// Set the baseURL for the test
	baseURL, err := sender.extractBaseURL(server.URL)
	if err != nil {
		t.Fatalf("Failed to extract base URL: %v", err)
	}
	sender.baseURL = baseURL
	
	// Create test events
	events := []types.GunfightEvent{
		{
			RoundNumber:    1,
			Player1SteamID: "steam_123",
			Player2SteamID: "steam_456",
		},
		{
			RoundNumber:    1,
			Player1SteamID: "steam_789",
			Player2SteamID: "steam_012",
		},
		{
			RoundNumber:    2,
			Player1SteamID: "steam_345",
			Player2SteamID: "steam_678",
		},
	}
	
	ctx := context.Background()
	err = sender.SendGunfightEvents(ctx, "test-job-123", server.URL, events)
	
	if err != nil {
		t.Errorf("Expected no error, got: %v", err)
	}
}

func TestBatchSender_SendGunfightEvents_Empty(t *testing.T) {
	cfg := &config.Config{
		Batch: config.BatchConfig{
			HTTPTimeout: 30 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	ctx := context.Background()
	err := sender.SendGunfightEvents(ctx, "test-job-123", "http://localhost:8080", []types.GunfightEvent{})
	
	if err != nil {
		t.Errorf("Expected no error for empty events, got: %v", err)
	}
}

func TestBatchSender_SendGrenadeEvents(t *testing.T) {
	// Create a test server that handles the specific routes
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Check if the request is for the grenade events endpoint
		if r.Method == "POST" && strings.Contains(r.URL.Path, "/api/job/") && strings.Contains(r.URL.Path, "/event/grenade") {
			w.WriteHeader(http.StatusOK)
			w.Write([]byte(`{"success": true}`))
		} else {
			w.WriteHeader(http.StatusNotFound)
		}
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			GrenadeEventsSize: 2,
			HTTPTimeout:       30 * time.Second,
			RetryAttempts:     3,
			RetryDelay:        1 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	// Set the baseURL for the test
	baseURL, err := sender.extractBaseURL(server.URL)
	if err != nil {
		t.Fatalf("Failed to extract base URL: %v", err)
	}
	sender.baseURL = baseURL
	
	// Create test events
	events := []types.GrenadeEvent{
		{
			RoundNumber:   1,
			PlayerSteamID: "steam_123",
			GrenadeType:   "flash",
		},
		{
			RoundNumber:   1,
			PlayerSteamID: "steam_456",
			GrenadeType:   "smoke",
		},
		{
			RoundNumber:   2,
			PlayerSteamID: "steam_789",
			GrenadeType:   "he",
		},
	}
	
	ctx := context.Background()
	err = sender.SendGrenadeEvents(ctx, "test-job-123", server.URL, events)
	
	if err != nil {
		t.Errorf("Expected no error, got: %v", err)
	}
}

func TestBatchSender_SendDamageEvents(t *testing.T) {
	// Create a test server that handles the specific routes
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Check if the request is for the damage events endpoint
		if r.Method == "POST" && strings.Contains(r.URL.Path, "/api/job/") && strings.Contains(r.URL.Path, "/event/damage") {
			w.WriteHeader(http.StatusOK)
			w.Write([]byte(`{"success": true}`))
		} else {
			w.WriteHeader(http.StatusNotFound)
		}
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			DamageEventsSize: 2,
			HTTPTimeout:      30 * time.Second,
			RetryAttempts:    3,
			RetryDelay:       1 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	// Set the baseURL for the test
	baseURL, err := sender.extractBaseURL(server.URL)
	if err != nil {
		t.Fatalf("Failed to extract base URL: %v", err)
	}
	sender.baseURL = baseURL
	
	// Create test events
	events := []types.DamageEvent{
		{
			RoundNumber:      1,
			AttackerSteamID: "steam_123",
			VictimSteamID:   "steam_456",
			Damage:          25,
		},
		{
			RoundNumber:      1,
			AttackerSteamID: "steam_789",
			VictimSteamID:   "steam_012",
			Damage:          50,
		},
		{
			RoundNumber:      2,
			AttackerSteamID: "steam_345",
			VictimSteamID:   "steam_678",
			Damage:          75,
		},
	}
	
	ctx := context.Background()
	err = sender.SendDamageEvents(ctx, "test-job-123", server.URL, events)
	
	if err != nil {
		t.Errorf("Expected no error, got: %v", err)
	}
}

func TestBatchSender_SendRoundEvents(t *testing.T) {
	// Create a test server that handles the specific routes
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Check if the request is for the round events endpoint
		if r.Method == "POST" && strings.Contains(r.URL.Path, "/api/job/") && strings.Contains(r.URL.Path, "/event/round") {
			w.WriteHeader(http.StatusOK)
			w.Write([]byte(`{"success": true}`))
		} else {
			w.WriteHeader(http.StatusNotFound)
		}
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			HTTPTimeout:   30 * time.Second,
			RetryAttempts: 3,
			RetryDelay:    1 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	// Set the baseURL for the test
	baseURL, err := sender.extractBaseURL(server.URL)
	if err != nil {
		t.Fatalf("Failed to extract base URL: %v", err)
	}
	sender.baseURL = baseURL
	
	// Create test events
	events := []types.RoundEvent{
		{
			RoundNumber: 1,
			EventType:   "start",
		},
		{
			RoundNumber: 1,
			EventType:   "end",
			Winner:      stringPtr("T"),
		},
		{
			RoundNumber: 2,
			EventType:   "start",
		},
	}
	
	ctx := context.Background()
	err = sender.SendRoundEvents(ctx, "test-job-123", server.URL, events)
	
	if err != nil {
		t.Errorf("Expected no error, got: %v", err)
	}
}

func TestBatchSender_SendCompletion(t *testing.T) {
	// Create a test server
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			HTTPTimeout: 30 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	ctx := context.Background()
	err := sender.SendCompletion(ctx, "test-job-123", server.URL)
	
	if err != nil {
		t.Errorf("Expected no error, got: %v", err)
	}
}

func TestBatchSender_SendError(t *testing.T) {
	// Create a test server
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			HTTPTimeout: 30 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	ctx := context.Background()
	err := sender.SendError(ctx, "test-job-123", server.URL, "Test error message")
	
	if err != nil {
		t.Errorf("Expected no error, got: %v", err)
	}
}

func TestBatchSender_SendRequest_Error(t *testing.T) {
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			HTTPTimeout: 30 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	ctx := context.Background()
	err := sender.sendRequest(ctx, "http://invalid-url-that-does-not-exist", map[string]string{"test": "data"})
	
	if err == nil {
		t.Error("Expected error for invalid URL, got none")
	}
}

func TestBatchSender_SendRequest_ServerError(t *testing.T) {
	// Create a test server that returns an error
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			HTTPTimeout: 30 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	ctx := context.Background()
	err := sender.sendRequest(ctx, server.URL, map[string]string{"test": "data"})
	
	if err == nil {
		t.Error("Expected error for server error, got none")
	}
}

func TestBatchSender_SendRequestWithRetry(t *testing.T) {
	attempts := 0
	// Create a test server that fails twice then succeeds
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		attempts++
		if attempts < 3 {
			w.WriteHeader(http.StatusInternalServerError)
		} else {
			w.WriteHeader(http.StatusOK)
		}
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			RetryAttempts: 3,
			RetryDelay:    10 * time.Millisecond,
			HTTPTimeout:   30 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	ctx := context.Background()
	err := sender.sendRequestWithRetry(ctx, server.URL, map[string]string{"test": "data"})
	
	if err != nil {
		t.Errorf("Expected no error after retries, got: %v", err)
	}
	
	if attempts != 3 {
		t.Errorf("Expected 3 attempts, got %d", attempts)
	}
}

func TestBatchSender_SendRequestWithRetry_AllFail(t *testing.T) {
	// Create a test server that always fails
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
	}))
	defer server.Close()
	
	cfg := &config.Config{
		Server: config.ServerConfig{
			APIKey: "test-api-key",
		},
		Batch: config.BatchConfig{
			RetryAttempts: 2,
			RetryDelay:    10 * time.Millisecond,
			HTTPTimeout:   30 * time.Second,
		},
	}
	logger := logrus.New()
	sender := NewBatchSender(cfg, logger)
	
	ctx := context.Background()
	err := sender.sendRequestWithRetry(ctx, server.URL, map[string]string{"test": "data"})
	
	if err == nil {
		t.Error("Expected error after all retries failed, got none")
	}
}

// Helper function for creating string pointers
func stringPtr(s string) *string {
	return &s
} 