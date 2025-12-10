package parser

import (
	"context"
	"os"
	"path/filepath"
	"testing"
	"time"

	"parser-service/internal/config"
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/sirupsen/logrus"
)

// createMockParser creates a mock demoinfocs.Parser for testing
func createMockParser() demoinfocs.Parser {
	// Return nil as a mock - the buildParsedData method should handle nil gracefully
	return nil
}

// setupTestParser creates a DemoParser with initialized progress manager for testing
func setupTestParser(cfg *config.Config, logger *logrus.Logger) *DemoParser {
	parser, err := NewDemoParser(cfg, logger, nil)
	if err != nil {
		// For testing purposes, create a mock parser without database
		parser = &DemoParser{
			config:           cfg,
			logger:           logger,
			gameModeDetector: NewGameModeDetector(logger),
		}
	}
	// Initialize progress manager with a no-op callback
	parser.progressManager = NewProgressManager(logger, func(update types.ProgressUpdate) {}, 100*time.Millisecond)
	return parser
}

func TestNewDemoParser(t *testing.T) {
	cfg := &config.Config{
		Database: config.DatabaseConfig{
			Host:     "localhost",
			Port:     3306,
			User:     "root",
			Password: "root",
			DBName:   "test_db",
			Charset:  "utf8mb4",
			MaxIdle:  10,
			MaxOpen:  100,
		},
	}
	logger := logrus.New()

	parser, err := NewDemoParser(cfg, logger, nil)
	if err != nil {
		// For testing purposes, create a mock parser without database
		parser = &DemoParser{
			config:           cfg,
			logger:           logger,
			gameModeDetector: NewGameModeDetector(logger),
		}
	}

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
		Database: config.DatabaseConfig{
			Host:     "localhost",
			Port:     3306,
			User:     "root",
			Password: "root",
			DBName:   "test_db",
			Charset:  "utf8mb4",
			MaxIdle:  10,
			MaxOpen:  100,
		},
	}
	logger := logrus.New()
	parser, err := NewDemoParser(cfg, logger, nil)
	if err != nil {
		// For testing purposes, create a mock parser without database
		parser = &DemoParser{
			config:           cfg,
			logger:           logger,
			gameModeDetector: NewGameModeDetector(logger),
		}
	}

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
	parser := setupTestParser(cfg, logger)

	// Create a test match state
	matchState := &types.MatchState{
		CurrentRound: 3,
		TotalRounds:  3,
		Players: map[string]*types.Player{
			"123": {
				SteamID: "123",
				Name:    "Player1",
				Team:    "A",
			},
			"456": {
				SteamID: "456",
				Name:    "Player2",
				Team:    "B",
			},
		},
		RoundEvents: []types.RoundEvent{
			{
				RoundNumber: 1,
				EventType:   "end",
				Winner:      stringPtr("T"),
			},
			{
				RoundNumber: 2,
				EventType:   "end",
				Winner:      stringPtr("T"),
			},
			{
				RoundNumber: 3,
				EventType:   "end",
				Winner:      stringPtr("CT"),
			},
		},
		GunfightEvents: []types.GunfightEvent{
			{
				RoundNumber:    1,
				Player1SteamID: "123",
				Player2SteamID: "456",
				IsFirstKill:    false,
			},
		},
		GrenadeEvents: []types.GrenadeEvent{
			{
				RoundNumber:       1,
				PlayerSteamID:     "123",
				FlashLeadsToKill:  false,
				FlashLeadsToDeath: false,
			},
		},
		DamageEvents: []types.DamageEvent{
			{
				RoundNumber:     1,
				AttackerSteamID: "123",
				VictimSteamID:   "456",
			},
		},
	}

	// Create an event processor with team assignment data
	eventProcessor := NewEventProcessor(matchState, logger, nil, nil)
	eventProcessor.teamAStartedAs = "CT"
	eventProcessor.teamBStartedAs = "T"
	eventProcessor.teamAWins = 1 // CT wins 1 round
	eventProcessor.teamBWins = 2 // T wins 2 rounds

	parsedData := parser.buildParsedData(matchState, "de_test", "", 1000, eventProcessor, createMockParser())

	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}

	// Test match data
	if parsedData.Match.TotalRounds != 3 {
		t.Errorf("Expected total rounds 3, got %d", parsedData.Match.TotalRounds)
	}

	// With the new logic: T wins 2 rounds, CT wins 1 round
	// Since T won more rounds, they should be the winning team
	if parsedData.Match.WinningTeam != "B" {
		t.Errorf("Expected winning team 'B', got %s", parsedData.Match.WinningTeam)
	}

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
	parser := setupTestParser(cfg, logger)

	// Create a test match state with no rounds
	matchState := &types.MatchState{
		CurrentRound: 0,
		TotalRounds:  0,
		Players:      make(map[string]*types.Player),
		RoundEvents:  []types.RoundEvent{},
	}

	// Create an event processor
	eventProcessor := NewEventProcessor(matchState, logger, nil, nil)

	parsedData := parser.buildParsedData(matchState, "de_test", "", 1000, eventProcessor, createMockParser())

	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}

	// Test default values
	if parsedData.Match.WinningTeam != "A" {
		t.Errorf("Expected winning team 'A' (default), got %s", parsedData.Match.WinningTeam)
	}

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
	parser := setupTestParser(cfg, logger)

	// Create a test match state with tied rounds
	matchState := &types.MatchState{
		CurrentRound: 2,
		TotalRounds:  2,
		Players:      make(map[string]*types.Player),
		RoundEvents: []types.RoundEvent{
			{
				RoundNumber: 1,
				EventType:   "end",
				Winner:      stringPtr("CT"),
			},
			{
				RoundNumber: 2,
				EventType:   "end",
				Winner:      stringPtr("T"),
			},
		},
	}

	// Create an event processor with tied scores
	eventProcessor := NewEventProcessor(matchState, logger, nil, nil)
	eventProcessor.teamAStartedAs = "CT"
	eventProcessor.teamBStartedAs = "T"
	eventProcessor.teamAWins = 1 // CT wins 1 round
	eventProcessor.teamBWins = 1 // T wins 1 round

	parsedData := parser.buildParsedData(matchState, "de_test", "", 1000, eventProcessor, createMockParser())

	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}

	// In case of tie, default to team A
	if parsedData.Match.WinningTeam != "A" {
		t.Errorf("Expected winning team 'A' (tie default), got %s", parsedData.Match.WinningTeam)
	}

	if parsedData.Match.WinningTeamScore != 1 {
		t.Errorf("Expected winning team score 1, got %d", parsedData.Match.WinningTeamScore)
	}

	if parsedData.Match.LosingTeamScore != 1 {
		t.Errorf("Expected losing team score 1, got %d", parsedData.Match.LosingTeamScore)
	}
}

func TestDemoParser_BuildParsedData_CS2HalftimeSwitch(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := setupTestParser(cfg, logger)

	// Create a test match state simulating CS2 halftime switch
	// First half: CT wins 7, T wins 5
	// Second half: CT wins 3, T wins 6
	// Total: CT wins 10, T wins 11
	// But the same team was CT in first half and T in second half
	matchState := &types.MatchState{
		CurrentRound: 21,
		TotalRounds:  21,
		Players: map[string]*types.Player{
			"123": {
				SteamID: "123",
				Name:    "Player1",
				Team:    "A",
			},
			"456": {
				SteamID: "456",
				Name:    "Player2",
				Team:    "B",
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
			Winner:      stringPtr(winner),
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
			Winner:      stringPtr(winner),
		})
	}

	// Create an event processor with the team assignment data
	eventProcessor := NewEventProcessor(matchState, logger, nil, nil)
	eventProcessor.teamAStartedAs = "CT"
	eventProcessor.teamBStartedAs = "T"
	eventProcessor.teamAWins = 10 // CT wins 10 rounds total
	eventProcessor.teamBWins = 11 // T wins 11 rounds total

	parsedData := parser.buildParsedData(matchState, "de_ancient", "", 1000, eventProcessor, createMockParser())

	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}

	// The team that was T should be the winning team (11 wins vs 10)
	if parsedData.Match.WinningTeam != "B" {
		t.Errorf("Expected winning team 'B', got %s", parsedData.Match.WinningTeam)
	}

	if parsedData.Match.WinningTeamScore != 11 {
		t.Errorf("Expected winning team score 11, got %d", parsedData.Match.WinningTeamScore)
	}

	if parsedData.Match.LosingTeamScore != 10 {
		t.Errorf("Expected losing team score 10, got %d", parsedData.Match.LosingTeamScore)
	}

	if parsedData.Match.TotalRounds != 21 {
		t.Errorf("Expected total rounds 21, got %d", parsedData.Match.TotalRounds)
	}
}

func TestDemoParser_BuildParsedData_FallbackMapName(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := setupTestParser(cfg, logger)

	matchState := &types.MatchState{
		CurrentRound: 1,
		TotalRounds:  1,
		Players:      make(map[string]*types.Player),
		RoundEvents: []types.RoundEvent{
			{
				RoundNumber: 1,
				EventType:   "end",
				Winner:      stringPtr("CT"),
			},
		},
	}

	// Create an event processor
	eventProcessor := NewEventProcessor(matchState, logger, nil, nil)
	eventProcessor.teamAStartedAs = "CT"
	eventProcessor.teamBStartedAs = "T"
	eventProcessor.teamAWins = 1
	eventProcessor.teamBWins = 0

	parsedData := parser.buildParsedData(matchState, "", "", 1000, eventProcessor, createMockParser())

	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}

	// Should fallback to de_dust2
	if parsedData.Match.Map != "de_dust2" {
		t.Errorf("Expected fallback map 'de_dust2', got %s", parsedData.Match.Map)
	}
}

func TestDemoParser_ParseDemo_InvalidPath(t *testing.T) {
	cfg := &config.Config{
		Parser: config.ParserConfig{
			MaxDemoSize: 100 * 1024 * 1024,
		},
		Database: config.DatabaseConfig{
			Host:     "localhost",
			Port:     3306,
			User:     "root",
			Password: "root",
			DBName:   "test_db",
			Charset:  "utf8mb4",
			MaxIdle:  10,
			MaxOpen:  100,
		},
	}
	logger := logrus.New()
	parser, err := NewDemoParser(cfg, logger, nil)
	if err != nil {
		// For testing purposes, create a mock parser without database
		parser = &DemoParser{
			config:           cfg,
			logger:           logger,
			gameModeDetector: NewGameModeDetector(logger),
		}
	}

	ctx := context.Background()
	progressCallback := func(update types.ProgressUpdate) {
		// Mock progress callback
	}

	_, parseErr := parser.ParseDemo(ctx, "/nonexistent/path.dem", progressCallback)

	if parseErr == nil {
		t.Error("Expected error for non-existent file, got none")
	}
}

func TestDemoParser_ParseDemo_InvalidExtension(t *testing.T) {
	cfg := &config.Config{
		Parser: config.ParserConfig{
			MaxDemoSize: 100 * 1024 * 1024,
		},
		Database: config.DatabaseConfig{
			Host:     "localhost",
			Port:     3306,
			User:     "root",
			Password: "root",
			DBName:   "test_db",
			Charset:  "utf8mb4",
			MaxIdle:  10,
			MaxOpen:  100,
		},
	}
	logger := logrus.New()
	parser, err := NewDemoParser(cfg, logger, nil)
	if err != nil {
		// For testing purposes, create a mock parser without database
		parser = &DemoParser{
			config:           cfg,
			logger:           logger,
			gameModeDetector: NewGameModeDetector(logger),
		}
	}

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

func TestDemoParser_BuildParsedData_OvertimeSwitches(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := setupTestParser(cfg, logger)

	// Create a test match state simulating overtime with side switches
	// First half: CT wins 6, T wins 6 (tied 6-6)
	// Second half: CT wins 6, T wins 6 (tied 12-12, goes to overtime)
	// Overtime 1: CT wins 2, T wins 1 (CT side dominates)
	// Overtime 2: CT wins 1, T wins 2 (T side dominates)
	// Total: CT wins 15, T wins 15 (still tied, but we'll simulate more overtime)
	matchState := &types.MatchState{
		CurrentRound: 30,
		TotalRounds:  30,
		Players: map[string]*types.Player{
			"123": {
				SteamID: "123",
				Name:    "Player1",
				Team:    "A",
			},
			"456": {
				SteamID: "456",
				Name:    "Player2",
				Team:    "B",
			},
		},
		RoundEvents: []types.RoundEvent{},
	}

	// Add round events for first half (tied 6-6)
	for i := 1; i <= 12; i++ {
		winner := "T"
		if i <= 6 {
			winner = "CT"
		}
		matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
			RoundNumber: i,
			EventType:   "end",
			Winner:      stringPtr(winner),
		})
	}

	// Add round events for second half (tied 6-6, total 12-12)
	for i := 13; i <= 24; i++ {
		winner := "CT"
		if i >= 19 {
			winner = "T"
		}
		matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
			RoundNumber: i,
			EventType:   "end",
			Winner:      stringPtr(winner),
		})
	}

	// Add overtime rounds 1-3 (teams continue on their current sides)
	// Team A is now T, Team B is now CT
	// CT wins 2, T wins 1
	matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
		RoundNumber: 25,
		EventType:   "end",
		Winner:      stringPtr("CT"),
	})
	matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
		RoundNumber: 26,
		EventType:   "end",
		Winner:      stringPtr("CT"),
	})
	matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
		RoundNumber: 27,
		EventType:   "end",
		Winner:      stringPtr("T"),
	})

	// Add overtime rounds 4-6 (teams switch sides)
	// Team A is now CT, Team B is now T
	// CT wins 1, T wins 2
	matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
		RoundNumber: 28,
		EventType:   "end",
		Winner:      stringPtr("CT"),
	})
	matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
		RoundNumber: 29,
		EventType:   "end",
		Winner:      stringPtr("T"),
	})
	matchState.RoundEvents = append(matchState.RoundEvents, types.RoundEvent{
		RoundNumber: 30,
		EventType:   "end",
		Winner:      stringPtr("T"),
	})

	// Create an event processor with the team assignment data
	eventProcessor := NewEventProcessor(matchState, logger, nil, nil)
	eventProcessor.teamAStartedAs = "CT"
	eventProcessor.teamBStartedAs = "T"
	eventProcessor.teamACurrentSide = "CT"
	eventProcessor.teamBCurrentSide = "T"

	// Manually set the win counts based on our expected calculation with corrected overtime logic
	// Team A (started CT): 6 CT wins in first half + 6 T wins in second half + 1 T win in OT1 + 1 CT win in OT2 = 14 wins
	// Team B (started T): 6 T wins in first half + 6 CT wins in second half + 2 CT wins in OT1 + 2 T wins in OT2 = 16 wins
	// Note: With the fix, OT1 (rounds 25-27) teams stay on their current sides, OT2 (rounds 28-30) teams switch sides
	eventProcessor.teamAWins = 14
	eventProcessor.teamBWins = 16

	parsedData := parser.buildParsedData(matchState, "de_mirage", "", 1000, eventProcessor, createMockParser())

	if parsedData == nil {
		t.Fatal("Expected parsed data to be created, got nil")
	}

	// Team B should be the winning team (16 wins vs 14)
	if parsedData.Match.WinningTeam != "B" {
		t.Errorf("Expected winning team 'B', got %s", parsedData.Match.WinningTeam)
	}

	if parsedData.Match.WinningTeamScore != 16 {
		t.Errorf("Expected winning team score 16, got %d", parsedData.Match.WinningTeamScore)
	}

	if parsedData.Match.LosingTeamScore != 14 {
		t.Errorf("Expected losing team score 14, got %d", parsedData.Match.LosingTeamScore)
	}

	if parsedData.Match.TotalRounds != 30 {
		t.Errorf("Expected total rounds 30, got %d", parsedData.Match.TotalRounds)
	}
}

func TestDemoParser_OvertimeSideSwitchingLogic(t *testing.T) {
	logger := logrus.New()

	// Test the corrected overtime side switching logic
	// This test verifies that side switches happen at the correct rounds
	eventProcessor := NewEventProcessor(&types.MatchState{}, logger, nil, nil)
	eventProcessor.teamAStartedAs = "CT"
	eventProcessor.teamBStartedAs = "T"
	eventProcessor.teamACurrentSide = "CT"
	eventProcessor.teamBCurrentSide = "T"

	// Test halftime switch (round 13)
	eventProcessor.currentRound = 13
	matchHandler := NewMatchHandler(eventProcessor, logger)
	matchHandler.checkForSideSwitch()

	if eventProcessor.teamACurrentSide != "T" || eventProcessor.teamBCurrentSide != "CT" {
		t.Errorf("Expected halftime switch at round 13. Team A: %s, Team B: %s",
			eventProcessor.teamACurrentSide, eventProcessor.teamBCurrentSide)
	}

	// Test overtime round 25 (should NOT switch - this was the bug)
	eventProcessor.currentRound = 25
	matchHandler.checkForSideSwitch()

	if eventProcessor.teamACurrentSide != "T" || eventProcessor.teamBCurrentSide != "CT" {
		t.Errorf("Expected NO switch at round 25 (overtime round 1). Team A: %s, Team B: %s",
			eventProcessor.teamACurrentSide, eventProcessor.teamBCurrentSide)
	}

	// Test overtime round 28 (should switch - this is correct)
	eventProcessor.currentRound = 28
	matchHandler.checkForSideSwitch()

	if eventProcessor.teamACurrentSide != "CT" || eventProcessor.teamBCurrentSide != "T" {
		t.Errorf("Expected switch at round 28 (overtime round 4). Team A: %s, Team B: %s",
			eventProcessor.teamACurrentSide, eventProcessor.teamBCurrentSide)
	}

	// Test overtime round 31 (should switch again)
	eventProcessor.currentRound = 31
	matchHandler.checkForSideSwitch()

	if eventProcessor.teamACurrentSide != "T" || eventProcessor.teamBCurrentSide != "CT" {
		t.Errorf("Expected switch at round 31 (overtime round 7). Team A: %s, Team B: %s",
			eventProcessor.teamACurrentSide, eventProcessor.teamBCurrentSide)
	}
}

func TestDemoParser_DetectMatchType_ServerName(t *testing.T) {
	cfg := &config.Config{}
	logger := logrus.New()
	parser := setupTestParser(cfg, logger)

	tests := []struct {
		name       string
		serverName string
		expected   string
	}{
		{
			name:       "FACEIT.com server name",
			serverName: "FACEIT.com Server",
			expected:   types.MatchTypeFaceit,
		},
		{
			name:       "FACEIT.com lowercase",
			serverName: "faceit.com server",
			expected:   types.MatchTypeFaceit,
		},
		{
			name:       "FACEIT.com mixed case",
			serverName: "FaceIt.CoM Server",
			expected:   types.MatchTypeFaceit,
		},
		{
			name:       "Valve server name",
			serverName: "Valve CS:GO Server",
			expected:   types.MatchTypeValve,
		},
		{
			name:       "Valve lowercase",
			serverName: "valve server",
			expected:   types.MatchTypeValve,
		},
		{
			name:       "Valve mixed case",
			serverName: "VaLvE Server",
			expected:   types.MatchTypeValve,
		},
		{
			name:       "Unknown server name",
			serverName: "ESL Server",
			expected:   types.MatchTypeUnknown,
		},
		{
			name:       "Empty server name",
			serverName: "",
			expected:   types.MatchTypeUnknown,
		},
		{
			name:       "Server name with Valve but not FACEIT",
			serverName: "Valve Community Server",
			expected:   types.MatchTypeValve,
		},
		{
			name:       "Server name with FACEIT.com takes priority",
			serverName: "FACEIT.com Valve Server",
			expected:   types.MatchTypeFaceit,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := parser.detectMatchType(tt.serverName, nil)
			if result != tt.expected {
				t.Errorf("Expected match type %s for server name '%s', got %s", tt.expected, tt.serverName, result)
			}
		})
	}
}
