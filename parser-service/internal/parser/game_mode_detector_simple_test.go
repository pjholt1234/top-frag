package parser

import (
	"testing"

	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
)

func TestNewGameModeDetector(t *testing.T) {
	logger := logrus.New()
	detector := NewGameModeDetector(logger)

	assert.NotNil(t, detector)
	assert.Equal(t, logger, detector.logger)
}

func TestGameModeDetector_AnalyzeGameModeFromRankTypes(t *testing.T) {
	logger := logrus.New()
	detector := NewGameModeDetector(logger)

	tests := []struct {
		name           string
		rankTypes      map[int]bool
		rankTypeCounts map[int]int
		expected       *types.GameMode
	}{
		{
			name:           "Competitive mode",
			rankTypes:      map[int]bool{12: true},
			rankTypeCounts: map[int]int{12: 5},
			expected: &types.GameMode{
				Mode:        "competitive",
				DisplayName: "Competitive",
				MaxRounds:   24,
				HasHalftime: true,
			},
		},
		{
			name:           "Premier mode",
			rankTypes:      map[int]bool{11: true},
			rankTypeCounts: map[int]int{11: 5},
			expected: &types.GameMode{
				Mode:        "premier",
				DisplayName: "Premier",
				MaxRounds:   30,
				HasHalftime: true,
			},
		},
		{
			name:           "Danger Zone mode",
			rankTypes:      map[int]bool{14: true},
			rankTypeCounts: map[int]int{14: 5},
			expected: &types.GameMode{
				Mode:        "danger_zone",
				DisplayName: "Danger Zone",
				MaxRounds:   0,
				HasHalftime: false,
			},
		},
		{
			name:           "Mixed rank types",
			rankTypes:      map[int]bool{12: true, 11: true, 14: true},
			rankTypeCounts: map[int]int{12: 2, 11: 2, 14: 1},
			expected: &types.GameMode{
				Mode:        "danger_zone",
				DisplayName: "Danger Zone",
				MaxRounds:   0,
				HasHalftime: false,
			},
		},
		{
			name:           "Unknown rank types",
			rankTypes:      map[int]bool{99: true},
			rankTypeCounts: map[int]int{99: 5},
			expected: &types.GameMode{
				Mode:        "other",
				DisplayName: "Other",
				MaxRounds:   0,
				HasHalftime: false,
			},
		},
		{
			name:           "Empty rank types",
			rankTypes:      map[int]bool{},
			rankTypeCounts: map[int]int{},
			expected: &types.GameMode{
				Mode:        "casual",
				DisplayName: "Casual",
				MaxRounds:   30,
				HasHalftime: false,
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := detector.analyzeGameModeFromRankTypes(tt.rankTypes, tt.rankTypeCounts)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestGameModeDetector_ConcurrentAccess(t *testing.T) {
	logger := logrus.New()
	detector := NewGameModeDetector(logger)

	// Test concurrent access
	done := make(chan bool, 10)

	for i := 0; i < 10; i++ {
		go func(id int) {
			rankTypes := map[int]bool{12: true}
			rankTypeCounts := map[int]int{12: 5}

			result := detector.analyzeGameModeFromRankTypes(rankTypes, rankTypeCounts)
			assert.Equal(t, "competitive", result.Mode)

			done <- true
		}(i)
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}
}
