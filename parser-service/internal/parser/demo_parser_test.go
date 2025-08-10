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
	
	parsedData := parser.buildParsedData(matchState, "de_test")
	
	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}
	
	// Test match data
	if parsedData.Match.TotalRounds != 3 {
		t.Errorf("Expected total rounds 3, got %d", parsedData.Match.TotalRounds)
	}
	
	// With the new logic: T wins 2 rounds, CT wins 1 round
	// Since T dominated (2 wins vs 1), team1 (T) should have 2 wins, team2 (CT) should have 1 win
	if parsedData.Match.WinningTeamScore != 2 {
		t.Errorf("Expected winning team score 2, got %d", parsedData.Match.WinningTeamScore)
	}
	
	if parsedData.Match.LosingTeamScore != 1 {
		t.Errorf("Expected losing team score 1, got %d", parsedData.Match.LosingTeamScore)
	}
	
	// Test map name
	if parsedData.Match.Map != "de_test" {
		t.Errorf("Expected map 'de_test', got %s", parsedData.Match.Map)
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
	
	parsedData := parser.buildParsedData(matchState, "de_test")
	
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
	
	if parsedData.Match.TotalRounds != 0 {
		t.Errorf("Expected total rounds 0, got %d", parsedData.Match.TotalRounds)
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
	
	parsedData := parser.buildParsedData(matchState, "de_test")
	
	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}
	
	// Test tie game handling - both teams should have 1 win
	if parsedData.Match.WinningTeamScore != 1 {
		t.Errorf("Expected winning team score 1, got %d", parsedData.Match.WinningTeamScore)
	}
	
	if parsedData.Match.LosingTeamScore != 1 {
		t.Errorf("Expected losing team score 1, got %d", parsedData.Match.LosingTeamScore)
	}
	
	if parsedData.Match.TotalRounds != 2 {
		t.Errorf("Expected total rounds 2, got %d", parsedData.Match.TotalRounds)
	}
}

func TestDemoParser_BuildParsedData_CS2HalftimeSwitch(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)
	
	// Create a test match state simulating CS2 halftime switch
	// First half: CT wins 7, T wins 5
	// Second half: CT wins 3, T wins 6
	// Total: CT wins 10, T wins 11
	// But the same team was CT in first half and T in second half
	matchState := &types.MatchState{
		CurrentRound: 21,
		TotalRounds:  21,
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
		RoundEvents: []types.RoundEvent{},
	}
	
	// Add round events for first half (CT dominates)
	for i := 1; i <= 12; i++ {
		winner := "T"
		if i <= 7 {
			winner = "CT"
		}
		matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
			RoundNumber: i,
			EventType:   "end",
			Winner:      demoStringPtr(winner),
		})
	}
	
	// Add round events for second half (T dominates)
	for i := 13; i <= 21; i++ {
		winner := "CT"
		if i >= 16 {
			winner = "T"
		}
		matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
			RoundNumber: i,
			EventType:   "end",
			Winner:      demoStringPtr(winner),
		})
	}
	
	parsedData := parser.buildParsedData(matchState, "de_ancient")
	
	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}
	
	// The team that was CT in first half (and won 7 rounds) should be the winning team
	// They should have 7 + 6 = 13 wins total
	if parsedData.Match.WinningTeamScore != 13 {
		t.Errorf("Expected winning team score 13, got %d", parsedData.Match.WinningTeamScore)
	}
	
	// The team that was T in first half (and won 5 rounds) should be the losing team
	// They should have 5 + 3 = 8 wins total
	if parsedData.Match.LosingTeamScore != 8 {
		t.Errorf("Expected losing team score 8, got %d", parsedData.Match.LosingTeamScore)
	}
	
	if parsedData.Match.TotalRounds != 21 {
		t.Errorf("Expected total rounds 21, got %d", parsedData.Match.TotalRounds)
	}
	
	if parsedData.Match.Map != "de_ancient" {
		t.Errorf("Expected map 'de_ancient', got %s", parsedData.Match.Map)
	}
}

func TestDemoParser_BuildParsedData_FallbackMapName(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := NewDemoParser(cfg, logger)
	
	// Create a test match state
	matchState := &types.MatchState{
		CurrentRound: 1,
		TotalRounds:  1,
		Players:      make(map[string]*types.Player),
		RoundEvents: []types.RoundEvent{
			{
				RoundNumber: 1,
				EventType:   "end",
				Winner:      demoStringPtr("CT"),
			},
		},
	}
	
	// Test with empty map name (should fallback to de_dust2)
	parsedData := parser.buildParsedData(matchState, "")
	
	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}
	
	if parsedData.Match.Map != "de_dust2" {
		t.Errorf("Expected fallback map 'de_dust2', got %s", parsedData.Match.Map)
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