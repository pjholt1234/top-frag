package main

import (
	"fmt"
	"os"
	"path/filepath"

	"parser-service/internal/config"
	"parser-service/internal/database"

	"github.com/sirupsen/logrus"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		fmt.Printf("Failed to load configuration: %v\n", err)
		os.Exit(1)
	}

	logger := logrus.New()
	logger.SetLevel(logrus.InfoLevel)

	// Initialize database connection
	db, err := database.NewDatabase(&cfg.Database, logger)
	if err != nil {
		logger.WithError(err).Fatal("Failed to initialize database")
	}
	defer db.Close()

	// Run GORM AutoMigrate
	fmt.Println("Running GORM AutoMigrate...")
	if err := db.AutoMigrate(); err != nil {
		logger.WithError(err).Fatal("Failed to run AutoMigrate")
	}
	fmt.Println("✓ GORM AutoMigrate completed successfully")

	// Run SQL migrations
	migrationsDir := "migrations"
	files, err := os.ReadDir(migrationsDir)
	if err != nil {
		logger.WithError(err).Fatal("Failed to read migrations directory")
	}

	for _, file := range files {
		if filepath.Ext(file.Name()) == ".sql" {
			fmt.Printf("Running SQL migration: %s...\n", file.Name())
			sqlPath := filepath.Join(migrationsDir, file.Name())
			sqlContent, err := os.ReadFile(sqlPath)
			if err != nil {
				logger.WithError(err).Errorf("Failed to read migration file: %s", file.Name())
				continue
			}

			// Execute SQL migration
			if err := db.DB.Exec(string(sqlContent)).Error; err != nil {
				logger.WithError(err).Errorf("Failed to execute migration: %s", file.Name())
				// Continue with other migrations even if one fails
				continue
			}
			fmt.Printf("✓ Migration %s completed successfully\n", file.Name())
		}
	}

	fmt.Println("\nAll migrations completed!")
}
