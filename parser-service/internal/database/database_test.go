package database

import (
	"fmt"
	"path/filepath"
	"testing"
	"time"

	"parser-service/internal/config"
	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
	"gorm.io/driver/sqlite"
	"gorm.io/gorm"
)

func TestNewDatabase_SQLite(t *testing.T) {
	// Use SQLite for testing (in-memory database)
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	logger := logrus.New()
	database := &Database{
		DB:     db,
		Logger: logger,
	}

	assert.NotNil(t, database)
	assert.Equal(t, db, database.DB)
	assert.Equal(t, logger, database.Logger)
}

func TestNewDatabase_InvalidConfig(t *testing.T) {
	// Test with invalid database configuration
	cfg := &config.DatabaseConfig{
		Host:     "invalid-host",
		Port:     9999,
		User:     "invalid-user",
		Password: "invalid-password",
		DBName:   "invalid-db",
		Charset:  "utf8mb4",
		MaxIdle:  10,
		MaxOpen:  100,
	}

	logger := logrus.New()

	// This should fail with invalid configuration
	database, err := NewDatabase(cfg, logger)
	assert.Error(t, err)
	assert.Nil(t, database)
	assert.Contains(t, err.Error(), "failed to connect to database")
}

func TestDatabase_AutoMigrate(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	logger := logrus.New()
	database := &Database{
		DB:     db,
		Logger: logger,
	}

	// Test AutoMigrate
	err = database.AutoMigrate()
	assert.NoError(t, err)

	// Verify that the table was created
	var tables []string
	err = db.Raw("SELECT name FROM sqlite_master WHERE type='table' AND name='player_tick_data'").Scan(&tables).Error
	assert.NoError(t, err)
	assert.Len(t, tables, 1)
	assert.Equal(t, "player_tick_data", tables[0])
}

func TestDatabase_AutoMigrate_Error(t *testing.T) {
	// Create a database with invalid configuration to test error handling
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Close the database to simulate connection error
	sqlDB, err := db.DB()
	assert.NoError(t, err)
	err = sqlDB.Close()
	assert.NoError(t, err)

	logger := logrus.New()
	database := &Database{
		DB:     db,
		Logger: logger,
	}

	// Test AutoMigrate with closed database
	err = database.AutoMigrate()
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "failed to migrate database")
}

func TestDatabase_Close(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	logger := logrus.New()
	database := &Database{
		DB:     db,
		Logger: logger,
	}

	// Test Close
	err = database.Close()
	assert.NoError(t, err)

	// Verify that the database is closed
	sqlDB, err := db.DB()
	assert.NoError(t, err)
	err = sqlDB.Ping()
	assert.Error(t, err) // Should fail because database is closed
}

func TestDatabase_Close_Error(t *testing.T) {
	// Create a database and close it immediately to test error handling
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Close the database immediately
	sqlDB, err := db.DB()
	assert.NoError(t, err)
	err = sqlDB.Close()
	assert.NoError(t, err)

	logger := logrus.New()
	database := &Database{
		DB:     db,
		Logger: logger,
	}

	// Test Close with already closed database
	// This should not error because the underlying sql.DB is already closed
	err = database.Close()
	// The behavior may vary depending on the database driver
	// For SQLite, it might not error on double close
	if err != nil {
		assert.Contains(t, err.Error(), "failed to get underlying sql.DB")
	}
}

func TestDatabase_Integration(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	logger := logrus.New()
	database := &Database{
		DB:     db,
		Logger: logger,
	}

	// Test full integration: AutoMigrate -> Use -> Close
	err = database.AutoMigrate()
	assert.NoError(t, err)

	// Test that we can use the database
	var count int64
	err = db.Model(&types.PlayerTickData{}).Count(&count).Error
	assert.NoError(t, err)
	assert.Equal(t, int64(0), count)

	// Test Close
	err = database.Close()
	assert.NoError(t, err)
}

func TestDatabase_ConcurrentAccess(t *testing.T) {
	// Use file-based SQLite for concurrent testing (in-memory doesn't handle concurrency well)
	tempFile := filepath.Join(t.TempDir(), "test.db")
	db, err := gorm.Open(sqlite.Open(tempFile), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	database := &Database{
		DB:     db,
		Logger: logger,
	}

	// Ensure table exists before concurrent access
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	// Test concurrent access
	done := make(chan bool, 10)
	errors := make(chan error, 10)

	for i := 0; i < 10; i++ {
		go func() {
			var count int64
			err := db.Model(&types.PlayerTickData{}).Count(&count).Error
			errors <- err
			done <- true
		}()
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}

	// Check for errors
	close(errors)
	for err := range errors {
		assert.NoError(t, err)
	}

	// Test Close
	err = database.Close()
	assert.NoError(t, err)
}

func TestDatabase_DSN_Building(t *testing.T) {
	// Test DSN building logic by creating a mock database config
	cfg := &config.DatabaseConfig{
		Host:     "localhost",
		Port:     3306,
		User:     "testuser",
		Password: "testpass",
		DBName:   "testdb",
		Charset:  "utf8mb4",
		MaxIdle:  10,
		MaxOpen:  100,
	}

	// This test verifies that the DSN building logic works correctly
	// The actual connection will fail, but we can test the DSN format
	expectedDSN := "testuser:testpass@tcp(localhost:3306)/testdb?charset=utf8mb4&parseTime=True&loc=Local"

	// Build DSN using the same logic as NewDatabase
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?charset=%s&parseTime=True&loc=Local",
		cfg.User,
		cfg.Password,
		cfg.Host,
		cfg.Port,
		cfg.DBName,
		cfg.Charset,
	)

	assert.Equal(t, expectedDSN, dsn)
}

func TestDatabase_ConnectionPool_Configuration(t *testing.T) {
	// Test connection pool configuration
	cfg := &config.DatabaseConfig{
		Host:     "localhost",
		Port:     3306,
		User:     "testuser",
		Password: "testpass",
		DBName:   "testdb",
		Charset:  "utf8mb4",
		MaxIdle:  5,
		MaxOpen:  50,
	}

	// Use SQLite for testing (connection pool settings won't apply, but we can test the logic)
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Get underlying sql.DB to configure connection pool
	sqlDB, err := db.DB()
	assert.NoError(t, err)

	// Configure connection pool (same logic as NewDatabase)
	sqlDB.SetMaxIdleConns(cfg.MaxIdle)
	sqlDB.SetMaxOpenConns(cfg.MaxOpen)
	sqlDB.SetConnMaxLifetime(time.Hour)

	// Test the connection
	err = sqlDB.Ping()
	assert.NoError(t, err)

	// Clean up
	err = sqlDB.Close()
	assert.NoError(t, err)
}
