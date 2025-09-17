package parser

import (
	"fmt"
	"strconv"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/sirupsen/logrus"
)

// RankExtractor handles extraction of player matchmaking ranks from demos
type RankExtractor struct {
	logger *logrus.Logger
}

// NewRankExtractor creates a new rank extractor
func NewRankExtractor(logger *logrus.Logger) *RankExtractor {
	return &RankExtractor{
		logger: logger,
	}
}

// ExtractPlayerRank attempts to extract the matchmaking rank for a player
func (re *RankExtractor) ExtractPlayerRank(player *common.Player) *string {
	if player == nil {
		return nil
	}

	// Get the rank using the built-in Rank() method
	rank := player.Rank()
	rankType := player.RankType()
	competitiveWins := player.CompetitiveWins()

	// Log the raw values for debugging
	re.logger.WithFields(logrus.Fields{
		"player_name":      player.Name,
		"steam_id":         player.SteamID64,
		"rank":             rank,
		"rank_type":        rankType,
		"competitive_wins": competitiveWins,
	}).Debug("Extracted raw rank data from player")

	// Convert rank to string representation
	rankStr := re.convertRankToString(rank, rankType)

	if rankStr != nil {
		re.logger.WithFields(logrus.Fields{
			"player_name":      player.Name,
			"steam_id":         player.SteamID64,
			"rank_value":       *rankStr,
			"competitive_wins": competitiveWins,
		}).Info("Successfully extracted player rank")
	}

	return rankStr
}

// convertRankToString converts the rank number to a string representation
func (re *RankExtractor) convertRankToString(rank, rankType int) *string {
	// Only process if we have a valid rank and rank type
	if rank <= 0 || rankType != 12 {
		// Rank type 12 appears to be the standard competitive rank type
		// If rank is 0 or rankType is not 12, the player might be unranked
		if rank == 0 && rankType == 12 {
			rankStr := "Unranked"
			return &rankStr
		}
		return nil
	}

	// CS2 competitive ranks mapping (1-18)
	rankNames := map[int]string{
		1:  "Silver I",
		2:  "Silver II",
		3:  "Silver III",
		4:  "Silver IV",
		5:  "Silver Elite",
		6:  "Silver Elite Master",
		7:  "Gold Nova I",
		8:  "Gold Nova II",
		9:  "Gold Nova III",
		10: "Gold Nova Master",
		11: "Master Guardian I",
		12: "Master Guardian II",
		13: "Master Guardian Elite",
		14: "Distinguished Master Guardian",
		15: "Legendary Eagle",
		16: "Legendary Eagle Master",
		17: "Supreme Master First Class",
		18: "Global Elite",
	}

	if rankName, exists := rankNames[rank]; exists {
		return &rankName
	}

	// Fallback for unknown ranks
	rankStr := fmt.Sprintf("Rank %d", rank)
	return &rankStr
}

// GetRankDisplayName converts a rank number to a human-readable rank name
func (re *RankExtractor) GetRankDisplayName(rankValue string) string {
	// If it's already a display name, return as is
	if rankValue == "Unranked" {
		return rankValue
	}

	// Try to parse as number
	rankNum, err := strconv.Atoi(rankValue)
	if err != nil {
		return rankValue // Return original if not a number
	}

	// CS2 competitive ranks mapping
	rankNames := map[int]string{
		1:  "Silver I",
		2:  "Silver II",
		3:  "Silver III",
		4:  "Silver IV",
		5:  "Silver Elite",
		6:  "Silver Elite Master",
		7:  "Gold Nova I",
		8:  "Gold Nova II",
		9:  "Gold Nova III",
		10: "Gold Nova Master",
		11: "Master Guardian I",
		12: "Master Guardian II",
		13: "Master Guardian Elite",
		14: "Distinguished Master Guardian",
		15: "Legendary Eagle",
		16: "Legendary Eagle Master",
		17: "Supreme Master First Class",
		18: "Global Elite",
	}

	if rankName, exists := rankNames[rankNum]; exists {
		return rankName
	}

	return fmt.Sprintf("Rank %d", rankNum)
}

// ExtractPlayerRankInfo extracts comprehensive rank information for a player
func (re *RankExtractor) ExtractPlayerRankInfo(player *common.Player) *PlayerRankInfo {
	if player == nil {
		return nil
	}

	rank := player.Rank()
	rankType := player.RankType()
	competitiveWins := player.CompetitiveWins()

	rankStr := re.convertRankToString(rank, rankType)
	if rankStr == nil {
		return nil
	}

	return &PlayerRankInfo{
		Rank:            *rankStr,
		RankNumber:      rank,
		RankType:        rankType,
		CompetitiveWins: competitiveWins,
	}
}

// PlayerRankInfo contains comprehensive rank information for a player
type PlayerRankInfo struct {
	Rank            string `json:"rank"`
	RankNumber      int    `json:"rank_number"`
	RankType        int    `json:"rank_type"`
	CompetitiveWins int    `json:"competitive_wins"`
}
