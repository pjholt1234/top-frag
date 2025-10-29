package config

import (
	"time"

	"github.com/spf13/viper"
)

// Think of this file as the app.php file in laravel
// It holds the configuration for the application and the default values

// The following structs are used to parse the config file
// The tags mapstructure are used to map the struct fields to the config file keys
// This is using a library called viper to parse the config file

type Config struct {
	Environment   string              `mapstructure:"environment"`
	Server        ServerConfig        `mapstructure:"server"`
	Parser        ParserConfig        `mapstructure:"parser"`
	Batch         BatchConfig         `mapstructure:"batch"`
	Logging       LoggingConfig       `mapstructure:"logging"`
	Database      DatabaseConfig      `mapstructure:"database"`
	AimProcessing AimProcessingConfig `mapstructure:"aim_processing"`
}

type ServerConfig struct {
	Port         string        `mapstructure:"port"`
	ReadTimeout  time.Duration `mapstructure:"read_timeout"`
	WriteTimeout time.Duration `mapstructure:"write_timeout"`
	IdleTimeout  time.Duration `mapstructure:"idle_timeout"`
	APIKey       string        `mapstructure:"api_key"`
}

type ParserConfig struct {
	MaxConcurrentJobs int           `mapstructure:"max_concurrent_jobs"`
	ProgressInterval  time.Duration `mapstructure:"progress_interval"`
	MaxDemoSize       int64         `mapstructure:"max_demo_size"`
	TempDir           string        `mapstructure:"temp_dir"`
	TickSampleRate    int           `mapstructure:"tick_sample_rate"` // Store every Nth tick (1=all, 2=every 2nd, 3=every 3rd)
}

type BatchConfig struct {
	GunfightEventsSize int           `mapstructure:"gunfight_events_size"`
	GrenadeEventsSize  int           `mapstructure:"grenade_events_size"`
	DamageEventsSize   int           `mapstructure:"damage_events_size"`
	RoundEventsSize    int           `mapstructure:"round_events_size"`
	RetryAttempts      int           `mapstructure:"retry_attempts"`
	RetryDelay         time.Duration `mapstructure:"retry_delay"`
	HTTPTimeout        time.Duration `mapstructure:"http_timeout"`
}

type LoggingConfig struct {
	Level             string `mapstructure:"level"`
	Format            string `mapstructure:"format"`
	File              string `mapstructure:"file"`
	ErrorFile         string `mapstructure:"error_file"`
	PerformanceLog    bool   `mapstructure:"performance_log"`
	PerformanceFile   string `mapstructure:"performance_file"`
	PerformanceDetail string `mapstructure:"performance_detail"` // "basic", "detailed", "verbose"
}

type DatabaseConfig struct {
	Host            string `mapstructure:"host"`
	Port            int    `mapstructure:"port"`
	User            string `mapstructure:"user"`
	Password        string `mapstructure:"password"`
	DBName          string `mapstructure:"dbname"`
	Charset         string `mapstructure:"charset"`
	MaxIdle         int    `mapstructure:"max_idle"`
	MaxOpen         int    `mapstructure:"max_open"`
	CleanupOnFinish bool   `mapstructure:"cleanup_on_finish"`
}

type AimProcessingConfig struct {
	LimitAimProcessing bool     `mapstructure:"limit_aim_processing"`
	PlayerIds          []string `mapstructure:"player_ids"`
}

func Load() (*Config, error) {
	viper.SetConfigName("config")
	viper.SetConfigType("yaml")
	viper.AddConfigPath(".")
	viper.AddConfigPath("./config")
	viper.AddConfigPath("/etc/parser-service")

	viper.SetEnvPrefix("PARSER")
	viper.AutomaticEnv()

	setDefaults()

	if err := viper.ReadInConfig(); err != nil {
		if _, ok := err.(viper.ConfigFileNotFoundError); !ok {
			return nil, err
		}
	}

	var config Config
	if err := viper.Unmarshal(&config); err != nil {
		return nil, err
	}

	return &config, nil
}

// This is used to set the default values for the config file
// If the config file doesn't have a value for a field, it will use the default value
func setDefaults() {
	viper.SetDefault("environment", "development")
	viper.SetDefault("server.port", "8080")
	viper.SetDefault("server.read_timeout", "30s")
	viper.SetDefault("server.write_timeout", "30s")
	viper.SetDefault("server.idle_timeout", "60s")
	viper.SetDefault("server.api_key", "")

	viper.SetDefault("parser.max_concurrent_jobs", 3)
	viper.SetDefault("parser.progress_interval", "5s")
	viper.SetDefault("parser.max_demo_size", 500*1024*1024)
	viper.SetDefault("parser.temp_dir", "/tmp/parser-service")
	viper.SetDefault("parser.tick_sample_rate", 2) // Default: store every 2nd tick (50% reduction)

	viper.SetDefault("batch.gunfight_events_size", 100)
	viper.SetDefault("batch.grenade_events_size", 50)
	viper.SetDefault("batch.damage_events_size", 200)
	viper.SetDefault("batch.round_events_size", 100)
	viper.SetDefault("batch.retry_attempts", 3)
	viper.SetDefault("batch.retry_delay", "1s")
	viper.SetDefault("batch.http_timeout", "30s")

	viper.SetDefault("logging.level", "warn")
	viper.SetDefault("logging.format", "json")
	viper.SetDefault("logging.file", "logs/service.log")
	viper.SetDefault("logging.error_file", "logs/errors.log")
	viper.SetDefault("logging.performance_log", true)
	viper.SetDefault("logging.performance_file", "logs/performance.log")
	viper.SetDefault("logging.performance_detail", "detailed")

	viper.SetDefault("database.host", "localhost")
	viper.SetDefault("database.port", 3306)
	viper.SetDefault("database.user", "root")
	viper.SetDefault("database.password", "")
	viper.SetDefault("database.dbname", "top-frag-parser")
	viper.SetDefault("database.charset", "utf8mb4")
	viper.SetDefault("database.max_idle", 10)
	viper.SetDefault("database.max_open", 100)
	viper.SetDefault("database.cleanup_on_finish", false)

	viper.SetDefault("aim_processing.limit_aim_processing", false)
	viper.SetDefault("aim_processing.player_ids", []string{})
}
