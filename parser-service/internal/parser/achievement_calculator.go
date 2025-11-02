package parser

import (
	"parser-service/internal/types"
)

// playerRoleScore stores role scores for a player
type playerRoleScore struct {
	steamID string
	fragger float64
	support float64
	opener  float64
	closer  float64
}

// calculateAchievements calculates all achievements for players in the match
func calculateAchievements(playerMatchEvents []types.PlayerMatchEvent, aimEvents []types.AimAnalysisResult) []types.Achievement {
	achievements := make([]types.Achievement, 0)

	if len(playerMatchEvents) == 0 {
		return achievements
	}

	// Calculate role achievements (Fragger, Support, Opener, Closer)
	roleAchievements := calculateRoleAchievements(playerMatchEvents)
	achievements = append(achievements, roleAchievements...)

	// Top aimer (highest aim rating)
	if topAimer := findTopAimer(aimEvents); topAimer != nil {
		achievements = append(achievements, *topAimer)
	}

	// Impact player (highest total_impact)
	if impactPlayer := findImpactPlayer(playerMatchEvents); impactPlayer != nil {
		achievements = append(achievements, *impactPlayer)
	}

	// Difference maker (highest match_swing_percent)
	if diffMaker := findDifferenceMaker(playerMatchEvents); diffMaker != nil {
		achievements = append(achievements, *diffMaker)
	}

	return achievements
}

// calculateRoleAchievements calculates the top player for each role
func calculateRoleAchievements(playerMatchEvents []types.PlayerMatchEvent) []types.Achievement {
	achievements := make([]types.Achievement, 0)

	scores := make([]playerRoleScore, 0, len(playerMatchEvents))

	for _, pme := range playerMatchEvents {
		score := playerRoleScore{
			steamID: pme.PlayerSteamID,
			fragger: calculateFraggerScore(pme),
			support: calculateSupportScore(pme),
			opener:  calculateOpenerScore(pme),
			closer:  calculateCloserScore(pme),
		}
		scores = append(scores, score)
	}

	// Find top player for each role
	if topFragger := findTopInRole(scores, "fragger"); topFragger != "" {
		achievements = append(achievements, types.Achievement{
			PlayerSteamID: topFragger,
			AwardName:     "fragger",
		})
	}

	if topSupport := findTopInRole(scores, "support"); topSupport != "" {
		achievements = append(achievements, types.Achievement{
			PlayerSteamID: topSupport,
			AwardName:     "support",
		})
	}

	if topOpener := findTopInRole(scores, "opener"); topOpener != "" {
		achievements = append(achievements, types.Achievement{
			PlayerSteamID: topOpener,
			AwardName:     "opener",
		})
	}

	if topCloser := findTopInRole(scores, "closer"); topCloser != "" {
		achievements = append(achievements, types.Achievement{
			PlayerSteamID: topCloser,
			AwardName:     "closer",
		})
	}

	return achievements
}

// findTopInRole finds the player with the highest score for a specific role
func findTopInRole(scores []playerRoleScore, role string) string {
	if len(scores) == 0 {
		return ""
	}

	var topPlayer string
	var topScore float64 = -1

	for _, score := range scores {
		var currentScore float64
		switch role {
		case "fragger":
			currentScore = score.fragger
		case "support":
			currentScore = score.support
		case "opener":
			currentScore = score.opener
		case "closer":
			currentScore = score.closer
		default:
			continue
		}

		if currentScore > topScore {
			topScore = currentScore
			topPlayer = score.steamID
		}
	}

	// Only return if score is valid (> 0)
	if topScore > 0 {
		return topPlayer
	}

	return ""
}

// calculateFraggerScore calculates the fragger role score based on config values
func calculateFraggerScore(pme types.PlayerMatchEvent) float64 {
	if pme.Deaths == 0 && pme.Kills == 0 {
		return 0
	}

	// Calculate number of rounds played (estimate from deaths + 1)
	roundsPlayed := float64(pme.Deaths + 1)
	if roundsPlayed == 0 {
		roundsPlayed = 1
	}

	scores := []struct {
		score  float64
		weight float64
	}{
		// Kill death ratio (max 1.5, weight 2.0)
		{normalizeScore(float64(pme.Kills)/max(float64(pme.Deaths), 1.0), 1.5, true), 2.0},
		// Total kills per round (max 0.9, weight 4.0)
		{normalizeScore(float64(pme.Kills)/roundsPlayed, 0.9, true), 4.0},
		// Average damage per round (max 90, weight 3.0)
		{normalizeScore(pme.ADR, 90.0, true), 3.0},
		// Trade kill percentage (max 50%, weight 3.0)
		{normalizeScore(calculatePercentage(pme.TotalSuccessfulTrades, pme.TotalPossibleTrades), 50.0, true), 3.0},
		// Trade opportunities per round (max 1.5, weight 1.0)
		{normalizeScore(float64(pme.TotalPossibleTrades)/roundsPlayed, 1.5, true), 1.0},
	}

	return calculateWeightedMean(scores)
}

// calculateSupportScore calculates the support role score
func calculateSupportScore(pme types.PlayerMatchEvent) float64 {
	totalGrenades := pme.FlashesThrown + pme.FireGrenadesThrown + pme.SmokesThrown + pme.HesThrown + pme.DecoysThrown

	scores := []struct {
		score  float64
		weight float64
	}{
		// Total grenades thrown (max 25, weight 1.0)
		{normalizeScore(float64(totalGrenades), 25.0, true), 1.0},
		// Damage dealt from grenades (max 200, weight 2.0)
		{normalizeScore(float64(pme.DamageDealt), 200.0, true), 2.0},
		// Enemy flash duration (max 30, weight 2.0)
		{normalizeScore(pme.EnemyFlashDuration, 30.0, true), 2.0},
		// Average grenade effectiveness (max 50, weight 5.0)
		{normalizeScore(float64(pme.AverageGrenadeEffectiveness), 50.0, true), 5.0},
		// Total flashes leading to kills (max 5, weight 2.0)
		{normalizeScore(float64(pme.FlashesLeadingToKills), 5.0, true), 2.0},
	}

	return calculateWeightedMean(scores)
}

// calculateOpenerScore calculates the opener role score
func calculateOpenerScore(pme types.PlayerMatchEvent) float64 {
	firstKillPlusMinus := pme.FirstKills - pme.FirstDeaths
	firstKillAttempts := pme.FirstKills + pme.FirstDeaths

	scores := []struct {
		score  float64
		weight float64
	}{
		// Average round time of death (max 25, lower is better, weight 1.0)
		{normalizeScore(pme.AverageRoundTimeOfDeath, 25.0, false), 1.0},
		// Average time to contact (max 20, lower is better, weight 3.0)
		{normalizeScore(pme.AverageTimeToContact, 20.0, false), 3.0},
		// First kills plus minus (max 3, weight 5.0)
		{normalizeScore(float64(firstKillPlusMinus), 3.0, true), 5.0},
		// First kill attempts (max 4, weight 4.0)
		{normalizeScore(float64(firstKillAttempts), 4.0, true), 4.0},
		// Traded death percentage (max 50%, weight 2.0)
		{normalizeScore(calculatePercentage(pme.TotalTradedDeaths, pme.TotalPossibleTradedDeaths), 50.0, true), 2.0},
	}

	return calculateWeightedMean(scores)
}

// calculateCloserScore calculates the closer role score
func calculateCloserScore(pme types.PlayerMatchEvent) float64 {
	totalClutchAttempts := pme.ClutchAttempts1v1 + pme.ClutchAttempts1v2 + pme.ClutchAttempts1v3 + pme.ClutchAttempts1v4 + pme.ClutchAttempts1v5
	totalClutchWins := pme.ClutchWins1v1 + pme.ClutchWins1v2 + pme.ClutchWins1v3 + pme.ClutchWins1v4 + pme.ClutchWins1v5

	scores := []struct {
		score  float64
		weight float64
	}{
		// Average round time to death (max 40, higher is better, weight 1.0)
		{normalizeScore(pme.AverageRoundTimeOfDeath, 40.0, true), 1.0},
		// Average round time to contact (max 35, higher is better, weight 1.0)
		{normalizeScore(pme.AverageTimeToContact, 35.0, true), 1.0},
		// Clutch win percentage (max 25%, weight 4.0)
		{normalizeScore(calculatePercentage(totalClutchWins, totalClutchAttempts), 25.0, true), 4.0},
		// Total clutch attempts (max 5, weight 2.0)
		{normalizeScore(float64(totalClutchAttempts), 5.0, true), 2.0},
	}

	return calculateWeightedMean(scores)
}

// findTopAimer finds the player with the highest aim rating
func findTopAimer(aimEvents []types.AimAnalysisResult) *types.Achievement {
	if len(aimEvents) == 0 {
		return nil
	}

	var topPlayer string
	var topRating float64 = -1

	for _, event := range aimEvents {
		if event.AimRating > topRating {
			topRating = event.AimRating
			topPlayer = event.PlayerSteamID
		}
	}

	if topRating > 0 && topPlayer != "" {
		return &types.Achievement{
			PlayerSteamID: topPlayer,
			AwardName:     "top_aimer",
		}
	}

	return nil
}

// findImpactPlayer finds the player with the highest total impact
func findImpactPlayer(playerMatchEvents []types.PlayerMatchEvent) *types.Achievement {
	if len(playerMatchEvents) == 0 {
		return nil
	}

	var topPlayer string
	var topImpact float64 = -1

	for _, pme := range playerMatchEvents {
		if pme.TotalImpact > topImpact {
			topImpact = pme.TotalImpact
			topPlayer = pme.PlayerSteamID
		}
	}

	if topImpact > 0 && topPlayer != "" {
		return &types.Achievement{
			PlayerSteamID: topPlayer,
			AwardName:     "impact_player",
		}
	}

	return nil
}

// findDifferenceMaker finds the player with the highest match swing percent
func findDifferenceMaker(playerMatchEvents []types.PlayerMatchEvent) *types.Achievement {
	if len(playerMatchEvents) == 0 {
		return nil
	}

	var topPlayer string
	var topSwing float64 = -1

	for _, pme := range playerMatchEvents {
		if pme.MatchSwingPercent > topSwing {
			topSwing = pme.MatchSwingPercent
			topPlayer = pme.PlayerSteamID
		}
	}

	if topSwing > 0 && topPlayer != "" {
		return &types.Achievement{
			PlayerSteamID: topPlayer,
			AwardName:     "difference_maker",
		}
	}

	return nil
}

// normalizeScore normalizes a score based on max value and direction
func normalizeScore(value, maxValue float64, higherBetter bool) float64 {
	if maxValue == 0 {
		return 0
	}

	var score float64
	if higherBetter {
		score = value / maxValue
	} else {
		score = 1 - (value / maxValue)
	}

	// Clamp between 0 and 1
	if score < 0 {
		score = 0
	} else if score > 1 {
		score = 1
	}

	return score * 100 // Scale to 0-100
}

// calculateWeightedMean calculates the weighted mean of scores
func calculateWeightedMean(scores []struct {
	score  float64
	weight float64
}) float64 {
	if len(scores) == 0 {
		return 0
	}

	var totalWeightedScore float64
	var totalWeight float64

	for _, s := range scores {
		totalWeightedScore += s.score * s.weight
		totalWeight += s.weight
	}

	if totalWeight == 0 {
		return 0
	}

	return totalWeightedScore / totalWeight
}

// calculatePercentage calculates percentage, handling division by zero
func calculatePercentage(numerator, denominator int) float64 {
	if denominator == 0 {
		return 0
	}
	return (float64(numerator) / float64(denominator)) * 100.0
}

// max returns the maximum of two float64 values
func max(a, b float64) float64 {
	if a > b {
		return a
	}
	return b
}
