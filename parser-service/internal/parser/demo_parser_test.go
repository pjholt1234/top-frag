package parser

import (
	"context"
	"os"
	"path/filepath"
	"testing"

	"github.com/sirupsen/logrus"
	"parser-service/internal/config"
	"parser-service/internal/types"
)

func TestNewDemoParser(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	
	parser := NewDemoParser(cfg, logger)
	
	if parser == nil {
		t.Fatal("Expected DemoParser to be created, got nil")
	}
	
	if parser.config != cfg {
		t.Error("Expected config to be set correctly")
	}
	
	if parser.logger != logger {
		t.Error("Expected logger to be set correctly")
	}
}

func TestDemoParser_ValidateDemoFile(t *testing.T) {
	cfg := &config.Config{
		Parser: config.ParserConfig{
			MaxDemoSize: 100 * 1024 * 1024, // 100MB
		},
	}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)
	
	tests := []struct {
		name        string
		demoPath    string
		expectError bool
		setup       func() string
		cleanup     func(string)
	}{
		{
			name:        "non-existent file",
			demoPath:    "/path/to/nonexistent.dem",
			expectError: true,
		},
		{
			name:        "invalid file extension",
			demoPath:    "/path/to/file.txt",
			expectError: true,
		},
		{
			name:        "valid demo file",
			expectError: false,
			setup: func() string {
				// Create a temporary .dem file
				tmpDir := t.TempDir()
				demoPath := filepath.Join(tmpDir, "test.dem")
				file, err := os.Create(demoPath)
				if err != nil {
					t.Fatalf("Failed to create test file: %v", err)
				}
				defer file.Close()
				
				// Write some content to make it a valid file
				_, err = file.WriteString("demo content")
				if err != nil {
					t.Fatalf("Failed to write to test file: %v", err)
				}
				
				return demoPath
			},
		},
		{
			name:        "file too large",
			expectError: true,
			setup: func() string {
				// Create a temporary .dem file that's too large
				tmpDir := t.TempDir()
				demoPath := filepath.Join(tmpDir, "large.dem")
				file, err := os.Create(demoPath)
				if err != nil {
					t.Fatalf("Failed to create test file: %v", err)
				}
				defer file.Close()
				
				// Write content to make it larger than the max size
				largeContent := make([]byte, 200*1024*1024) // 200MB
				_, err = file.Write(largeContent)
				if err != nil {
					t.Fatalf("Failed to write to test file: %v", err)
				}
				
				return demoPath
			},
		},
	}
	
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var demoPath string
			if tt.setup != nil {
				demoPath = tt.setup()
			} else {
				demoPath = tt.demoPath
			}
			
			err := parser.validateDemoFile(demoPath)
			
			if tt.expectError && err == nil {
				t.Error("Expected error but got none")
			}
			
			if !tt.expectError && err != nil {
				t.Errorf("Expected no error but got: %v", err)
			}
		})
	}
}

func TestDemoParser_BuildParsedData(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)
	
	// Create a test match state
	matchState := &types.MatchState{
		CurrentRound: 5,
		TotalRounds:  30,
		Players: map[string]*types.Player{
			"steam_123": {
				SteamID: "steam_123",
				Name:    "Player1",
				Team:    "T",
			},
			"steam_456": {
				SteamID: "steam_456",
				Name:    "Player2",
				Team:    "CT",
			},
		},
		RoundEvents: []types.RoundEvent{
			{
				RoundNumber: 1,
				EventType:   "end",
				Winner:      demoStringPtr("T"),
			},
			{
				RoundNumber: 2,
				EventType:   "end",
				Winner:      demoStringPtr("CT"),
			},
			{
				RoundNumber: 3,
				EventType:   "end",
				Winner:      demoStringPtr("T"),
			},
		},
		GunfightEvents: []types.GunfightEvent{
			{
				RoundNumber:    1,
				Player1SteamID: "steam_123",
				Player2SteamID: "steam_456",
			},
		},
		GrenadeEvents: []types.GrenadeEvent{
			{
				RoundNumber:   1,
				PlayerSteamID: "steam_123",
				GrenadeType:   "flash",
			},
		},
		DamageEvents: []types.DamageEvent{
			{
				RoundNumber:      1,
				AttackerSteamID: "steam_123",
				VictimSteamID:   "steam_456",
			},
		},
	}
	
	parsedData := parser.buildParsedData(matchState)
	
	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}
	
	// Test match data
	if parsedData.Match.TotalRounds != 30 {
		t.Errorf("Expected total rounds 30, got %d", parsedData.Match.TotalRounds)
	}
	
	if parsedData.Match.WinningTeamScore != 2 {
		t.Errorf("Expected winning team score 2, got %d", parsedData.Match.WinningTeamScore)
	}
	
	if parsedData.Match.LosingTeamScore != 1 {
		t.Errorf("Expected losing team score 1, got %d", parsedData.Match.LosingTeamScore)
	}
	
	// Test players
	if len(parsedData.Players) != 2 {
		t.Errorf("Expected 2 players, got %d", len(parsedData.Players))
	}
	
	// Test events
	if len(parsedData.GunfightEvents) != 1 {
		t.Errorf("Expected 1 gunfight event, got %d", len(parsedData.GunfightEvents))
	}
	
	if len(parsedData.GrenadeEvents) != 1 {
		t.Errorf("Expected 1 grenade event, got %d", len(parsedData.GrenadeEvents))
	}
	
	if len(parsedData.DamageEvents) != 1 {
		t.Errorf("Expected 1 damage event, got %d", len(parsedData.DamageEvents))
	}
	
	if len(parsedData.RoundEvents) != 3 {
		t.Errorf("Expected 3 round events, got %d", len(parsedData.RoundEvents))
	}
}

func TestDemoParser_BuildParsedData_NoRounds(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)
	
	// Create a test match state with no rounds
	matchState := &types.MatchState{
		CurrentRound: 0,
		TotalRounds:  0,
		Players:      make(map[string]*types.Player),
		RoundEvents:  []types.RoundEvent{},
	}
	
	parsedData := parser.buildParsedData(matchState)
	
	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}
	
	// Test default values
	if parsedData.Match.WinningTeamScore != 0 {
		t.Errorf("Expected winning team score 0, got %d", parsedData.Match.WinningTeamScore)
	}
	
	if parsedData.Match.LosingTeamScore != 0 {
		t.Errorf("Expected losing team score 0, got %d", parsedData.Match.LosingTeamScore)
	}
}

func TestDemoParser_BuildParsedData_TieGame(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)
	
	// Create a test match state with tied rounds
	matchState := &types.MatchState{
		CurrentRound: 2,
		TotalRounds:  2,
		Players:      make(map[string]*types.Player),
		RoundEvents: []types.RoundEvent{
			{
				RoundNumber: 1,
				EventType:   "end",
				Winner:      demoStringPtr("T"),
			},
			{
				RoundNumber: 2,
				EventType:   "end",
				Winner:      demoStringPtr("CT"),
			},
		},
	}
	
	parsedData := parser.buildParsedData(matchState)
	
	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}
	
	// Test tie game handling
	if parsedData.Match.WinningTeamScore != 1 {
		t.Errorf("Expected winning team score 1, got %d", parsedData.Match.WinningTeamScore)
	}
	
	if parsedData.Match.LosingTeamScore != 1 {
		t.Errorf("Expected losing team score 1, got %d", parsedData.Match.LosingTeamScore)
	}
}

func TestDemoParser_ParseDemo_InvalidPath(t *testing.T) {
	cfg := &config.Config{
		Parser: config.ParserConfig{
			MaxDemoSize: 100 * 1024 * 1024,
		},
	}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)
	
	ctx := context.Background()
	progressCallback := func(update types.ProgressUpdate) {
		// Mock progress callback
	}
	
	_, err := parser.ParseDemo(ctx, "/nonexistent/path.dem", progressCallback)
	
	if err == nil {
		t.Error("Expected error for non-existent file, got none")
	}
}

func TestDemoParser_ParseDemo_InvalidExtension(t *testing.T) {
	cfg := &config.Config{
		Parser: config.ParserConfig{
			MaxDemoSize: 100 * 1024 * 1024,
		},
	}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)
	
	// Create a temporary file with wrong extension
	tmpDir := t.TempDir()
	invalidPath := filepath.Join(tmpDir, "test.txt")
	file, err := os.Create(invalidPath)
	if err != nil {
		t.Fatalf("Failed to create test file: %v", err)
	}
	defer file.Close()
	
	ctx := context.Background()
	progressCallback := func(update types.ProgressUpdate) {
		// Mock progress callback
	}
	
	_, err = parser.ParseDemo(ctx, invalidPath, progressCallback)
	
	if err == nil {
		t.Error("Expected error for invalid file extension, got none")
	}
}

// Helper function for creating string pointers
func demoStringPtr(s string) *string {
	return &s
} 