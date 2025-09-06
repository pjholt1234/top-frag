package types

import (
	"fmt"
	"time"
)

// TestContext collects multiple assertions and reports all failures
type TestContext struct {
	testName   string
	failures   []*AssertionResult
	assertions int
	startTime  time.Time
}

// NewTestContext creates a new test context for collecting assertions
func NewTestContext(testName string) *TestContext {
	return &TestContext{
		testName:  testName,
		failures:  make([]*AssertionResult, 0),
		startTime: time.Now(),
	}
}

// AssertCount performs a count assertion and logs failure if any
func (tc *TestContext) AssertCount(results []*QueryResult, operator string, expected int) {
	tc.assertions++
	result := AssertCount(results, operator, expected, tc.testName)
	if result.Status != "passed" {
		tc.failures = append(tc.failures, result)
	}
}

// AssertValue performs a value assertion and logs failure if any
func (tc *TestContext) AssertValue(actual interface{}, operator string, expected interface{}) {
	tc.assertions++
	result := AssertValue(actual, operator, expected, tc.testName)
	if result.Status != "passed" {
		tc.failures = append(tc.failures, result)
	}
}

// AssertExists performs an existence assertion and logs failure if any
func (tc *TestContext) AssertExists(results []*QueryResult) {
	tc.assertions++
	result := AssertExists(results, tc.testName)
	if result.Status != "passed" {
		tc.failures = append(tc.failures, result)
	}
}

// AssertNotNull performs a not-null assertion and logs failure if any
func (tc *TestContext) AssertNotNull(result *QueryResult) {
	tc.assertions++
	assertResult := AssertNotNull(result, tc.testName)
	if assertResult.Status != "passed" {
		tc.failures = append(tc.failures, assertResult)
	}
}

// AssertEmpty performs an empty assertion and logs failure if any
func (tc *TestContext) AssertEmpty(results []*QueryResult) {
	tc.assertions++
	result := AssertEmpty(results, tc.testName)
	if result.Status != "passed" {
		tc.failures = append(tc.failures, result)
	}
}

// AssertFieldExists performs a field existence assertion and logs failure if any
func (tc *TestContext) AssertFieldExists(result *QueryResult, field string) {
	tc.assertions++
	assertResult := AssertFieldExists(result, field, tc.testName)
	if assertResult.Status != "passed" {
		tc.failures = append(tc.failures, assertResult)
	}
}

// GetResult returns the final test result - passed if no failures, failed with details if any failures
func (tc *TestContext) GetResult() *AssertionResult {
	duration := time.Since(tc.startTime)

	if len(tc.failures) == 0 {
		// All assertions passed
		return &AssertionResult{
			TestName:  tc.testName,
			Status:    "passed",
			Assertion: fmt.Sprintf("%d assertions passed", tc.assertions),
			Expected:  "all pass",
			Actual:    "all passed",
			Duration:  duration,
		}
	}

	// Some assertions failed - return the first failure with summary
	firstFailure := tc.failures[0]
	firstFailure.Duration = duration

	if len(tc.failures) > 1 {
		firstFailure.ErrorMessage = fmt.Sprintf("%s (+ %d more failures)",
			firstFailure.ErrorMessage, len(tc.failures)-1)
	}

	return firstFailure
}

// HasFailures returns true if any assertions failed
func (tc *TestContext) HasFailures() bool {
	return len(tc.failures) > 0
}

// GetFailures returns all failed assertions
func (tc *TestContext) GetFailures() []*AssertionResult {
	return tc.failures
}

// GetAssertionCount returns the total number of assertions made
func (tc *TestContext) GetAssertionCount() int {
	return tc.assertions
}
