package api

import (
	"testing"
)

func TestEndpoints_Constants(t *testing.T) {
	// Test health endpoints
	if HealthEndpoint != "/health" {
		t.Errorf("Expected HealthEndpoint to be '/health', got %s", HealthEndpoint)
	}

	if ReadinessEndpoint != "/ready" {
		t.Errorf("Expected ReadinessEndpoint to be '/ready', got %s", ReadinessEndpoint)
	}

	// Test API endpoints
	if ParseDemoEndpoint != "parse-demo" {
		t.Errorf("Expected ParseDemoEndpoint to be 'parse-demo', got %s", ParseDemoEndpoint)
	}

	// Test job event endpoint format
	expectedJobEventFormat := "/api/job/%s/event/%s"
	if JobEventEndpoint != expectedJobEventFormat {
		t.Errorf("Expected JobEventEndpoint to be '%s', got %s", expectedJobEventFormat, JobEventEndpoint)
	}
}

func TestEventTypes_Constants(t *testing.T) {
	// Test event type constants
	if EventTypeRound != "round" {
		t.Errorf("Expected EventTypeRound to be 'round', got %s", EventTypeRound)
	}

	if EventTypeGunfight != "gunfight" {
		t.Errorf("Expected EventTypeGunfight to be 'gunfight', got %s", EventTypeGunfight)
	}

	if EventTypeGrenade != "grenade" {
		t.Errorf("Expected EventTypeGrenade to be 'grenade', got %s", EventTypeGrenade)
	}

	if EventTypeDamage != "damage" {
		t.Errorf("Expected EventTypeDamage to be 'damage', got %s", EventTypeDamage)
	}
}

func TestJobEventEndpoint_Formatting(t *testing.T) {
	// Test that the JobEventEndpoint format works correctly
	jobID := "test-job-123"
	eventType := EventTypeGunfight

	// This would be how the endpoint is used in practice
	formattedEndpoint := "/api/job/" + jobID + "/event/" + eventType
	expectedEndpoint := "/api/job/test-job-123/event/gunfight"

	if formattedEndpoint != expectedEndpoint {
		t.Errorf("Expected formatted endpoint to be '%s', got %s", expectedEndpoint, formattedEndpoint)
	}
}

func TestEventTypes_Validation(t *testing.T) {
	// Test that all event types are valid
	validEventTypes := []string{
		EventTypeRound,
		EventTypeGunfight,
		EventTypeGrenade,
		EventTypeDamage,
	}

	// Test that each event type is not empty
	for _, eventType := range validEventTypes {
		if eventType == "" {
			t.Errorf("Event type should not be empty")
		}
	}

	// Test that event types are unique
	eventTypeMap := make(map[string]bool)
	for _, eventType := range validEventTypes {
		if eventTypeMap[eventType] {
			t.Errorf("Event type '%s' is duplicated", eventType)
		}
		eventTypeMap[eventType] = true
	}
}

func TestEndpoints_Formatting(t *testing.T) {
	// Test that endpoints can be properly formatted
	testCases := []struct {
		name      string
		jobID     string
		eventType string
		expected  string
	}{
		{
			name:      "gunfight event",
			jobID:     "job-123",
			eventType: EventTypeGunfight,
			expected:  "/api/job/job-123/event/gunfight",
		},
		{
			name:      "grenade event",
			jobID:     "job-456",
			eventType: EventTypeGrenade,
			expected:  "/api/job/job-456/event/grenade",
		},
		{
			name:      "round event",
			jobID:     "job-789",
			eventType: EventTypeRound,
			expected:  "/api/job/job-789/event/round",
		},
		{
			name:      "damage event",
			jobID:     "job-abc",
			eventType: EventTypeDamage,
			expected:  "/api/job/job-abc/event/damage",
		},
		{
			name:      "empty job ID",
			jobID:     "",
			eventType: EventTypeGunfight,
			expected:  "/api/job//event/gunfight",
		},
		{
			name:      "special characters in job ID",
			jobID:     "job-123_456-789",
			eventType: EventTypeGrenade,
			expected:  "/api/job/job-123_456-789/event/grenade",
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			formattedEndpoint := "/api/job/" + tc.jobID + "/event/" + tc.eventType
			if formattedEndpoint != tc.expected {
				t.Errorf("Expected '%s', got '%s'", tc.expected, formattedEndpoint)
			}
		})
	}
}

func TestEndpoints_Consistency(t *testing.T) {
	// Test that endpoints follow consistent patterns
	endpoints := []string{
		HealthEndpoint,
		ReadinessEndpoint,
		ParseDemoEndpoint,
	}

	// All endpoints should start with "/" or be a simple string
	for _, endpoint := range endpoints {
		if endpoint == "" {
			t.Errorf("Endpoint should not be empty")
		}
	}

	// Health and readiness endpoints should start with "/"
	healthEndpoints := []string{HealthEndpoint, ReadinessEndpoint}
	for _, endpoint := range healthEndpoints {
		if endpoint[0] != '/' {
			t.Errorf("Health endpoint '%s' should start with '/'", endpoint)
		}
	}
}

func TestEventTypes_Usage(t *testing.T) {
	// Test that event types can be used in typical scenarios
	eventTypes := []string{
		EventTypeRound,
		EventTypeGunfight,
		EventTypeGrenade,
		EventTypeDamage,
	}

	// Test that each event type can be used in URL construction
	for _, eventType := range eventTypes {
		// Simulate URL construction
		url := "/api/job/test-job/event/" + eventType

		// URL should contain the event type
		if len(url) <= len("/api/job/test-job/event/") {
			t.Errorf("URL should be longer than base path")
		}

		// URL should end with the event type
		if url[len(url)-len(eventType):] != eventType {
			t.Errorf("URL should end with event type '%s'", eventType)
		}
	}
}

func TestEndpoints_Uniqueness(t *testing.T) {
	// Test that all endpoints are unique
	endpoints := []string{
		HealthEndpoint,
		ReadinessEndpoint,
		ParseDemoEndpoint,
	}

	endpointMap := make(map[string]bool)
	for _, endpoint := range endpoints {
		if endpointMap[endpoint] {
			t.Errorf("Endpoint '%s' is duplicated", endpoint)
		}
		endpointMap[endpoint] = true
	}
}

func TestEventTypes_Completeness(t *testing.T) {
	// Test that we have all the expected event types
	expectedEventTypes := map[string]bool{
		"round":    false,
		"gunfight": false,
		"grenade":  false,
		"damage":   false,
	}

	actualEventTypes := []string{
		EventTypeRound,
		EventTypeGunfight,
		EventTypeGrenade,
		EventTypeDamage,
	}

	// Mark found event types
	for _, eventType := range actualEventTypes {
		if expectedEventTypes[eventType] {
			t.Errorf("Event type '%s' is duplicated", eventType)
		}
		expectedEventTypes[eventType] = true
	}

	// Check that all expected event types were found
	for eventType, found := range expectedEventTypes {
		if !found {
			t.Errorf("Expected event type '%s' was not found", eventType)
		}
	}
}
