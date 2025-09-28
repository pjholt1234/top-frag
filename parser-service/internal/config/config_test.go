package config

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestLoad_DefaultValues(t *testing.T) {
	// Test with no config file (should use defaults)
	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Check default values
	assert.Equal(t, "8080", cfg.Server.Port)
	assert.Equal(t, 30*time.Second, cfg.Server.ReadTimeout)
	assert.Equal(t, 30*time.Second, cfg.Server.WriteTimeout)
	assert.Equal(t, 60*time.Second, cfg.Server.IdleTimeout)
	assert.Equal(t, "", cfg.Server.APIKey)

	assert.Equal(t, 3, cfg.Parser.MaxConcurrentJobs)
	assert.Equal(t, 5*time.Second, cfg.Parser.ProgressInterval)
	assert.Equal(t, int64(500*1024*1024), cfg.Parser.MaxDemoSize) // 500MB
	assert.Equal(t, "/tmp/parser-service", cfg.Parser.TempDir)

	assert.Equal(t, 100, cfg.Batch.GunfightEventsSize)
	assert.Equal(t, 50, cfg.Batch.GrenadeEventsSize)
	assert.Equal(t, 200, cfg.Batch.DamageEventsSize)
	assert.Equal(t, 100, cfg.Batch.RoundEventsSize)
	assert.Equal(t, 3, cfg.Batch.RetryAttempts)
	assert.Equal(t, 1*time.Second, cfg.Batch.RetryDelay)
	assert.Equal(t, 30*time.Second, cfg.Batch.HTTPTimeout)

	assert.Equal(t, "warn", cfg.Logging.Level)
	assert.Equal(t, "json", cfg.Logging.Format)
	assert.Equal(t, "logs/service.log", cfg.Logging.File)
	assert.Equal(t, "logs/errors.log", cfg.Logging.ErrorFile)
}

func TestLoad_ValidConfigFile(t *testing.T) {
	// Create a temporary config file
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	configContent := `
server:
  port: "9090"
  read_timeout: "60s"
  write_timeout: "60s"
  idle_timeout: "120s"
  api_key: "custom-api-key"

parser:
  max_concurrent_jobs: 5
  progress_interval: "10s"
  max_demo_size: 1000000000
  temp_dir: "/custom/temp"

batch:
  gunfight_events_size: 200
  grenade_events_size: 100
  damage_events_size: 400
  round_events_size: 200
  retry_attempts: 5
  retry_delay: "2s"
  http_timeout: "60s"

logging:
  level: "debug"
  format: "text"
  file: "custom.log"
  error_file: "custom-errors.log"
`

	err := os.WriteFile(configPath, []byte(configContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Check custom values
	assert.Equal(t, "9090", cfg.Server.Port)
	assert.Equal(t, 60*time.Second, cfg.Server.ReadTimeout)
	assert.Equal(t, 60*time.Second, cfg.Server.WriteTimeout)
	assert.Equal(t, 120*time.Second, cfg.Server.IdleTimeout)
	assert.Equal(t, "custom-api-key", cfg.Server.APIKey)

	assert.Equal(t, 5, cfg.Parser.MaxConcurrentJobs)
	assert.Equal(t, 10*time.Second, cfg.Parser.ProgressInterval)
	assert.Equal(t, int64(1000000000), cfg.Parser.MaxDemoSize)
	assert.Equal(t, "/custom/temp", cfg.Parser.TempDir)

	assert.Equal(t, 200, cfg.Batch.GunfightEventsSize)
	assert.Equal(t, 100, cfg.Batch.GrenadeEventsSize)
	assert.Equal(t, 400, cfg.Batch.DamageEventsSize)
	assert.Equal(t, 200, cfg.Batch.RoundEventsSize)
	assert.Equal(t, 5, cfg.Batch.RetryAttempts)
	assert.Equal(t, 2*time.Second, cfg.Batch.RetryDelay)
	assert.Equal(t, 60*time.Second, cfg.Batch.HTTPTimeout)

	assert.Equal(t, "debug", cfg.Logging.Level)
	assert.Equal(t, "text", cfg.Logging.Format)
	assert.Equal(t, "custom.log", cfg.Logging.File)
	assert.Equal(t, "custom-errors.log", cfg.Logging.ErrorFile)
}

func TestLoad_InvalidConfigFile(t *testing.T) {
	// Create a temporary invalid config file
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	invalidConfigContent := `
server:
  port: "invalid-port"  # This should be a string, not a number
  read_timeout: "invalid-duration"
`

	err := os.WriteFile(configPath, []byte(invalidConfigContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	// This should fail because of invalid duration
	assert.Error(t, err)
	assert.Nil(t, cfg)
}

func TestLoad_PartialConfigFile(t *testing.T) {
	// Create a config file with only some fields
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	partialConfigContent := `
server:
  port: "3000"

parser:
  max_demo_size: 200000000

logging:
  level: "warn"
`

	err := os.WriteFile(configPath, []byte(partialConfigContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Check specified values
	assert.Equal(t, "3000", cfg.Server.Port)
	assert.Equal(t, int64(200000000), cfg.Parser.MaxDemoSize)
	assert.Equal(t, "warn", cfg.Logging.Level)

	// Check default values for unspecified fields
	assert.Equal(t, 30*time.Second, cfg.Server.ReadTimeout)
	assert.Equal(t, 3, cfg.Parser.MaxConcurrentJobs)
	assert.Equal(t, 100, cfg.Batch.GunfightEventsSize)
	assert.Equal(t, "json", cfg.Logging.Format)
}

func TestLoad_EmptyConfigFile(t *testing.T) {
	// Create an empty config file
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	err := os.WriteFile(configPath, []byte(""), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Should use all default values
	assert.Equal(t, "8080", cfg.Server.Port)
	assert.Equal(t, 30*time.Second, cfg.Server.ReadTimeout)
	assert.Equal(t, 3, cfg.Parser.MaxConcurrentJobs)
	assert.Equal(t, 100, cfg.Batch.GunfightEventsSize)
	assert.Equal(t, "warn", cfg.Logging.Level)
}

func TestLoad_ConfigFileNotExists(t *testing.T) {
	// Test with a non-existent config file (should use defaults)
	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Should use default values
	assert.Equal(t, "8080", cfg.Server.Port)
	assert.Equal(t, 30*time.Second, cfg.Server.ReadTimeout)
	assert.Equal(t, 3, cfg.Parser.MaxConcurrentJobs)
	assert.Equal(t, 100, cfg.Batch.GunfightEventsSize)
	assert.Equal(t, "warn", cfg.Logging.Level)
}

func TestLoad_ConfigFileWithComments(t *testing.T) {
	// Create a config file with comments
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	configContent := `
# Server configuration
server:
  port: "9090"  # Custom port
  read_timeout: "60s"  # 1 minute timeout

# Parser configuration
parser:
  max_concurrent_jobs: 5  # Allow more concurrent jobs
  max_demo_size: 1000000000  # 1GB limit

# Logging configuration
logging:
  level: "debug"  # Debug level for development
  format: "text"  # Human-readable format
`

	err := os.WriteFile(configPath, []byte(configContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Check values (comments should be ignored)
	assert.Equal(t, "9090", cfg.Server.Port)
	assert.Equal(t, 60*time.Second, cfg.Server.ReadTimeout)
	assert.Equal(t, 5, cfg.Parser.MaxConcurrentJobs)
	assert.Equal(t, int64(1000000000), cfg.Parser.MaxDemoSize)
	assert.Equal(t, "debug", cfg.Logging.Level)
	assert.Equal(t, "text", cfg.Logging.Format)
}

func TestLoad_ConfigFileWithInvalidYAML(t *testing.T) {
	// Create a file with invalid YAML syntax
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	invalidYAMLContent := `
server:
  port: "8080"
  invalid: [unclosed array
`

	err := os.WriteFile(configPath, []byte(invalidYAMLContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.Error(t, err)
	assert.Nil(t, cfg)
}

func TestLoad_ConfigFilePermissionDenied(t *testing.T) {
	// Create a config file with no read permissions
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	configContent := `
server:
  port: "8080"
`

	err := os.WriteFile(configPath, []byte(configContent), 0000) // No permissions
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.Error(t, err)
	assert.Nil(t, cfg)
}

func TestLoad_DatabaseConfigDefaults(t *testing.T) {
	// Test with no config file (should use defaults)
	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Check database default values
	assert.Equal(t, "localhost", cfg.Database.Host)
	assert.Equal(t, 3306, cfg.Database.Port)
	assert.Equal(t, "root", cfg.Database.User)
	assert.Equal(t, "", cfg.Database.Password)
	assert.Equal(t, "top-frag-parser", cfg.Database.DBName)
	assert.Equal(t, "utf8mb4", cfg.Database.Charset)
	assert.Equal(t, 10, cfg.Database.MaxIdle)
	assert.Equal(t, 100, cfg.Database.MaxOpen)
	assert.Equal(t, false, cfg.Database.CleanupOnFinish)
}

func TestLoad_DatabaseConfigFromFile(t *testing.T) {
	// Create a temporary config file with database settings
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	configContent := `
database:
  host: "db.example.com"
  port: 5432
  user: "parser_user"
  password: "secure_password"
  dbname: "parser_db"
  charset: "utf8"
  max_idle: 5
  max_open: 50
  cleanup_on_finish: true
`

	err := os.WriteFile(configPath, []byte(configContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Check database values from config file
	assert.Equal(t, "db.example.com", cfg.Database.Host)
	assert.Equal(t, 5432, cfg.Database.Port)
	assert.Equal(t, "parser_user", cfg.Database.User)
	assert.Equal(t, "secure_password", cfg.Database.Password)
	assert.Equal(t, "parser_db", cfg.Database.DBName)
	assert.Equal(t, "utf8", cfg.Database.Charset)
	assert.Equal(t, 5, cfg.Database.MaxIdle)
	assert.Equal(t, 50, cfg.Database.MaxOpen)
	assert.Equal(t, true, cfg.Database.CleanupOnFinish)
}

func TestLoad_EnvironmentVariableOverrides(t *testing.T) {
	// Skip this test for now as viper state is shared across tests
	// This would require a more complex setup to isolate viper state
	t.Skip("Skipping environment variable test due to viper state sharing")
}

func TestLoad_EnvironmentVariableWithConfigFile(t *testing.T) {
	// Skip this test for now as viper state is shared across tests
	// This would require a more complex setup to isolate viper state
	t.Skip("Skipping environment variable test due to viper state sharing")
}

func TestLoad_InvalidDurationInConfig(t *testing.T) {
	// Create a config file with invalid duration
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	configContent := `
server:
  read_timeout: "not-a-duration"
  write_timeout: "60s"
`

	err := os.WriteFile(configPath, []byte(configContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	// This should fail because of invalid duration
	assert.Error(t, err)
	assert.Nil(t, cfg)
}

func TestLoad_InvalidIntegerInConfig(t *testing.T) {
	// Create a config file with invalid integer
	tempDir := t.TempDir()
	configPath := filepath.Join(tempDir, "config.yaml")

	configContent := `
parser:
  max_concurrent_jobs: "not-a-number"
`

	err := os.WriteFile(configPath, []byte(configContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	// This should fail because of invalid integer
	assert.Error(t, err)
	assert.Nil(t, cfg)
}

func TestLoad_ConfigFileInSubdirectory(t *testing.T) {
	// Create a config file in a subdirectory
	tempDir := t.TempDir()
	configDir := filepath.Join(tempDir, "config")
	err := os.MkdirAll(configDir, 0755)
	assert.NoError(t, err)

	configPath := filepath.Join(configDir, "config.yaml")

	configContent := `
server:
  port: "9090"
parser:
  max_concurrent_jobs: 7
`

	err = os.WriteFile(configPath, []byte(configContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Check that config from subdirectory is loaded
	assert.Equal(t, "9090", cfg.Server.Port)
	assert.Equal(t, 7, cfg.Parser.MaxConcurrentJobs)
}

func TestLoad_MultipleConfigFiles(t *testing.T) {
	// Create multiple config files to test precedence
	tempDir := t.TempDir()

	// Create config in root directory
	rootConfigPath := filepath.Join(tempDir, "config.yaml")
	rootConfigContent := `
server:
  port: "8080"
parser:
  max_concurrent_jobs: 3
`
	err := os.WriteFile(rootConfigPath, []byte(rootConfigContent), 0644)
	assert.NoError(t, err)

	// Create config in subdirectory
	configDir := filepath.Join(tempDir, "config")
	err = os.MkdirAll(configDir, 0755)
	assert.NoError(t, err)

	subConfigPath := filepath.Join(configDir, "config.yaml")
	subConfigContent := `
server:
  port: "9090"
parser:
  max_concurrent_jobs: 5
`
	err = os.WriteFile(subConfigPath, []byte(subConfigContent), 0644)
	assert.NoError(t, err)

	// Change to the temp directory so viper can find the config
	originalDir, _ := os.Getwd()
	defer os.Chdir(originalDir)

	err = os.Chdir(tempDir)
	assert.NoError(t, err)

	cfg, err := Load()

	assert.NoError(t, err)
	assert.NotNil(t, cfg)

	// Should use the first config file found (root directory)
	assert.Equal(t, "8080", cfg.Server.Port)
	assert.Equal(t, 3, cfg.Parser.MaxConcurrentJobs)
}
