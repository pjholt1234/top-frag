package types

import (
	"fmt"
	"reflect"
	"strconv"
	"strings"
	"time"

	"github.com/sirupsen/logrus"
	"github.com/tidwall/gjson"
)

// TestCase provides the testing framework for making assertions on parser data
type TestCase struct {
	client      ParserClient
	eventType   string
	jobID       string
	filters     []Filter
	logger      *logrus.Logger
	currentData []gjson.Result
	testName    string
}

// Filter represents a where clause filter
type Filter struct {
	Field    string
	Operator string
	Value    interface{}
}

// ParserClient interface for fetching data from parser service
type ParserClient interface {
	GetEventData(eventType, jobID string) (string, error)
}

// NewTestCase creates a new TestCase instance
func NewTestCase(client ParserClient, jobID string, logger *logrus.Logger, testName string) *TestCase {
	return &TestCase{
		client:   client,
		jobID:    jobID,
		logger:   logger,
		testName: testName,
		filters:  make([]Filter, 0),
	}
}

// Data fetches data for the specified event type
func (tc *TestCase) Data(eventType string) *TestCase {
	// Clear cached data and filters when switching event types
	if tc.eventType != eventType {
		tc.currentData = nil
		tc.filters = make([]Filter, 0)
	}
	tc.eventType = eventType
	tc.logger.Debugf("Fetching data for event type: %s", eventType)
	return tc
}

// Where adds a filter condition to the query
func (tc *TestCase) Where(field, operator string, value interface{}) *TestCase {
	filter := Filter{
		Field:    field,
		Operator: operator,
		Value:    value,
	}
	tc.filters = append(tc.filters, filter)
	// Clear cached data when filters change to ensure fresh fetch
	tc.currentData = nil
	tc.logger.Debugf("Added filter: %s %s %v", field, operator, value)
	return tc
}

// Get returns all filtered results as QueryResult slice
func (tc *TestCase) Get() []*QueryResult {
	// Always fetch and filter data to ensure fresh results
	if err := tc.fetchAndFilterData(); err != nil {
		tc.logger.Errorf("Failed to fetch data: %v", err)
		return []*QueryResult{}
	}

	results := make([]*QueryResult, len(tc.currentData))
	for i, data := range tc.currentData {
		results[i] = NewQueryResult(data)
	}

	return results
}

// First returns the first result or nil if no results found
func (tc *TestCase) First() *QueryResult {
	results := tc.Get()
	if len(results) == 0 {
		return nil
	}
	return results[0]
}

// Count returns the number of filtered results
func (tc *TestCase) Count() int {
	return len(tc.Get())
}

// Assert validates the filtered data against expected values (DEPRECATED - use standalone assertions)
func (tc *TestCase) Assert(field, operator string, expected interface{}) *AssertionResult {
	startTime := time.Now()

	result := &AssertionResult{
		TestName:  tc.testName,
		Assertion: fmt.Sprintf("%s %s %v", field, operator, expected),
		Expected:  expected,
		Duration:  0,
		EventPath: tc.buildEventPath(),
	}

	// Fetch data if not already cached
	if tc.currentData == nil {
		if err := tc.fetchAndFilterData(); err != nil {
			result.Status = "error"
			result.ErrorMessage = err.Error()
			result.Duration = time.Since(startTime)
			return result
		}
	}

	// Perform assertion
	if err := tc.performAssertion(field, operator, expected, result); err != nil {
		result.Status = "error"
		result.ErrorMessage = err.Error()
	}

	result.Duration = time.Since(startTime)
	return result
}

// fetchAndFilterData retrieves and filters the data based on current filters
func (tc *TestCase) fetchAndFilterData() error {
	// Fetch raw data from parser service
	rawData, err := tc.client.GetEventData(tc.eventType, tc.jobID)
	if err != nil {
		return fmt.Errorf("failed to fetch data for %s: %v", tc.eventType, err)
	}

	// Parse JSON and get data array
	parsed := gjson.Parse(rawData)
	dataArray := parsed.Get("data")
	if !dataArray.Exists() {
		return fmt.Errorf("no data field found in response for %s", tc.eventType)
	}

	// Log total data count
	totalCount := 0
	dataArray.ForEach(func(key, value gjson.Result) bool {
		totalCount++
		return true
	})
	tc.logger.Infof("Total %s events available: %d", tc.eventType, totalCount)

	// Log filters being applied
	if len(tc.filters) > 0 {
		tc.logger.Infof("Applying filters: %+v", tc.filters)
	}

	// Apply filters
	tc.currentData = make([]gjson.Result, 0)
	dataArray.ForEach(func(key, value gjson.Result) bool {
		if tc.matchesFilters(value) {
			tc.currentData = append(tc.currentData, value)
		}
		return true
	})

	tc.logger.Infof("Filtered data: %d records match criteria out of %d total", len(tc.currentData), totalCount)

	// Log sample of filtered results for damage events
	if tc.eventType == "damage" && len(tc.currentData) > 0 {
		for i, result := range tc.currentData {
			if i < 2 { // Log first 2 filtered results
				tc.logger.Infof("Filtered damage event %d: attacker_steam_id=%s, round_number=%v, damage=%v",
					i, result.Get("attacker_steam_id").String(), result.Get("round_number").Value(), result.Get("damage").Value())
			}
		}
	}

	return nil
}

// matchesFilters checks if a data record matches all current filters
func (tc *TestCase) matchesFilters(record gjson.Result) bool {
	for _, filter := range tc.filters {
		if !tc.evaluateFilter(record, filter) {
			return false
		}
	}
	return true
}

// evaluateFilter evaluates a single filter against a record
func (tc *TestCase) evaluateFilter(record gjson.Result, filter Filter) bool {
	fieldValue := record.Get(filter.Field)
	if !fieldValue.Exists() {
		return false
	}

	return tc.compareValues(fieldValue, filter.Operator, filter.Value)
}

// performAssertion executes the assertion logic
func (tc *TestCase) performAssertion(field, operator string, expected interface{}, result *AssertionResult) error {
	switch operator {
	case "count":
		// Special case for counting records
		actual := len(tc.currentData)
		result.Actual = actual
		expectedInt, err := convertToInt(expected)
		if err != nil {
			return fmt.Errorf("invalid count expected value: %v", expected)
		}
		if actual == expectedInt {
			result.Status = "passed"
		} else {
			result.Status = "failed"
		}
		return nil

	case "exists":
		// Check if any records exist
		actual := len(tc.currentData) > 0
		result.Actual = actual
		expectedBool, ok := expected.(bool)
		if !ok {
			return fmt.Errorf("exists assertion requires boolean expected value, got %T", expected)
		}
		if actual == expectedBool {
			result.Status = "passed"
		} else {
			result.Status = "failed"
		}
		return nil

	default:
		// Field-based assertions
		if len(tc.currentData) == 0 {
			result.Status = "failed"
			result.Actual = nil
			result.ErrorMessage = "no records found matching filters"
			return nil
		}

		// For single record, get the field value
		if len(tc.currentData) == 1 {
			fieldValue := tc.currentData[0].Get(field)
			if !fieldValue.Exists() {
				result.Status = "failed"
				result.Actual = nil
				result.ErrorMessage = fmt.Sprintf("field '%s' not found", field)
				return nil
			}

			actual := tc.extractValue(fieldValue)
			result.Actual = actual

			if tc.compareValues(fieldValue, operator, expected) {
				result.Status = "passed"
			} else {
				result.Status = "failed"
			}
			return nil
		}

		// For multiple records, need aggregation logic
		return tc.performAggregateAssertion(field, operator, expected, result)
	}
}

// performAggregateAssertion handles assertions on multiple records
func (tc *TestCase) performAggregateAssertion(field, operator string, expected interface{}, result *AssertionResult) error {
	switch operator {
	case "sum", "avg", "min", "max":
		values := make([]float64, 0)
		for _, record := range tc.currentData {
			fieldValue := record.Get(field)
			if fieldValue.Exists() {
				if val := fieldValue.Num; val != 0 || fieldValue.String() == "0" {
					values = append(values, val)
				}
			}
		}

		if len(values) == 0 {
			result.Status = "failed"
			result.Actual = nil
			result.ErrorMessage = fmt.Sprintf("no numeric values found for field '%s'", field)
			return nil
		}

		var actual float64
		switch operator {
		case "sum":
			for _, v := range values {
				actual += v
			}
		case "avg":
			for _, v := range values {
				actual += v
			}
			actual /= float64(len(values))
		case "min":
			actual = values[0]
			for _, v := range values {
				if v < actual {
					actual = v
				}
			}
		case "max":
			actual = values[0]
			for _, v := range values {
				if v > actual {
					actual = v
				}
			}
		}

		result.Actual = actual
		expectedFloat, err := convertToFloat(expected)
		if err != nil {
			return fmt.Errorf("invalid numeric expected value: %v", expected)
		}

		if actual == expectedFloat {
			result.Status = "passed"
		} else {
			result.Status = "failed"
		}
		return nil

	default:
		return fmt.Errorf("unsupported operator '%s' for multiple records", operator)
	}
}

// compareValues compares two values using the specified operator
func (tc *TestCase) compareValues(fieldValue gjson.Result, operator string, expected interface{}) bool {
	switch operator {
	case "=", "==":
		return tc.isEqual(fieldValue, expected)
	case "!=":
		return !tc.isEqual(fieldValue, expected)
	case ">":
		return tc.isGreater(fieldValue, expected)
	case ">=":
		return tc.isGreaterOrEqual(fieldValue, expected)
	case "<":
		return tc.isLess(fieldValue, expected)
	case "<=":
		return tc.isLessOrEqual(fieldValue, expected)
	case "in":
		return tc.isIn(fieldValue, expected)
	case "between":
		return tc.isBetween(fieldValue, expected)
	case "contains":
		return tc.contains(fieldValue, expected)
	default:
		tc.logger.Warnf("Unknown operator: %s", operator)
		return false
	}
}

// Helper comparison methods
func (tc *TestCase) isEqual(fieldValue gjson.Result, expected interface{}) bool {
	actual := tc.extractValue(fieldValue)

	// Soft equals - handle different types that represent the same value

	// Handle numeric comparison - convert both to float64 for comparison
	actualFloat, err1 := convertToFloat(actual)
	expectedFloat, err2 := convertToFloat(expected)
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

func (tc *TestCase) isGreater(fieldValue gjson.Result, expected interface{}) bool {
	actualFloat, err1 := convertToFloat(tc.extractValue(fieldValue))
	expectedFloat, err2 := convertToFloat(expected)
	if err1 != nil || err2 != nil {
		return false
	}
	return actualFloat > expectedFloat
}

func (tc *TestCase) isGreaterOrEqual(fieldValue gjson.Result, expected interface{}) bool {
	actualFloat, err1 := convertToFloat(tc.extractValue(fieldValue))
	expectedFloat, err2 := convertToFloat(expected)
	if err1 != nil || err2 != nil {
		return false
	}
	return actualFloat >= expectedFloat
}

func (tc *TestCase) isLess(fieldValue gjson.Result, expected interface{}) bool {
	actualFloat, err1 := convertToFloat(tc.extractValue(fieldValue))
	expectedFloat, err2 := convertToFloat(expected)
	if err1 != nil || err2 != nil {
		return false
	}
	return actualFloat < expectedFloat
}

func (tc *TestCase) isLessOrEqual(fieldValue gjson.Result, expected interface{}) bool {
	actualFloat, err1 := convertToFloat(tc.extractValue(fieldValue))
	expectedFloat, err2 := convertToFloat(expected)
	if err1 != nil || err2 != nil {
		return false
	}
	return actualFloat <= expectedFloat
}

func (tc *TestCase) isIn(fieldValue gjson.Result, expected interface{}) bool {
	actual := tc.extractValue(fieldValue)

	// Expected should be a slice
	expectedSlice := reflect.ValueOf(expected)
	if expectedSlice.Kind() != reflect.Slice {
		return false
	}

	for i := 0; i < expectedSlice.Len(); i++ {
		if reflect.DeepEqual(actual, expectedSlice.Index(i).Interface()) {
			return true
		}
	}
	return false
}

func (tc *TestCase) isBetween(fieldValue gjson.Result, expected interface{}) bool {
	actualFloat, err := convertToFloat(tc.extractValue(fieldValue))
	if err != nil {
		return false
	}

	// Expected should be a map with "min" and "max" keys
	expectedMap, ok := expected.(map[string]interface{})
	if !ok {
		return false
	}

	minVal, err1 := convertToFloat(expectedMap["min"])
	maxVal, err2 := convertToFloat(expectedMap["max"])
	if err1 != nil || err2 != nil {
		return false
	}

	return actualFloat >= minVal && actualFloat <= maxVal
}

func (tc *TestCase) contains(fieldValue gjson.Result, expected interface{}) bool {
	actual := tc.extractValue(fieldValue)
	actualStr, ok := actual.(string)
	if !ok {
		return false
	}

	expectedStr, ok := expected.(string)
	if !ok {
		return false
	}

	return strings.Contains(actualStr, expectedStr)
}

// extractValue converts gjson.Result to appropriate Go type
func (tc *TestCase) extractValue(result gjson.Result) interface{} {
	switch result.Type {
	case gjson.String:
		return result.String()
	case gjson.Number:
		return result.Num
	case gjson.True:
		return true
	case gjson.False:
		return false
	case gjson.Null:
		return nil
	default:
		return result.Value()
	}
}

// buildEventPath creates a descriptive path for the current query
func (tc *TestCase) buildEventPath() string {
	path := tc.eventType
	if len(tc.filters) > 0 {
		var filterParts []string
		for _, filter := range tc.filters {
			filterParts = append(filterParts, fmt.Sprintf("%s%s%v", filter.Field, filter.Operator, filter.Value))
		}
		path += "[" + strings.Join(filterParts, ",") + "]"
	}
	return path
}

// Utility conversion functions
func convertToInt(value interface{}) (int, error) {
	switch v := value.(type) {
	case int:
		return v, nil
	case int64:
		return int(v), nil
	case float64:
		return int(v), nil
	case string:
		return strconv.Atoi(v)
	default:
		return 0, fmt.Errorf("cannot convert %T to int", value)
	}
}

func convertToFloat(value interface{}) (float64, error) {
	switch v := value.(type) {
	case float64:
		return v, nil
	case int:
		return float64(v), nil
	case int64:
		return float64(v), nil
	case string:
		return strconv.ParseFloat(v, 64)
	default:
		return 0, fmt.Errorf("cannot convert %T to float64", value)
	}
}
