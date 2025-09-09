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

// Compute flash score (-100 -> +100)
func ScoreFlash(GrenadeEvent types.GrenadeEvent) int {
	// Weights & caps
	const (
		capDuration = 3.0
		capPlayers  = 5
		capLeads    = 1
		maxRaw      = 100.0 // Reduced from 200 to match actual max possible score
	)
	w := map[string]float64{
		"eDur": 20, "ePlayers": 30, "eKill": 50, // Increased weights for positive effects
		"fDur": 20, "fPlayers": 30, "fDeath": 50, // Increased weights for negative effects
	}

	// Normalize
	var eDur, fDur float64
	if GrenadeEvent.FriendlyFlashDuration != nil {
		fDur = clamp(*GrenadeEvent.FriendlyFlashDuration/capDuration, 0, 1)
	}
	if GrenadeEvent.EnemyFlashDuration != nil {
		eDur = clamp(*GrenadeEvent.EnemyFlashDuration/capDuration, 0, 1)
	}

	ePlayers := clamp(float64(GrenadeEvent.EnemyPlayersAffected)/capPlayers, 0, 1)
	fPlayers := clamp(float64(GrenadeEvent.FriendlyPlayersAffected)/capPlayers, 0, 1)
	eKill := clamp(boolToFloat(GrenadeEvent.FlashLeadsToKill)/capLeads, 0, 1)
	fDeath := clamp(boolToFloat(GrenadeEvent.FlashLeadsToDeath)/capLeads, 0, 1)

	// Calculate individual components
	enemyScore := eDur*w["eDur"] + ePlayers*w["ePlayers"] + eKill*w["eKill"]
	friendlyPenalty := fDur*w["fDur"] + fPlayers*w["fPlayers"] + fDeath*w["fDeath"]

	raw := enemyScore - friendlyPenalty

	// Clamp raw score to [-maxRaw, +maxRaw] and normalize to -100..100
	raw = clamp(raw, -maxRaw, maxRaw)
	finalScore := int(math.Round((raw / maxRaw) * 100))

	return finalScore
}

// Score explosive (-100 -> +100)
func ScoreExplosive(GrenadeEvent types.GrenadeEvent) int {
	const maxDamage = 100.0
	raw := float64(GrenadeEvent.DamageDealt - GrenadeEvent.TeamDamageDealt)
	raw = clamp(raw, -maxDamage, maxDamage)
	return int(math.Round((raw / maxDamage) * 100))
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
