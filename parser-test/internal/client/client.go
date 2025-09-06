package client

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"sync"
	"time"

	"github.com/sirupsen/logrus"

	"parser-test/internal/server"
	"parser-test/internal/types"
)

// TestClient manages the interaction with the parser service
type TestClient struct {
	config     *types.TestConfig
	httpClient *http.Client
	logger     *logrus.Logger
	mockServer *server.MockServer
	jobID      string
}

// NewTestClient creates a new TestClient instance
func NewTestClient(config *types.TestConfig) *TestClient {
	logger := logrus.New()
	if config.Verbose {
		logger.SetLevel(logrus.DebugLevel)
	} else {
		logger.SetLevel(logrus.InfoLevel)
	}

	// Create mock server to receive parser service callbacks
	mockServer := server.NewMockServer(logger, 8081) // Use port 8081 for mock server

	return &TestClient{
		config: config,
		httpClient: &http.Client{
			Timeout: time.Duration(config.Timeout) * time.Second,
		},
		logger:     logger,
		mockServer: mockServer,
	}
}

// RunTests executes the full test suite
func (tc *TestClient) RunTests() (*types.TestResults, error) {
	startTime := time.Now()

	tc.logger.Info("Starting parser service integration tests")
	tc.logger.Infof("Demo file: %s", tc.config.DemoFile)
	tc.logger.Infof("Parser service URL: %s", tc.config.BaseURL)

	// Start mock server to receive parser service callbacks
	if err := tc.mockServer.Start(); err != nil {
		return nil, fmt.Errorf("failed to start mock server: %v", err)
	}
	defer tc.mockServer.Stop()

	tc.logger.Infof("Mock server started at: %s", tc.mockServer.GetBaseURL())

	// Step 1: Upload demo file and start parsing
	if err := tc.uploadDemo(); err != nil {
		return nil, fmt.Errorf("failed to upload demo: %v", err)
	}

	// Step 2: Wait for parsing completion
	if err := tc.waitForCompletion(); err != nil {
		return nil, fmt.Errorf("parsing failed: %v", err)
	}

	// Step 3: Run event tests
	results := &types.TestResults{
		StartTime:    startTime,
		EventResults: make([]*types.EventResults, 0),
	}

	eventTypes := tc.getEventTypesToTest()

	if tc.config.Parallel {
		tc.logger.Info("Running tests in parallel")
		results = tc.runTestsParallel(eventTypes, results)
	} else {
		tc.logger.Info("Running tests sequentially")
		results = tc.runTestsSequential(eventTypes, results)
	}

	// Step 4: Calculate final results
	results.EndTime = time.Now()
	results.Duration = results.EndTime.Sub(results.StartTime)

	// Calculate totals
	for _, eventResult := range results.EventResults {
		results.TotalTests += eventResult.TotalTests
		results.PassedTests += eventResult.PassedTests
		results.FailedTests += eventResult.FailedTests
		results.ErrorTests += eventResult.ErrorTests
	}

	tc.logger.Infof("Tests completed in %v", results.Duration)
	return results, nil
}

// uploadDemo uploads the demo file to the parser service
func (tc *TestClient) uploadDemo() error {
	tc.logger.Info("Uploading demo file to parser service")

	// Create multipart form
	var body bytes.Buffer
	writer := multipart.NewWriter(&body)

	// Add demo file
	file, err := os.Open(tc.config.DemoFile)
	if err != nil {
		return fmt.Errorf("failed to open demo file: %v", err)
	}
	defer file.Close()

	part, err := writer.CreateFormFile("demo_file", filepath.Base(tc.config.DemoFile))
	if err != nil {
		return fmt.Errorf("failed to create form file: %v", err)
	}

	if _, err := io.Copy(part, file); err != nil {
		return fmt.Errorf("failed to copy file data: %v", err)
	}

	// Add required form fields - use our mock server URLs
	// Progress callback gets job_id in JSON payload, completion callback gets job_id in JSON payload
	// Event data URLs are constructed by parser service from completion_callback_url base
	baseURL := tc.mockServer.GetBaseURL()
	writer.WriteField("progress_callback_url", fmt.Sprintf("%s/api/demo-parser/progress", baseURL))
	writer.WriteField("completion_callback_url", fmt.Sprintf("%s/api/demo-parser/completion", baseURL))

	writer.Close()

	// Create request
	url := fmt.Sprintf("%s/api/parse-demo", tc.config.BaseURL)
	req, err := http.NewRequest("POST", url, &body)
	if err != nil {
		return fmt.Errorf("failed to create request: %v", err)
	}

	req.Header.Set("Content-Type", writer.FormDataContentType())
	if tc.config.APIToken != "" {
		req.Header.Set("X-API-Key", tc.config.APIToken)
	}

	// Send request
	resp, err := tc.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("failed to send request: %v", err)
	}
	defer resp.Body.Close()

	// Parse response
	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusAccepted {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("upload failed with status %d: %s", resp.StatusCode, string(bodyBytes))
	}

	var response struct {
		JobID   string `json:"job_id"`
		Success bool   `json:"success"`
		Message string `json:"message"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&response); err != nil {
		return fmt.Errorf("failed to decode response: %v", err)
	}

	if !response.Success {
		return fmt.Errorf("upload failed: %s", response.Message)
	}

	tc.jobID = response.JobID
	tc.logger.Infof("Demo uploaded successfully, job ID: %s", tc.jobID)
	return nil
}

// waitForCompletion waits for the parsing job to complete
func (tc *TestClient) waitForCompletion() error {
	tc.logger.Info("Waiting for parsing to complete")

	maxWaitTime := time.Duration(tc.config.Timeout) * time.Second

	// Wait for job completion using our mock server's completion endpoint
	url := fmt.Sprintf("%s/test/job/%s/completed", tc.mockServer.GetBaseURL(), tc.jobID)

	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return fmt.Errorf("failed to create completion check request: %v", err)
	}

	// Set a reasonable timeout for waiting
	client := &http.Client{
		Timeout: maxWaitTime,
	}

	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("failed to wait for completion: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode == http.StatusOK {
		tc.logger.Info("Job completed successfully")
		return nil
	} else if resp.StatusCode == http.StatusRequestTimeout {
		return fmt.Errorf("parsing timed out")
	} else {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("completion check failed with status %d: %s", resp.StatusCode, string(bodyBytes))
	}
}

// getEventTypesToTest returns the list of event types to test
func (tc *TestClient) getEventTypesToTest() []string {
	allEvents := []string{"damage", "gunfight", "grenade", "round", "player-round"}

	if tc.config.SpecificTest != "" {
		return []string{tc.config.SpecificTest}
	}

	return allEvents
}

// runTestsParallel runs tests for all event types in parallel
func (tc *TestClient) runTestsParallel(eventTypes []string, results *types.TestResults) *types.TestResults {
	var wg sync.WaitGroup
	resultsChan := make(chan *types.EventResults, len(eventTypes))

	for _, eventType := range eventTypes {
		wg.Add(1)
		go func(et string) {
			defer wg.Done()
			eventResult := tc.runEventTests(et)
			resultsChan <- eventResult
		}(eventType)
	}

	wg.Wait()
	close(resultsChan)

	for eventResult := range resultsChan {
		results.EventResults = append(results.EventResults, eventResult)
	}

	return results
}

// runTestsSequential runs tests for all event types sequentially
func (tc *TestClient) runTestsSequential(eventTypes []string, results *types.TestResults) *types.TestResults {
	for _, eventType := range eventTypes {
		eventResult := tc.runEventTests(eventType)
		results.EventResults = append(results.EventResults, eventResult)
	}
	return results
}

// runEventTests runs tests for a specific event type
func (tc *TestClient) runEventTests(eventType string) *types.EventResults {
	startTime := time.Now()

	tc.logger.Infof("Running tests for %s events", eventType)

	eventResult := &types.EventResults{
		EventType:  eventType,
		Assertions: make([]*types.AssertionResult, 0),
	}

	// Get test functions for this event type
	testFunctions := tc.getTestFunctions(eventType)

	for _, testFunc := range testFunctions {
		assertion := testFunc(tc)
		eventResult.Assertions = append(eventResult.Assertions, assertion)

		switch assertion.Status {
		case "passed":
			eventResult.PassedTests++
		case "failed":
			eventResult.FailedTests++
		case "error":
			eventResult.ErrorTests++
		}
		eventResult.TotalTests++
	}

	eventResult.Duration = time.Since(startTime)
	tc.logger.Infof("Completed %s tests: %d passed, %d failed, %d errors",
		eventType, eventResult.PassedTests, eventResult.FailedTests, eventResult.ErrorTests)

	return eventResult
}

// TestFunction represents a test function
type TestFunction func(*TestClient) *types.AssertionResult

// getRoundTests returns empty slice for round tests (placeholder)
func (tc *TestClient) getRoundTests() []TestFunction {
	return []TestFunction{}
}

// getTestFunctions returns the test functions for a given event type
func (tc *TestClient) getTestFunctions(eventType string) []TestFunction {
	switch eventType {
	case "damage":
		// Use new assertion pattern
		return tc.getDamageTests()
	case "gunfight":
		return tc.getGunfightTests()
	case "grenade":
		return tc.getGrenadeTests()
	case "round":
		return tc.getRoundTests()
	case "player-round":
		return tc.getPlayerRoundTests()
	default:
		tc.logger.Warnf("No tests defined for event type: %s", eventType)
		return []TestFunction{}
	}
}

// GetEventData implements the ParserClient interface
func (tc *TestClient) GetEventData(eventType, jobID string) (string, error) {
	// Fetch data from our mock server that received the parser service callbacks
	url := fmt.Sprintf("%s/test/job/%s/event/%s", tc.mockServer.GetBaseURL(), jobID, eventType)

	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return "", fmt.Errorf("failed to create request: %v", err)
	}

	resp, err := tc.httpClient.Do(req)
	if err != nil {
		return "", fmt.Errorf("failed to send request: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return "", fmt.Errorf("request failed with status %d: %s", resp.StatusCode, string(bodyBytes))
	}

	bodyBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return "", fmt.Errorf("failed to read response body: %v", err)
	}

	return string(bodyBytes), nil
}
