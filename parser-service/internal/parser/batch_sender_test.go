package parser

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"parser-service/internal/config"
	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
)

// Helper function to create a test ProgressManager
func createTestProgressManager() *ProgressManager {
	logger := logrus.New()
	progressCallback := func(update types.ProgressUpdate) {
		// No-op for testing
	}
	return NewProgressManager(logger, progressCallback, 100*time.Millisecond)
}

func TestNewBatchSender(t *testing.T) {
	cfg := &config.Config{
		Batch: config.BatchConfig{
			HTTPTimeout: 30 * time.Second,
		},
	}
	logger := logrus.New()

	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

	tests := []struct {
		name          string
		completionURL string
		expectedBase  string
		expectError   bool
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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
			IsFirstKill:    false,
		},
		{
			RoundNumber:    1,
			Player1SteamID: "steam_789",
			Player2SteamID: "steam_012",
			IsFirstKill:    false,
		},
		{
			RoundNumber:    2,
			Player1SteamID: "steam_345",
			Player2SteamID: "steam_678",
			IsFirstKill:    false,
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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

	ctx := context.Background()
	err := sender.SendGunfightEvents(ctx, "test-job-123", "http://localhost:8080", []types.GunfightEvent{})

	if err != nil {
		t.Errorf("Expected no error for empty events, got: %v", err)
	}
}

func TestBatchSender_SendGunfightEvents_IsFirstKillField(t *testing.T) {
	// Create a test server that captures the request body
	var capturedBody []byte
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Check if the request is for the gunfight events endpoint
		if r.Method == "POST" && strings.Contains(r.URL.Path, "/api/job/") && strings.Contains(r.URL.Path, "/event/gunfight") {
			capturedBody, _ = io.ReadAll(r.Body)
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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

	// Set the baseURL for the test
	baseURL, err := sender.extractBaseURL(server.URL)
	if err != nil {
		t.Fatalf("Failed to extract base URL: %v", err)
	}
	sender.baseURL = baseURL

	// Create test events with is_first_kill field
	events := []types.GunfightEvent{
		{
			RoundNumber:    1,
			Player1SteamID: "steam_123",
			Player2SteamID: "steam_456",
			IsFirstKill:    true, // First kill of the round
		},
		{
			RoundNumber:    1,
			Player1SteamID: "steam_789",
			Player2SteamID: "steam_012",
			IsFirstKill:    false, // Not first kill
		},
	}

	ctx := context.Background()
	err = sender.SendGunfightEvents(ctx, "test-job-123", server.URL, events)

	if err != nil {
		t.Errorf("Expected no error, got: %v", err)
	}

	// Parse the captured request body
	var payload map[string]interface{}
	if err := json.Unmarshal(capturedBody, &payload); err != nil {
		t.Fatalf("Failed to parse request body: %v", err)
	}

	// Check that the data field exists
	data, ok := payload["data"].([]interface{})
	if !ok {
		t.Fatal("Expected 'data' field in payload")
	}

	if len(data) != 2 {
		t.Fatalf("Expected 2 events in data, got %d", len(data))
	}

	// Check first event (should be first kill)
	event1, ok := data[0].(map[string]interface{})
	if !ok {
		t.Fatal("Expected first event to be a map")
	}

	isFirstKill1, exists := event1["is_first_kill"]
	if !exists {
		t.Error("Expected 'is_first_kill' field in first event")
	}

	if isFirstKill1 != true {
		t.Errorf("Expected first event is_first_kill to be true, got %v", isFirstKill1)
	}

	// Check second event (should not be first kill)
	event2, ok := data[1].(map[string]interface{})
	if !ok {
		t.Fatal("Expected second event to be a map")
	}

	isFirstKill2, exists := event2["is_first_kill"]
	if !exists {
		t.Error("Expected 'is_first_kill' field in second event")
	}

	if isFirstKill2 != false {
		t.Errorf("Expected second event is_first_kill to be false, got %v", isFirstKill2)
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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

	// Set the baseURL for the test
	baseURL, err := sender.extractBaseURL(server.URL)
	if err != nil {
		t.Fatalf("Failed to extract base URL: %v", err)
	}
	sender.baseURL = baseURL

	// Create test events
	events := []types.GrenadeEvent{
		{
			RoundNumber:       1,
			PlayerSteamID:     "steam_123",
			GrenadeType:       "flash",
			FlashLeadsToKill:  false,
			FlashLeadsToDeath: false,
		},
		{
			RoundNumber:       1,
			PlayerSteamID:     "steam_456",
			GrenadeType:       "smoke",
			FlashLeadsToKill:  false,
			FlashLeadsToDeath: false,
		},
		{
			RoundNumber:       2,
			PlayerSteamID:     "steam_789",
			GrenadeType:       "he",
			FlashLeadsToKill:  false,
			FlashLeadsToDeath: false,
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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

	// Set the baseURL for the test
	baseURL, err := sender.extractBaseURL(server.URL)
	if err != nil {
		t.Fatalf("Failed to extract base URL: %v", err)
	}
	sender.baseURL = baseURL

	// Create test events
	events := []types.DamageEvent{
		{
			RoundNumber:     1,
			AttackerSteamID: "steam_123",
			VictimSteamID:   "steam_456",
			Damage:          25,
		},
		{
			RoundNumber:     1,
			AttackerSteamID: "steam_789",
			VictimSteamID:   "steam_012",
			Damage:          50,
		},
		{
			RoundNumber:     2,
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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
	sender := NewBatchSender(cfg, logger, createTestProgressManager())

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
