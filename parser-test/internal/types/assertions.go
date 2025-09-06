package types

import (
	"fmt"
	"reflect"
	"strconv"
	"strings"
	"time"
)

// Collection Assertions

// AssertCount asserts the count of results matches expected value with operator
func AssertCount(results []*QueryResult, operator string, expected int, testName string) *AssertionResult {
	startTime := time.Now()
	actual := len(results)

	result := &AssertionResult{
		TestName:  testName,
		Assertion: fmt.Sprintf("count %s %d", operator, expected),
		Expected:  expected,
		Actual:    actual,
		Duration:  time.Since(startTime),
	}

	if compareNumbers(float64(actual), operator, float64(expected)) {
		result.Status = "passed"
	} else {
		result.Status = "failed"
	}

	return result
}

// AssertExists asserts that results array is not empty
func AssertExists(results []*QueryResult, testName string) *AssertionResult {
	startTime := time.Now()
	actual := len(results) > 0

	result := &AssertionResult{
		TestName:  testName,
		Assertion: "exists = true",
		Expected:  true,
		Actual:    actual,
		Duration:  time.Since(startTime),
	}

	if actual {
		result.Status = "passed"
	} else {
		result.Status = "failed"
		result.ErrorMessage = "no records found"
	}

	return result
}

// AssertEmpty asserts that results array is empty
func AssertEmpty(results []*QueryResult, testName string) *AssertionResult {
	startTime := time.Now()
	actual := len(results) == 0

	result := &AssertionResult{
		TestName:  testName,
		Assertion: "empty = true",
		Expected:  true,
		Actual:    actual,
		Duration:  time.Since(startTime),
	}

	if actual {
		result.Status = "passed"
	} else {
		result.Status = "failed"
		result.ErrorMessage = fmt.Sprintf("expected empty but found %d records", len(results))
	}

	return result
}

// Single Value Assertions

// AssertValue asserts a single value matches expected with operator
func AssertValue(actual interface{}, operator string, expected interface{}, testName string) *AssertionResult {
	startTime := time.Now()

	result := &AssertionResult{
		TestName:  testName,
		Assertion: fmt.Sprintf("value %s %v", operator, expected),
		Expected:  expected,
		Actual:    actual,
		Duration:  time.Since(startTime),
	}

	if compareValues(actual, operator, expected) {
		result.Status = "passed"
	} else {
		result.Status = "failed"
	}

	return result
}

// AssertNotNull asserts that a QueryResult is not nil
func AssertNotNull(result *QueryResult, testName string) *AssertionResult {
	startTime := time.Now()
	actual := result != nil

	assertionResult := &AssertionResult{
		TestName:  testName,
		Assertion: "not null",
		Expected:  true,
		Actual:    actual,
		Duration:  time.Since(startTime),
	}

	if actual {
		assertionResult.Status = "passed"
	} else {
		assertionResult.Status = "failed"
		assertionResult.ErrorMessage = "expected non-null result but got nil"
	}

	return assertionResult
}

// AssertNull asserts that a QueryResult is nil
func AssertNull(result *QueryResult, testName string) *AssertionResult {
	startTime := time.Now()
	actual := result == nil

	assertionResult := &AssertionResult{
		TestName:  testName,
		Assertion: "null",
		Expected:  true,
		Actual:    actual,
		Duration:  time.Since(startTime),
	}

	if actual {
		assertionResult.Status = "passed"
	} else {
		assertionResult.Status = "failed"
		assertionResult.ErrorMessage = "expected null result but got non-nil"
	}

	return assertionResult
}

// AssertFieldExists asserts that a field exists in a QueryResult
func AssertFieldExists(result *QueryResult, field string, testName string) *AssertionResult {
	startTime := time.Now()

	assertionResult := &AssertionResult{
		TestName:  testName,
		Assertion: fmt.Sprintf("field '%s' exists", field),
		Expected:  true,
		Duration:  time.Since(startTime),
	}

	if result == nil {
		assertionResult.Status = "failed"
		assertionResult.Actual = false
		assertionResult.ErrorMessage = "cannot check field on nil result"
		return assertionResult
	}

	actual := result.HasField(field)
	assertionResult.Actual = actual

	if actual {
		assertionResult.Status = "passed"
	} else {
		assertionResult.Status = "failed"
		assertionResult.ErrorMessage = fmt.Sprintf("field '%s' not found in result", field)
	}

	return assertionResult
}

// Helper comparison functions

func compareValues(actual interface{}, operator string, expected interface{}) bool {
	switch operator {
	case "=", "==":
		return isEqual(actual, expected)
	case "!=":
		return !isEqual(actual, expected)
	case ">", ">=", "<", "<=":
		actualFloat, err1 := convertToFloat64(actual)
		expectedFloat, err2 := convertToFloat64(expected)
		if err1 != nil || err2 != nil {
			return false
		}
		return compareNumbers(actualFloat, operator, expectedFloat)
	case "contains":
		actualStr := fmt.Sprintf("%v", actual)
		expectedStr := fmt.Sprintf("%v", expected)
		return strings.Contains(actualStr, expectedStr)
	case "starts_with":
		actualStr := fmt.Sprintf("%v", actual)
		expectedStr := fmt.Sprintf("%v", expected)
		return strings.HasPrefix(actualStr, expectedStr)
	case "ends_with":
		actualStr := fmt.Sprintf("%v", actual)
		expectedStr := fmt.Sprintf("%v", expected)
		return strings.HasSuffix(actualStr, expectedStr)
	default:
		return false
	}
}

func compareNumbers(actual float64, operator string, expected float64) bool {
	switch operator {
	case ">":
		return actual > expected
	case ">=":
		return actual >= expected
	case "<":
		return actual < expected
	case "<=":
		return actual <= expected
	case "=", "==":
		return actual == expected
	case "!=":
		return actual != expected
	default:
		return false
	}
}

func isEqual(actual, expected interface{}) bool {
	// Soft equals - handle different types that represent the same value

	// Handle numeric comparison - convert both to float64 for comparison
	actualFloat, err1 := convertToFloat64(actual)
	expectedFloat, err2 := convertToFloat64(expected)
	if err1 == nil && err2 == nil {
		return actualFloat == expectedFloat
	}

	// Handle string comparison - convert both to strings
	actualStr := fmt.Sprintf("%v", actual)
	expectedStr := fmt.Sprintf("%v", expected)
	if actualStr == expectedStr {
		return true
	}

	// Fall back to deep equal for complex types
	return reflect.DeepEqual(actual, expected)
}

func convertToFloat64(value interface{}) (float64, error) {
	switch v := value.(type) {
	case float64:
		return v, nil
	case float32:
		return float64(v), nil
	case int:
		return float64(v), nil
	case int8:
		return float64(v), nil
	case int16:
		return float64(v), nil
	case int32:
		return float64(v), nil
	case int64:
		return float64(v), nil
	case uint:
		return float64(v), nil
	case uint8:
		return float64(v), nil
	case uint16:
		return float64(v), nil
	case uint32:
		return float64(v), nil
	case uint64:
		return float64(v), nil
	case string:
		return strconv.ParseFloat(v, 64)
	default:
		return 0, fmt.Errorf("cannot convert %T to float64", value)
	}
}
