package cli

import (
	"fmt"
	"os"
	"path/filepath"

	"github.com/sirupsen/logrus"
	"github.com/spf13/cobra"

	"parser-test/internal/client"
	"parser-test/internal/types"
)

var (
	demoFile     string
	baseURL      string
	apiToken     string
	verbose      bool
	errorsOnly   bool
	timeout      int
	specificTest string
	parallel     bool
	outputFormat string
)

var rootCmd = &cobra.Command{
	Use:   "parser-test",
	Short: "CLI integration test harness for Go parser service",
	Long: `A comprehensive CLI tool for testing a Go parser service that processes 
Counter-Strike demo files. Supports queryable, pluggable assertions on streaming 
parser data from multiple endpoints.`,
	RunE: runTests,
}

func Execute() error {
	return rootCmd.Execute()
}

func init() {
	// Required flags
	rootCmd.Flags().StringVarP(&demoFile, "demo", "d", "", "Path to the demo file (.dem) to process (required)")
	rootCmd.MarkFlagRequired("demo")

	// Optional configuration flags
	rootCmd.Flags().StringVar(&baseURL, "url", "http://localhost:8080", "Base URL of the parser service")
	rootCmd.Flags().StringVar(&apiToken, "token", "", "API token for authentication")
	rootCmd.Flags().IntVar(&timeout, "timeout", 300, "Request timeout in seconds")

	// Output control flags
	rootCmd.Flags().BoolVarP(&verbose, "log", "v", false, "Enable verbose logging")
	rootCmd.Flags().BoolVarP(&errorsOnly, "errors", "e", false, "Show only failed assertions")
	rootCmd.Flags().StringVarP(&outputFormat, "format", "f", "text", "Output format: text, json")

	// Test execution flags
	rootCmd.Flags().StringVarP(&specificTest, "test", "t", "", "Run only specific test file (e.g., 'damage', 'gunfight')")
	rootCmd.Flags().BoolVarP(&parallel, "parallel", "p", true, "Run tests in parallel")
}

func runTests(cmd *cobra.Command, args []string) error {
	// Configure logging
	if verbose {
		logrus.SetLevel(logrus.DebugLevel)
	} else {
		logrus.SetLevel(logrus.InfoLevel)
	}

	// Validate demo file exists
	if _, err := os.Stat(demoFile); os.IsNotExist(err) {
		return fmt.Errorf("demo file not found: %s", demoFile)
	}

	// Get absolute path for demo file
	absPath, err := filepath.Abs(demoFile)
	if err != nil {
		return fmt.Errorf("failed to get absolute path for demo file: %v", err)
	}

	// Create test configuration
	config := &types.TestConfig{
		DemoFile:     absPath,
		BaseURL:      baseURL,
		APIToken:     apiToken,
		Timeout:      timeout,
		Verbose:      verbose,
		ErrorsOnly:   errorsOnly,
		SpecificTest: specificTest,
		Parallel:     parallel,
		OutputFormat: outputFormat,
	}

	// Create test client
	testClient := client.NewTestClient(config)

	// Run the test suite
	results, err := testClient.RunTests()
	if err != nil {
		return fmt.Errorf("failed to run tests: %v", err)
	}

	// Output results
	if err := outputResults(results, config); err != nil {
		return fmt.Errorf("failed to output results: %v", err)
	}

	// Exit with non-zero code if any tests failed
	if results.HasFailures() {
		os.Exit(1)
	}

	return nil
}

func outputResults(results *types.TestResults, config *types.TestConfig) error {
	switch config.OutputFormat {
	case "json":
		return results.OutputJSON(os.Stdout)
	case "text":
		fallthrough
	default:
		return results.OutputText(os.Stdout, config.ErrorsOnly)
	}
}
