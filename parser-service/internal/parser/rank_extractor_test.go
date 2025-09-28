package parser

import (
	"testing"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
)

func TestNewRankExtractor(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	assert.NotNil(t, extractor)
	assert.Equal(t, logger, extractor.logger)
}

func TestRankExtractor_ExtractPlayerRank_NilPlayer(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	result := extractor.ExtractPlayerRank(nil)
	assert.Nil(t, result)
}

func TestRankExtractor_ExtractPlayerRank_ValidPlayer(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	// Create a mock player with rank information
	player := &common.Player{
		SteamID64: 76561198000000000,
		Name:      "TestPlayer",
	}

	// Note: We can't easily mock the rank methods as they are part of the demoinfocs library
	// The actual rank extraction will be tested through integration tests

	result := extractor.ExtractPlayerRank(player)

	assert.NotNil(t, result)
	assert.NotNil(t, result.RankString)
	assert.NotNil(t, result.RankType)
	assert.NotNil(t, result.RankValue)
	assert.Equal(t, 5, *result.RankValue)
	assert.Equal(t, "Competitive", *result.RankType)
}

func TestRankExtractor_ConvertRankToString_Competitive(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	tests := []struct {
		name     string
		rank     int
		rankType int
		expected *string
	}{
		{
			name:     "Silver 1",
			rank:     1,
			rankType: 12,
			expected: stringPtrHelper("Silver 1"),
		},
		{
			name:     "Silver 2",
			rank:     2,
			rankType: 12,
			expected: stringPtrHelper("Silver 2"),
		},
		{
			name:     "Gold Nova 1",
			rank:     7,
			rankType: 12,
			expected: stringPtrHelper("Gold Nova 1"),
		},
		{
			name:     "Gold Nova 2",
			rank:     8,
			rankType: 12,
			expected: stringPtrHelper("Gold Nova 2"),
		},
		{
			name:     "Master Guardian 1",
			rank:     11,
			rankType: 12,
			expected: stringPtrHelper("Master Guardian 1"),
		},
		{
			name:     "Global Elite",
			rank:     18,
			rankType: 12,
			expected: stringPtrHelper("Global Elite"),
		},
		{
			name:     "Unranked",
			rank:     0,
			rankType: 12,
			expected: nil,
		},
		{
			name:     "Invalid rank",
			rank:     99,
			rankType: 12,
			expected: stringPtrHelper("Rank 99"),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := extractor.convertRankToString(tt.rank, tt.rankType)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestRankExtractor_ConvertRankToString_Premier(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	tests := []struct {
		name     string
		rank     int
		rankType int
		expected *string
	}{
		{
			name:     "Premier 0-999",
			rank:     500,
			rankType: 11,
			expected: stringPtrHelper("Premier 500"),
		},
		{
			name:     "Premier 1000-1999",
			rank:     1500,
			rankType: 11,
			expected: stringPtrHelper("Premier 1500"),
		},
		{
			name:     "Premier 2000-2999",
			rank:     2500,
			rankType: 11,
			expected: stringPtrHelper("Premier 2500"),
		},
		{
			name:     "Premier 3000+",
			rank:     3500,
			rankType: 11,
			expected: stringPtrHelper("Premier 3500"),
		},
		{
			name:     "Premier 0",
			rank:     0,
			rankType: 11,
			expected: stringPtrHelper("Premier 0"),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := extractor.convertRankToString(tt.rank, tt.rankType)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestRankExtractor_ConvertRankToString_FaceIT(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	tests := []struct {
		name     string
		rank     int
		rankType int
		expected *string
	}{
		{
			name:     "FaceIT Level 1",
			rank:     1,
			rankType: 14,
			expected: stringPtrHelper("FaceIT Level 1"),
		},
		{
			name:     "FaceIT Level 5",
			rank:     5,
			rankType: 14,
			expected: stringPtrHelper("FaceIT Level 5"),
		},
		{
			name:     "FaceIT Level 10",
			rank:     10,
			rankType: 14,
			expected: stringPtrHelper("FaceIT Level 10"),
		},
		{
			name:     "FaceIT Level 0",
			rank:     0,
			rankType: 14,
			expected: stringPtrHelper("FaceIT Level 0"),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := extractor.convertRankToString(tt.rank, tt.rankType)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestRankExtractor_ConvertRankToString_UnknownType(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	tests := []struct {
		name     string
		rank     int
		rankType int
		expected *string
	}{
		{
			name:     "Unknown type with valid rank",
			rank:     5,
			rankType: 99,
			expected: stringPtrHelper("Rank 5"),
		},
		{
			name:     "Unknown type with zero rank",
			rank:     0,
			rankType: 99,
			expected: nil,
		},
		{
			name:     "Unknown type with negative rank",
			rank:     -1,
			rankType: 99,
			expected: nil,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := extractor.convertRankToString(tt.rank, tt.rankType)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestRankExtractor_ConvertRankTypeToString(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	tests := []struct {
		name     string
		rankType int
		expected *string
	}{
		{
			name:     "Competitive mode",
			rankType: 12,
			expected: stringPtrHelper("Competitive"),
		},
		{
			name:     "Premier mode",
			rankType: 11,
			expected: stringPtrHelper("Premier"),
		},
		{
			name:     "FaceIT mode",
			rankType: 14,
			expected: stringPtrHelper("FaceIT"),
		},
		{
			name:     "Unknown mode",
			rankType: 99,
			expected: stringPtrHelper("Unknown"),
		},
		{
			name:     "Zero mode",
			rankType: 0,
			expected: stringPtrHelper("Unknown"),
		},
		{
			name:     "Negative mode",
			rankType: -1,
			expected: stringPtrHelper("Unknown"),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := extractor.convertRankTypeToString(tt.rankType)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestRankExtractor_ConvertCompetitiveRankToString(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	tests := []struct {
		name     string
		rank     int
		expected *string
	}{
		{
			name:     "Silver 1",
			rank:     1,
			expected: stringPtrHelper("Silver 1"),
		},
		{
			name:     "Silver 2",
			rank:     2,
			expected: stringPtrHelper("Silver 2"),
		},
		{
			name:     "Silver 3",
			rank:     3,
			expected: stringPtrHelper("Silver 3"),
		},
		{
			name:     "Silver 4",
			rank:     4,
			expected: stringPtrHelper("Silver 4"),
		},
		{
			name:     "Silver Elite",
			rank:     5,
			expected: stringPtrHelper("Silver Elite"),
		},
		{
			name:     "Silver Elite Master",
			rank:     6,
			expected: stringPtrHelper("Silver Elite Master"),
		},
		{
			name:     "Gold Nova 1",
			rank:     7,
			expected: stringPtrHelper("Gold Nova 1"),
		},
		{
			name:     "Gold Nova 2",
			rank:     8,
			expected: stringPtrHelper("Gold Nova 2"),
		},
		{
			name:     "Gold Nova 3",
			rank:     9,
			expected: stringPtrHelper("Gold Nova 3"),
		},
		{
			name:     "Master Guardian 1",
			rank:     11,
			expected: stringPtrHelper("Master Guardian 1"),
		},
		{
			name:     "Master Guardian 2",
			rank:     12,
			expected: stringPtrHelper("Master Guardian 2"),
		},
		{
			name:     "Master Guardian Elite",
			rank:     13,
			expected: stringPtrHelper("Master Guardian Elite"),
		},
		{
			name:     "Distinguished Master Guardian",
			rank:     14,
			expected: stringPtrHelper("Distinguished Master Guardian"),
		},
		{
			name:     "Legendary Eagle",
			rank:     15,
			expected: stringPtrHelper("Legendary Eagle"),
		},
		{
			name:     "Legendary Eagle Master",
			rank:     16,
			expected: stringPtrHelper("Legendary Eagle Master"),
		},
		{
			name:     "Supreme Master First Class",
			rank:     17,
			expected: stringPtrHelper("Supreme Master First Class"),
		},
		{
			name:     "Global Elite",
			rank:     18,
			expected: stringPtrHelper("Global Elite"),
		},
		{
			name:     "Unranked",
			rank:     0,
			expected: nil,
		},
		{
			name:     "Invalid rank",
			rank:     99,
			expected: stringPtrHelper("Rank 99"),
		},
		{
			name:     "Negative rank",
			rank:     -1,
			expected: nil,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := extractor.convertCompetitiveRankToString(tt.rank)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestRankExtractor_ConvertPremierRankToString(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	tests := []struct {
		name     string
		rank     int
		expected *string
	}{
		{
			name:     "Premier 0",
			rank:     0,
			expected: stringPtrHelper("Premier 0"),
		},
		{
			name:     "Premier 500",
			rank:     500,
			expected: stringPtrHelper("Premier 500"),
		},
		{
			name:     "Premier 1000",
			rank:     1000,
			expected: stringPtrHelper("Premier 1000"),
		},
		{
			name:     "Premier 1500",
			rank:     1500,
			expected: stringPtrHelper("Premier 1500"),
		},
		{
			name:     "Premier 2000",
			rank:     2000,
			expected: stringPtrHelper("Premier 2000"),
		},
		{
			name:     "Premier 2500",
			rank:     2500,
			expected: stringPtrHelper("Premier 2500"),
		},
		{
			name:     "Premier 3000",
			rank:     3000,
			expected: stringPtrHelper("Premier 3000"),
		},
		{
			name:     "Premier 3500",
			rank:     3500,
			expected: stringPtrHelper("Premier 3500"),
		},
		{
			name:     "Premier 4000",
			rank:     4000,
			expected: stringPtrHelper("Premier 4000"),
		},
		{
			name:     "Premier 5000",
			rank:     5000,
			expected: stringPtrHelper("Premier 5000"),
		},
		{
			name:     "Premier 10000",
			rank:     10000,
			expected: stringPtrHelper("Premier 10000"),
		},
		{
			name:     "Negative rank",
			rank:     -1,
			expected: stringPtrHelper("Premier -1"),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := extractor.convertPremierRankToString(tt.rank)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestRankExtractor_ConvertFaceITRankToString(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	tests := []struct {
		name     string
		rank     int
		expected *string
	}{
		{
			name:     "FaceIT Level 0",
			rank:     0,
			expected: stringPtrHelper("FaceIT Level 0"),
		},
		{
			name:     "FaceIT Level 1",
			rank:     1,
			expected: stringPtrHelper("FaceIT Level 1"),
		},
		{
			name:     "FaceIT Level 5",
			rank:     5,
			expected: stringPtrHelper("FaceIT Level 5"),
		},
		{
			name:     "FaceIT Level 10",
			rank:     10,
			expected: stringPtrHelper("FaceIT Level 10"),
		},
		{
			name:     "FaceIT Level 15",
			rank:     15,
			expected: stringPtrHelper("FaceIT Level 15"),
		},
		{
			name:     "FaceIT Level 20",
			rank:     20,
			expected: stringPtrHelper("FaceIT Level 20"),
		},
		{
			name:     "FaceIT Level 25",
			rank:     25,
			expected: stringPtrHelper("FaceIT Level 25"),
		},
		{
			name:     "FaceIT Level 30",
			rank:     30,
			expected: stringPtrHelper("FaceIT Level 30"),
		},
		{
			name:     "FaceIT Level 50",
			rank:     50,
			expected: stringPtrHelper("FaceIT Level 50"),
		},
		{
			name:     "FaceIT Level 100",
			rank:     100,
			expected: stringPtrHelper("FaceIT Level 100"),
		},
		{
			name:     "Negative level",
			rank:     -1,
			expected: stringPtrHelper("FaceIT Level -1"),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := extractor.convertFaceITRankToString(tt.rank)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestRankExtractor_EdgeCases(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	// Test with very large rank values
	result1 := extractor.convertPremierRankToString(999999)
	assert.Equal(t, stringPtrHelper("Premier 999999"), result1)

	result2 := extractor.convertFaceITRankToString(999)
	assert.Equal(t, stringPtrHelper("FaceIT Level 999"), result2)

	// Test with zero values
	result3 := extractor.convertCompetitiveRankToString(0)
	assert.Nil(t, result3)

	result4 := extractor.convertPremierRankToString(0)
	assert.Equal(t, stringPtrHelper("Premier 0"), result4)

	result5 := extractor.convertFaceITRankToString(0)
	assert.Equal(t, stringPtrHelper("FaceIT Level 0"), result5)
}

func TestRankExtractor_ConcurrentAccess(t *testing.T) {
	logger := logrus.New()
	extractor := NewRankExtractor(logger)

	// Test concurrent access
	done := make(chan bool, 10)

	for i := 0; i < 10; i++ {
		go func(id int) {
			// Test different rank types
			rankType := 12 + (id % 3) // 12, 11, 14
			rank := id + 1

			result := extractor.convertRankToString(rank, rankType)
			assert.NotNil(t, result)

			rankTypeStr := extractor.convertRankTypeToString(rankType)
			assert.NotNil(t, rankTypeStr)

			done <- true
		}(i)
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}
}

// Helper function to create string pointers
func stringPtrHelper(s string) *string {
	return &s
}
