# Parser Service Integration Test Harness

A comprehensive CLI tool for testing a Go parser service that processes Counter-Strike 2 demo files. This testing harness provides black-box testing capabilities with queryable, pluggable assertions on streaming parser data from multiple endpoints. It's designed to validate parser service functionality, data accuracy, and performance characteristics.

## 🎯 Overview

The Parser Test Harness is a critical component of the Top Frag platform, providing:
- **Black-box Testing**: Treats the parser service as a black box, requiring only configuration
- **Comprehensive Validation**: Tests all event types with detailed assertions
- **Performance Testing**: Measures parsing time and validates performance characteristics
- **Data Integrity**: Ensures extracted data matches expected patterns and values
- **Regression Testing**: Prevents breaking changes in parser service updates

## 🏗️ Architecture Overview

The test harness follows a modular architecture with clear separation of concerns:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   CLI Interface │    │  Test Client    │    │  Mock Server    │
│   (Cobra)       │───▶│  (Orchestrator) │───▶│  (Callbacks)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  Test Results   │    │  Event Tests    │    │ Parser Service  │
│  (Output)       │    │  (Assertions)   │    │  (Target)       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Features

- **Black-box Testing**: Treats the parser service as a black box, only requiring configuration
- **CLI Interface**: Easy-to-use command-line interface with comprehensive options
- **Parallel Execution**: Run tests in parallel for faster execution
- **Queryable Assertions**: Powerful filtering and assertion framework using GJSON
- **Multiple Output Formats**: Text and JSON output formats
- **Comprehensive Logging**: Detailed logging with configurable verbosity
- **Event-Specific Tests**: Dedicated test suites for each event type
- **Mock Server**: Built-in HTTP server to receive parser service callbacks
- **Performance Metrics**: Detailed timing and performance measurements
- **Error Handling**: Comprehensive error detection and reporting

## 📁 Project Structure

```
parser-test/
├── main.go                 # Application entry point
├── debug_test.go          # Debug and development tests
├── go.mod                 # Go module dependencies
├── go.sum                 # Dependency checksums
├── Makefile              # Build and development commands
├── README.md             # This documentation file
├── internal/              # Private application code
│   ├── cli/              # Command-line interface
│   │   └── root.go       # CLI root command and flag definitions
│   ├── client/           # Test client and orchestration
│   │   ├── client.go     # Main test client and test execution
│   │   ├── damage_tests.go      # Damage event test suite
│   │   ├── grenade_tests.go     # Grenade event test suite
│   │   ├── gunfight_tests.go    # Gunfight event test suite
│   │   ├── player_round_tests.go # Player round event test suite
│   │   └── round_tests.go       # Round event test suite
│   ├── server/           # Mock server for callbacks
│   │   └── mock_server.go # HTTP server for receiving parser callbacks
│   └── types/            # Data structures and type definitions
│       ├── assertions.go # Assertion framework and operators
│       ├── config.go     # Test configuration structures
│       ├── query_result.go # Query result data structures
│       ├── results.go    # Test results and output formatting
│       ├── test_context.go # Test context and data management
│       └── testcase.go   # Test case definitions and execution
└── .gitignore            # Git ignore patterns
```

### Key Components Explained

#### **CLI Interface (`internal/cli/`)**
- **Purpose**: Command-line interface using Cobra framework
- **Features**:
  - Comprehensive flag definitions for all test options
  - Input validation and error handling
  - Configuration management and environment variable support
  - Output format selection (text/JSON)

#### **Test Client (`internal/client/`)**
- **Purpose**: Main test orchestration and execution engine
- **Responsibilities**:
  - Demo file upload to parser service
  - Mock server management for callbacks
  - Test suite execution (parallel/sequential)
  - Result aggregation and reporting
  - Performance measurement and timing

#### **Event Test Suites (`internal/client/*_tests.go`)**
- **Purpose**: Specialized test suites for each event type
- **Test Categories**:
  - **Damage Tests**: Validate damage event extraction and accuracy
  - **Grenade Tests**: Test grenade throw detection and effectiveness
  - **Gunfight Tests**: Verify combat encounter processing
  - **Round Tests**: Validate round-level event handling
  - **Player Round Tests**: Test per-player round statistics

#### **Mock Server (`internal/server/`)**
- **Purpose**: HTTP server to receive parser service callbacks
- **Features**:
  - Progress callback handling
  - Completion callback processing
  - Event data collection and storage
  - Error handling and logging

#### **Type System (`internal/types/`)**
- **Purpose**: Comprehensive type definitions and data structures
- **Key Types**:
  - `TestConfig`: Test configuration and parameters
  - `TestCase`: Individual test case definition and execution
  - `AssertionResult`: Test assertion results and validation
  - `TestResults`: Aggregated test results and reporting
  - `QueryResult`: Data querying and filtering results

## Installation

```bash
# Clone the repository
cd parser-test

# Install dependencies
go mod tidy

# Build the binary
go build -o parser-test .

# Or use Makefile
make build
```

## Usage

### Basic Usage

```bash
# Run all tests on a demo file
./parser-test --demo path/to/demo.dem

# Run with verbose logging
./parser-test --demo path/to/demo.dem --log

# Run only specific event tests
./parser-test --demo path/to/demo.dem --test damage

# Show only failed tests
./parser-test --demo path/to/demo.dem --errors

# Output results in JSON format
./parser-test --demo path/to/demo.dem --format json
```

### Command Line Options

| Flag | Description | Default |
|------|-------------|---------|
| `--demo, -d` | Path to demo file (.dem) - **Required** | |
| `--url` | Base URL of parser service | `http://localhost:8080` |
| `--token` | API token for authentication | |
| `--timeout` | Request timeout in seconds | `300` |
| `--log, -v` | Enable verbose logging | `false` |
| `--errors, -e` | Show only failed assertions | `false` |
| `--format, -f` | Output format (text, json) | `text` |
| `--test, -t` | Run specific test (damage, gunfight, grenade, round, player-round) | |
| `--parallel, -p` | Run tests in parallel | `true` |

### Configuration

The test harness can be configured through environment variables:

```bash
export PARSER_SERVICE_URL="http://localhost:8080"
export PARSER_SERVICE_TOKEN="your-api-token"
export PARSER_TEST_TIMEOUT="300"
```

## 🧪 Testing Methodology

### Test Execution Workflow

The test harness follows a systematic approach to validate parser service functionality:

```
1. Demo Upload → 2. Parser Processing → 3. Callback Collection → 4. Data Validation → 5. Results Reporting
```

#### **Phase 1: Demo Upload and Processing**
- Upload demo file to parser service via HTTP POST
- Receive job ID for tracking
- Start mock server to receive callbacks
- Monitor parsing progress via progress callbacks

#### **Phase 2: Data Collection**
- Collect completion callback with parsed data
- Store event data for validation
- Measure processing time and performance metrics
- Handle errors and timeout scenarios

#### **Phase 3: Test Execution**
- Execute event-specific test suites
- Run assertions on collected data
- Validate data integrity and accuracy
- Generate comprehensive test results

#### **Phase 4: Results and Reporting**
- Aggregate test results across all event types
- Generate formatted output (text/JSON)
- Provide performance metrics and timing
- Exit with appropriate status codes

### Test Structure

### Event Types

The harness tests five main event types with comprehensive validation:

1. **Damage Events** (`damage`) - Player damage tracking and weapon effectiveness
2. **Gunfight Events** (`gunfight`) - Combat encounters and engagement analysis
3. **Grenade Events** (`grenade`) - Grenade usage, effectiveness, and tactical analysis
4. **Round Events** (`round`) - Round-level events and match progression
5. **Player Round Events** (`player-round`) - Per-player round statistics and performance

### Test Framework

Tests use a fluent API for data querying and assertions:

```go
func TestPlayerOneRound1Damage(tc *TestCase) *AssertionResult {
    return tc.Data("damage").
        Where("player", "=", "player one").
        Where("round", "=", 1).
        Assert("damage", "=", 33)
}
```

### Assertion Operators

The framework supports various assertion operators:

- **Comparison**: `=`, `!=`, `>`, `>=`, `<`, `<=`
- **Membership**: `in`, `between`
- **String**: `contains`
- **Aggregate**: `sum`, `avg`, `min`, `max`, `count`
- **Existence**: `exists`

### Filter Examples

```go
// Basic filtering
tc.Data("damage").Where("round_number", "=", 1)

// Multiple conditions
tc.Data("gunfight").
   Where("headshot", "=", true).
   Where("distance", ">", 500)

// Range conditions
tc.Data("damage").Where("damage", "between", map[string]interface{}{
    "min": 30,
    "max": 100,
})

// Array membership
tc.Data("grenade").Where("grenade_type", "in", []string{"hegrenade", "flashbang"})
```

## Example Test Cases

### Damage Events

```go
// Test that damage events exist
tc.Data("damage").Assert("count", ">", 0)

// Test specific player damage in round 1
tc.Data("damage").
   Where("round_number", "=", 1).
   Where("attacker_steam_id", "=", "steam_76561198081165057").
   Assert("damage", ">=", 30)

// Test headshot damage values
tc.Data("damage").
   Where("headshot", "=", true).
   Assert("damage", ">", 90)
```

### Gunfight Events

```go
// Test distance calculations
tc.Data("gunfight").
   Where("distance", ">", 0).
   Where("distance", "<", 3000).
   Assert("count", ">", 0)

// Test weapon variety
tc.Data("gunfight").
   Where("player_1_weapon", "=", "AK-47").
   Assert("count", ">", 0)
```

### Grenade Events

```go
// Test grenade types
tc.Data("grenade").
   Where("grenade_type", "=", "hegrenade").
   Assert("count", ">", 0)

// Test flash effectiveness
tc.Data("grenade").
   Where("grenade_type", "=", "flashbang").
   Where("flash_duration", ">", 0).
   Assert("count", ">=", 0)
```

## Output Formats

### Text Output

```
=== Parser Service Integration Test Results ===

Total Tests:    25
Passed:         23
Failed:         2
Errors:         0
Duration:       15.2s
Success Rate:   92.0%

--- Damage Events ---
Tests: 6 | Passed: 6 | Failed: 0 | Errors: 0 | Duration: 2.1s

✅ TestDamageEventsExist
   Assertion: count > 0
   Duration: 245ms

❌ TestPlayerOneRound1Damage
   Assertion: damage >= 30
   Expected: 30
   Actual:   25
   Duration: 180ms
```

### JSON Output

```json
{
  "start_time": "2024-01-15T10:30:00Z",
  "end_time": "2024-01-15T10:30:15Z",
  "duration": "15.2s",
  "total_tests": 25,
  "passed_tests": 23,
  "failed_tests": 2,
  "error_tests": 0,
  "event_results": [
    {
      "event_type": "damage",
      "total_tests": 6,
      "passed_tests": 6,
      "failed_tests": 0,
      "error_tests": 0,
      "duration": "2.1s",
      "assertions": [...]
    }
  ]
}
```

## Adding New Tests

### 1. Create Test Functions

Add new test functions to the appropriate event test file:

```go
// In damage_tests.go
func (tc *TestClient) TestNewDamageFeature(client *TestClient) *types.AssertionResult {
    testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestNewDamageFeature")
    return testCase.Data("damage").
        Where("new_field", ">", 0).
        Assert("count", ">", 0)
}
```

### 2. Register Test Functions

Add the new function to the test suite:

```go
func (tc *TestClient) getDamageTests() []TestFunction {
    return []TestFunction{
        tc.TestDamageEventsExist,
        tc.TestPlayerOneRound1Damage,
        tc.TestNewDamageFeature, // Add here
        // ... other tests
    }
}
```

### 3. Complex Assertions

For complex assertions, you can implement custom logic:

```go
func (tc *TestClient) TestComplexLogic(client *TestClient) *types.AssertionResult {
    testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestComplexLogic")
    
    // Custom validation logic
    result := testCase.Data("damage").Assert("count", ">", 0)
    
    if result.Status == "passed" {
        // Additional validation
        customCheck := performCustomValidation(result.Actual)
        if !customCheck {
            result.Status = "failed"
            result.ErrorMessage = "Custom validation failed"
        }
    }
    
    return result
}
```

## Error Handling

The test harness provides comprehensive error handling:

- **Network Errors**: Connection timeouts, service unavailable
- **Data Errors**: Invalid JSON, missing fields
- **Assertion Errors**: Failed comparisons, type mismatches
- **Configuration Errors**: Invalid demo files, missing parameters

## Performance

- **Parallel Execution**: Tests run in parallel by default for optimal performance
- **Streaming**: Data is processed as it arrives from the parser service
- **Caching**: Test data is cached to avoid redundant API calls
- **Timeouts**: Configurable timeouts prevent hanging tests

## Troubleshooting

### Common Issues

1. **Demo file not found**: Ensure the demo file path is correct and accessible
2. **Parser service unavailable**: Check that the service is running and accessible
3. **Authentication errors**: Verify API token if required
4. **Timeout errors**: Increase timeout value for large demo files

### Debug Mode

Enable verbose logging for detailed debugging:

```bash
./parser-test --demo demo.dem --log
```

### Specific Test Debugging

Run only specific event tests to isolate issues:

```bash
./parser-test --demo demo.dem --test damage --log --errors
```
## Dependencies

- **Cobra**: CLI framework
- **GJSON**: JSON querying
- **Logrus**: Structured logging
- **Go 1.21+**: Runtime requirement

## License

This project is licensed under the MIT License.

---

*Last updated: September 6, 2025*
*Version: 1.0.0*
