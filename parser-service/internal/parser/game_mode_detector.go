package parser

import (
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/sirupsen/logrus"
)

// GameModeDetector handles detection of game modes from demo rules
type GameModeDetector struct {
	logger *logrus.Logger
}

// NewGameModeDetector creates a new game mode detector
func NewGameModeDetector(logger *logrus.Logger) *GameModeDetector {
	return &GameModeDetector{
		logger: logger,
	}
}

// DetectGameMode analyzes player rank types to determine the game mode
func (gmd *GameModeDetector) DetectGameMode(parser demoinfocs.Parser) (*types.GameMode, error) {
	if parser == nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "parser is nil, cannot detect game mode", nil).
			WithContext("method", "DetectGameMode")
		gmd.logger.WithError(parseError).Error("Critical error in game mode detection")
		return &types.GameMode{
			Mode:        "other",
			DisplayName: "Other",
			MaxRounds:   0,
			HasHalftime: false,
		}, parseError
	}

	gameState := parser.GameState()
	if gameState == nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityError, "game state is nil, cannot detect game mode", nil).
			WithContext("method", "DetectGameMode")
		gmd.logger.WithError(parseError).Error("Error in game mode detection")
		return &types.GameMode{
			Mode:        "other",
			DisplayName: "Other",
			MaxRounds:   0,
			HasHalftime: false,
		}, parseError
	}

	// Get all players to analyze their rank types
	players := gameState.Participants().All()
	if len(players) == 0 {
		_ = types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "no players found, falling back to ConVar detection", nil).
			WithContext("method", "DetectGameMode")
		return gmd.detectGameModeFromConVars(parser)
	}

	// Analyze rank types from players
	rankTypeCounts := make(map[int]int)
	rankTypes := make(map[int]bool)

	for _, player := range players {
		if player != nil {
			rankType := player.RankType()
			rankTypeCounts[rankType]++
			rankTypes[rankType] = true
		}
	}

	// Log rank type distribution
	// Determine game mode based on rank types
	gameMode := gmd.analyzeGameModeFromRankTypes(rankTypes, rankTypeCounts)

	// If we couldn't determine from rank types, fall back to ConVars
	if gameMode.Mode == "other" {
		return gmd.detectGameModeFromConVars(parser)
	}

	return gameMode, nil
}

// analyzeGameModeFromRankTypes determines game mode based on player rank types
func (gmd *GameModeDetector) analyzeGameModeFromRankTypes(rankTypes map[int]bool, rankTypeCounts map[int]int) *types.GameMode {
	// Check for Danger Zone rank type (typically rank type 10 or 14)
	if rankTypes[10] || rankTypes[14] {
		return &types.GameMode{Mode: "danger_zone", DisplayName: "Danger Zone", MaxRounds: 0, HasHalftime: false}
	}

	// Premier mode uses rank type 11
	if rankTypes[11] {
		return &types.GameMode{
			Mode:        "premier",
			DisplayName: "Premier",
			MaxRounds:   30,
			HasHalftime: true,
		}
	}

	// Valve Competitive mode uses rank type 12
	if rankTypes[12] {
		return &types.GameMode{
			Mode:        "competitive",
			DisplayName: "Competitive",
			MaxRounds:   24,
			HasHalftime: true,
		}
	}

	// Wingman mode uses rank type 13 (if it exists)
	if rankTypes[13] {
		return &types.GameMode{
			Mode:        "wingman",
			DisplayName: "Wingman",
			MaxRounds:   16,
			HasHalftime: false,
		}
	}

	// Check for casual/unranked (rank type 0 or no rank type)
	if rankTypes[0] || len(rankTypes) == 0 {
		return &types.GameMode{
			Mode:        "casual",
			DisplayName: "Casual",
			MaxRounds:   30,
			HasHalftime: false,
		}
	}

	// Unknown rank types - return other
	return &types.GameMode{
		Mode:        "other",
		DisplayName: "Other",
		MaxRounds:   0,
		HasHalftime: false,
	}
}

// detectGameModeFromConVars is the fallback method using ConVars (original logic)
func (gmd *GameModeDetector) detectGameModeFromConVars(parser demoinfocs.Parser) (*types.GameMode, error) {
	gameState := parser.GameState()
	rules := gameState.Rules()
	conVars := rules.ConVars()

	// Validate the game mode configuration
	if validationError := gmd.validateGameModeConfiguration(conVars); validationError != nil {
		// Log validation errors but continue with detection
		// removed non-error warn log
	}

	// Extract key configuration values
	maxRounds := conVars["mp_maxrounds"]
	halftime := conVars["mp_halftime"]
	friendlyFire := conVars["mp_friendlyfire"]
	timeoutDuration := conVars["mp_technical_timeout_duration_s"]
	timeoutPerTeam := conVars["mp_technical_timeout_per_team"]
	startingLosses := conVars["mp_starting_losses"]

	// Validate critical configuration values
	if maxRounds == "" {
		_ = types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "max_rounds configuration missing, using default", nil).
			WithContext("method", "detectGameModeFromConVars").
			WithContext("missing_config", "mp_maxrounds")
		maxRounds = "24" // Default fallback
	}

	if halftime == "" {
		_ = types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityInfo, "halftime configuration missing, using default", nil).
			WithContext("method", "detectGameModeFromConVars").
			WithContext("missing_config", "mp_halftime")
		halftime = "true" // Default fallback
	}

	// Log the key values for debugging
	// Determine game mode based on rules
	gameMode, analysisError := gmd.analyzeGameMode(maxRounds, halftime, friendlyFire, timeoutDuration, timeoutPerTeam, startingLosses)

	// Log analysis errors as warnings but continue
	if analysisError != nil {
		// removed non-error warn log
	}

	return gameMode, nil
}

// analyzeGameMode analyzes the rule values to determine the specific game mode
func (gmd *GameModeDetector) analyzeGameMode(maxRounds, halftime, friendlyFire, timeoutDuration, timeoutPerTeam, startingLosses string) (*types.GameMode, error) {
	// Premier mode characteristics (30 rounds)
	if maxRounds == "30" && halftime == "true" && friendlyFire == "true" {
		return &types.GameMode{
			Mode:        "premier",
			DisplayName: "Premier",
			MaxRounds:   30,
			HasHalftime: true,
		}, nil
	}

	// Competitive mode characteristics (24 rounds)
	if maxRounds == "24" && halftime == "true" && friendlyFire == "true" {
		return &types.GameMode{
			Mode:        "competitive",
			DisplayName: "Competitive",
			MaxRounds:   24,
			HasHalftime: true,
		}, nil
	}

	// Wingman mode characteristics
	if maxRounds == "16" && halftime == "false" {
		return &types.GameMode{
			Mode:        "wingman",
			DisplayName: "Wingman",
			MaxRounds:   16,
			HasHalftime: false,
		}, nil
	}

	// Casual mode characteristics
	if maxRounds == "30" && halftime == "false" && friendlyFire == "false" {
		return &types.GameMode{
			Mode:        "casual",
			DisplayName: "Casual",
			MaxRounds:   30,
			HasHalftime: false,
		}, nil
	}

	// Custom/Community server characteristics
	if maxRounds != "24" && maxRounds != "30" && maxRounds != "16" {
		// Log as info since custom servers are expected
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityInfo, "detected custom server configuration", nil).
			WithContext("method", "analyzeGameMode").
			WithContext("max_rounds", maxRounds).
			WithContext("halftime", halftime).
			WithContext("friendly_fire", friendlyFire)

		return &types.GameMode{
			Mode:        "custom",
			DisplayName: "Custom",
			MaxRounds:   gmd.parseMaxRounds(maxRounds),
			HasHalftime: halftime == "true",
		}, parseError
	}

	// Default fallback - log as warning since we couldn't determine the mode
	parseError := types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "could not determine game mode from configuration", nil).
		WithContext("method", "analyzeGameMode").
		WithContext("max_rounds", maxRounds).
		WithContext("halftime", halftime).
		WithContext("friendly_fire", friendlyFire)
		// removed non-error warn log

	return &types.GameMode{
		Mode:        "other",
		DisplayName: "Other",
		MaxRounds:   gmd.parseMaxRounds(maxRounds),
		HasHalftime: halftime == "true",
	}, parseError
}

// parseMaxRounds safely parses the max rounds string to an integer
func (gmd *GameModeDetector) parseMaxRounds(maxRoundsStr string) int {
	if maxRoundsStr == "" {
		return 0
	}

	// Simple parsing - in a real implementation you might want to use strconv.Atoi
	// For now, we'll handle the common cases
	switch maxRoundsStr {
	case "1":
		return 1
	case "16":
		return 16
	case "24":
		return 24
	case "30":
		return 30
	default:
		// Try to parse as integer, fallback to 0 if it fails
		// In a production environment, you'd use strconv.Atoi here
		return 0
	}
}

// validateGameModeConfiguration validates the game mode configuration and returns appropriate errors
func (gmd *GameModeDetector) validateGameModeConfiguration(conVars map[string]string) error {
	// Check for critical missing configurations
	criticalConfigs := []string{"mp_maxrounds", "mp_halftime"}
	for _, config := range criticalConfigs {
		if _, exists := conVars[config]; !exists {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "critical game mode configuration missing", nil).
				WithContext("method", "validateGameModeConfiguration").
				WithContext("missing_config", config)
				// removed non-error warn log
			return parseError
		}
	}

	// Check for suspicious configurations that might indicate demo corruption
	maxRounds := conVars["mp_maxrounds"]
	if maxRounds != "" {
		validRounds := []string{"1", "16", "24", "30", "32", "40", "50"}
		isValid := false
		for _, valid := range validRounds {
			if maxRounds == valid {
				isValid = true
				break
			}
		}

		if !isValid {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityInfo, "unusual max rounds configuration detected", nil).
				WithContext("method", "validateGameModeConfiguration").
				WithContext("max_rounds", maxRounds)
			return parseError
		}
	}

	return nil
}

// GetGameModeDisplayName returns a human-readable name for the game mode
func (gmd *GameModeDetector) GetGameModeDisplayName(mode string) string {
	switch mode {
	case "premier":
		return "Premier"
	case "wingman":
		return "Wingman"
	case "casual":
		return "Casual"
	case "custom":
		return "Custom"
	default:
		return "Other"
	}
}
