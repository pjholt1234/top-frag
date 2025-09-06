package types

import (
	"github.com/tidwall/gjson"
)

// QueryResult represents a single record from a query
type QueryResult struct {
	data gjson.Result
}

// NewQueryResult creates a new QueryResult from gjson.Result
func NewQueryResult(data gjson.Result) *QueryResult {
	return &QueryResult{data: data}
}

// GetField returns the raw value of a field
func (qr *QueryResult) GetField(field string) interface{} {
	return qr.data.Get(field).Value()
}

// GetString returns a field value as string
func (qr *QueryResult) GetString(field string) string {
	return qr.data.Get(field).String()
}

// GetInt returns a field value as int
func (qr *QueryResult) GetInt(field string) int {
	return int(qr.data.Get(field).Int())
}

// GetFloat returns a field value as float64
func (qr *QueryResult) GetFloat(field string) float64 {
	return qr.data.Get(field).Float()
}

// GetBool returns a field value as bool
func (qr *QueryResult) GetBool(field string) bool {
	return qr.data.Get(field).Bool()
}

// HasField checks if a field exists
func (qr *QueryResult) HasField(field string) bool {
	return qr.data.Get(field).Exists()
}

// GetRaw returns the raw gjson.Result for advanced usage
func (qr *QueryResult) GetRaw() gjson.Result {
	return qr.data
}

// ToMap converts the result to a map for debugging
func (qr *QueryResult) ToMap() map[string]interface{} {
	result := make(map[string]interface{})
	qr.data.ForEach(func(key, value gjson.Result) bool {
		result[key.String()] = value.Value()
		return true
	})
	return result
}
