package types

import (
	"encoding/json"
	"fmt"
	"io"
	"strings"
	"time"
)

// TestResults contains the results of all test executions
type TestResults struct {
	StartTime    time.Time       `json:"start_time"`
	EndTime      time.Time       `json:"end_time"`
	Duration     time.Duration   `json:"duration"`
	TotalTests   int             `json:"total_tests"`
	PassedTests  int             `json:"passed_tests"`
	FailedTests  int             `json:"failed_tests"`
	ErrorTests   int             `json:"error_tests"`
	EventResults []*EventResults `json:"event_results"`
	Summary      string          `json:"summary"`
}

// EventResults contains results for a specific event type
type EventResults struct {
	EventType   string             `json:"event_type"`
	TotalTests  int                `json:"total_tests"`
	PassedTests int                `json:"passed_tests"`
	FailedTests int                `json:"failed_tests"`
	ErrorTests  int                `json:"error_tests"`
	Duration    time.Duration      `json:"duration"`
	Assertions  []*AssertionResult `json:"assertions"`
}

// AssertionResult represents the result of a single assertion
type AssertionResult struct {
	TestName     string        `json:"test_name"`
	Assertion    string        `json:"assertion"`
	Status       string        `json:"status"` // "passed", "failed", "error"
	Expected     interface{}   `json:"expected"`
	Actual       interface{}   `json:"actual"`
	ErrorMessage string        `json:"error_message,omitempty"`
	Duration     time.Duration `json:"duration"`
	EventPath    string        `json:"event_path"`
}

// HasFailures returns true if any tests failed or had errors
func (tr *TestResults) HasFailures() bool {
	return tr.FailedTests > 0 || tr.ErrorTests > 0
}

// OutputJSON outputs the results in JSON format
func (tr *TestResults) OutputJSON(w io.Writer) error {
	encoder := json.NewEncoder(w)
	encoder.SetIndent("", "  ")
	return encoder.Encode(tr)
}

// OutputText outputs the results in human-readable text format
func (tr *TestResults) OutputText(w io.Writer, errorsOnly bool) error {
	// Print header
	fmt.Fprintf(w, "\n=== Parser Service Integration Test Results ===\n\n")

	// Print summary
	fmt.Fprintf(w, "Total Tests:    %d\n", tr.TotalTests)
	fmt.Fprintf(w, "Passed:         %d\n", tr.PassedTests)
	fmt.Fprintf(w, "Failed:         %d\n", tr.FailedTests)
	fmt.Fprintf(w, "Errors:         %d\n", tr.ErrorTests)
	fmt.Fprintf(w, "Duration:       %v\n", tr.Duration)
	fmt.Fprintf(w, "Success Rate:   %.1f%%\n\n", float64(tr.PassedTests)/float64(tr.TotalTests)*100)

	// Print event-specific results
	for _, eventResult := range tr.EventResults {
		if errorsOnly && eventResult.FailedTests == 0 && eventResult.ErrorTests == 0 {
			continue
		}

		fmt.Fprintf(w, "--- %s Events ---\n", strings.Title(eventResult.EventType))
		fmt.Fprintf(w, "Tests: %d | Passed: %d | Failed: %d | Errors: %d | Duration: %v\n\n",
			eventResult.TotalTests, eventResult.PassedTests,
			eventResult.FailedTests, eventResult.ErrorTests, eventResult.Duration)

		// Print assertion details
		for _, assertion := range eventResult.Assertions {
			if errorsOnly && assertion.Status == "passed" {
				continue
			}

			statusIcon := getStatusIcon(assertion.Status)
			fmt.Fprintf(w, "%s %s\n", statusIcon, assertion.TestName)
			fmt.Fprintf(w, "   Assertion: %s\n", assertion.Assertion)

			if assertion.Status != "passed" {
				if assertion.Status == "failed" {
					fmt.Fprintf(w, "   Expected:  %v\n", assertion.Expected)
					fmt.Fprintf(w, "   Actual:    %v\n", assertion.Actual)
				}
				if assertion.ErrorMessage != "" {
					fmt.Fprintf(w, "   Error:     %s\n", assertion.ErrorMessage)
				}
			}
			fmt.Fprintf(w, "   Duration:  %v\n", assertion.Duration)
			fmt.Fprintf(w, "\n")
		}
	}

	// Print final summary
	if tr.HasFailures() {
		fmt.Fprintf(w, "❌ Tests FAILED - %d failures, %d errors\n", tr.FailedTests, tr.ErrorTests)
	} else {
		fmt.Fprintf(w, "✅ All tests PASSED\n")
	}

	return nil
}

func getStatusIcon(status string) string {
	switch status {
	case "passed":
		return "✅"
	case "failed":
		return "❌"
	case "error":
		return "💥"
	default:
		return "❓"
	}
}
