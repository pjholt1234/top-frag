package main

import (
	"fmt"
	"os"

	"parser-service/internal/config"
)

func main() {
	fmt.Println("Starting debug version...")

	fmt.Println("Loading configuration...")
	cfg, err := config.Load()
	if err != nil {
		fmt.Printf("Failed to load configuration: %v\n", err)
		os.Exit(1)
	}

	fmt.Println("Configuration loaded successfully")
	fmt.Printf("Server port: %s\n", cfg.Server.Port)
	fmt.Printf("API Key: %s\n", cfg.Server.APIKey)
	fmt.Printf("Database host: %s\n", cfg.Database.Host)

	fmt.Println("Debug version completed successfully")
}
