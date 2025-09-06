package types

// TestConfig holds the configuration for the test runner
type TestConfig struct {
	DemoFile     string
	BaseURL      string
	APIToken     string
	Timeout      int
	Verbose      bool
	ErrorsOnly   bool
	SpecificTest string
	Parallel     bool
	OutputFormat string
}
