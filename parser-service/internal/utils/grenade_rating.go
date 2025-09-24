package grenade_rating

import (
	"math"
	"parser-service/internal/types"
)

// Clamp helper
func clamp(x, min, max float64) float64 {
	if x < min {
		return min
	} else if x > max {
		return max
	}
	return x
}

func boolToFloat(b bool) float64 {
	if b {
		return 1
	}
	return 0
}

// Compute flash score (-50 -> +50)
func ScoreFlash(GrenadeEvent types.GrenadeEvent) int {
	// Scoring system based on the test expectations
	score := 0.0

	// Enemy duration (clamped to 0-1, then scaled by 20)
	if GrenadeEvent.EnemyFlashDuration != nil {
		eDur := clamp(*GrenadeEvent.EnemyFlashDuration/3.0, 0, 1)
		score += eDur * 20
	}

	// Friendly duration (clamped to 0-1, then scaled by -20)
	if GrenadeEvent.FriendlyFlashDuration != nil {
		fDur := clamp(*GrenadeEvent.FriendlyFlashDuration/3.0, 0, 1)
		score -= fDur * 20
	}

	// Number of enemies flashed (clamped to 0-1, then scaled by 30)
	ePlayers := clamp(float64(GrenadeEvent.EnemyPlayersAffected)/5.0, 0, 1)
	score += ePlayers * 30

	// Number of friendlies flashed (clamped to 0-1, then scaled by -30)
	fPlayers := clamp(float64(GrenadeEvent.FriendlyPlayersAffected)/5.0, 0, 1)
	score -= fPlayers * 30

	// Leads to kill (50 points)
	if GrenadeEvent.FlashLeadsToKill {
		score += 50
	}

	// Leads to death (-50 points)
	if GrenadeEvent.FlashLeadsToDeath {
		score -= 50
	}

	// Cap at -100 to +100
	score = clamp(score, -100, 100)
	return int(math.Round(score))
}

// Score explosive (-50 -> +50)
func ScoreExplosive(GrenadeEvent types.GrenadeEvent) int {
	// New scoring system based on your specifications
	score := 0.0

	// Team damage (1 damage = -0.5 for molotov, -1 for HE)
	teamDamagePenalty := float64(GrenadeEvent.TeamDamageDealt)
	if GrenadeEvent.GrenadeType == "Molotov" || GrenadeEvent.GrenadeType == "Incendiary Grenade" {
		score -= teamDamagePenalty * 0.5 // Molotov penalty
	} else {
		score -= teamDamagePenalty * 1.0 // HE grenade penalty
	}

	// Enemy damage (1 damage = 1 for molotov, 2 for HE)
	enemyDamageBonus := float64(GrenadeEvent.DamageDealt)
	if GrenadeEvent.GrenadeType == "Molotov" || GrenadeEvent.GrenadeType == "Incendiary Grenade" {
		score += enemyDamageBonus * 1.0 // Molotov bonus
	} else {
		score += enemyDamageBonus * 2.0 // HE grenade bonus
	}

	// Cap at -50 to +50
	score = clamp(score, -50, 50)
	return int(math.Round(score))
}

// Score smoke (-100 -> +100)
func ScoreSmoke(TimeBlocked float64, KillsThroughSmoke float64, FriendlyHurt float64) int {
	const (
		maxTime  = 18.0
		maxKills = 3.0
		maxHurt  = 3.0
	)
	w := map[string]float64{"time": 0.35, "kills": 0.35, "hurt": 0.3}

	timeNorm := clamp(TimeBlocked/maxTime, 0, 1)
	killsNorm := clamp(float64(KillsThroughSmoke)/maxKills, 0, 1)
	hurtNorm := clamp(float64(FriendlyHurt)/maxHurt, 0, 1)

	raw := w["time"]*timeNorm + w["kills"]*killsNorm - w["hurt"]*hurtNorm
	raw = clamp(raw, -1, 1)
	return int(math.Round(raw * 100))
}

// ----------------------
// Round Aggregation
// ----------------------

// DecayFactor reduces value for repeated grenades (optional)
func AggregateRound(scores []float64, decayFactor float64) int {
	total := 0.0
	for i, s := range scores {
		weight := math.Pow(decayFactor, float64(i))
		total += s * weight
	}
	return int(math.Round(clamp(total, -100, 100)))
}

func AggregateMatch(roundScores []float64, avgValueLost float64) int {
	if len(roundScores) == 0 {
		return 0
	}

	// Average round score
	sum := 0.0
	for _, r := range roundScores {
		sum += r
	}
	avg := sum / float64(len(roundScores))

	// Penalty for wasted util (scale factor tunable)
	penaltyScale := 50.0
	penalty := avgValueLost * penaltyScale

	return int(clamp(avg-penalty, -100, 100))
}
