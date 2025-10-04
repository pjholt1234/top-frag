package utils

import (
	"parser-service/internal/types"
	"testing"
)

func TestFlashCalculationDebug(t *testing.T) {
	// Create a test grenade event with the exact values from the user
	enemyFlashDuration := 4.020761536
	friendlyFlashDuration := 0.0

	grenadeEvent := types.GrenadeEvent{
		EnemyFlashDuration:      &enemyFlashDuration,
		FriendlyFlashDuration:   &friendlyFlashDuration,
		EnemyPlayersAffected:    2,
		FriendlyPlayersAffected: 0,
		FlashLeadsToKill:        true,
		FlashLeadsToDeath:       false,
	}

	score := ScoreFlash(grenadeEvent)
	t.Logf("Calculated score: %d", score)

	// Expected calculation:
	// eDur = clamp(4.020761536/3.0, 0, 1) = 1.0 (capped)
	// fDur = 0
	// ePlayers = clamp(2/5, 0, 1) = 0.4
	// fPlayers = 0
	// eKill = 1.0
	// fDeath = 0
	//
	// enemyScore = 1.0*20 + 0.4*30 + 1.0*50 = 20 + 12 + 50 = 82
	// friendlyPenalty = 0
	// raw = 82
	// finalScore = round((82/100) * 100) = 82

	expectedScore := 82
	if score != expectedScore {
		t.Errorf("Expected score %d, got %d", expectedScore, score)
	}
}
