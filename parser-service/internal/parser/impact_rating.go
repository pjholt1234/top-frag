package parser

import (
	"math"
	"strings"

	"parser-service/internal/types"
)

// ImpactRatingCalculator handles the calculation of impact ratings for gunfight events
type ImpactRatingCalculator struct {
	// Cache for team strengths to avoid recalculation
	teamStrengths map[string]float64
}

// NewImpactRatingCalculator creates a new impact rating calculator
func NewImpactRatingCalculator() *ImpactRatingCalculator {
	return &ImpactRatingCalculator{
		teamStrengths: make(map[string]float64),
	}
}

// CalculateTeamStrength calculates the strength of a team based on man count and equipment
func (irc *ImpactRatingCalculator) CalculateTeamStrength(manCount int, equipmentValue int) float64 {
	manCountStrength := float64(manCount) * types.BasePlayerValue * types.ManCountWeight
	equipmentStrength := float64(equipmentValue) * types.EquipmentWeight

	return manCountStrength + equipmentStrength
}

// CalculateStrengthDifferential calculates the strength differential between two teams
func (irc *ImpactRatingCalculator) CalculateStrengthDifferential(team1Strength, team2Strength float64) float64 {
	// Max possible strength: 5 players * 2000 base value * 0.6 + 25000 equipment * 0.4 = 16000
	maxPossibleStrength := 5.0*types.BasePlayerValue*types.ManCountWeight + 25000.0*types.EquipmentWeight

	return (team2Strength - team1Strength) / maxPossibleStrength
}

// CalculateBaseImpactMultiplier calculates the base impact multiplier based on strength differential
func (irc *ImpactRatingCalculator) CalculateBaseImpactMultiplier(strengthDifferential float64) float64 {
	return 1.0 + (strengthDifferential * types.StrengthDiffMultiplier)
}

// DetermineContextMultiplier determines the context multiplier based on the gunfight scenario
func (irc *ImpactRatingCalculator) DetermineContextMultiplier(roundScenario string, isFirstKill bool, isClutch bool, clutchWon bool) float64 {
	// First gunfight gets bonus
	if isFirstKill {
		return types.OpeningDuelMultiplier
	}

	// Clutch situations
	if isClutch {
		if clutchWon {
			return types.WonClutchMultiplier
		} else {
			return types.FailedClutchMultiplier
		}
	}

	// Standard action
	return types.StandardMultiplier
}

// IsClutchSituation determines if a round scenario represents a clutch situation
func (irc *ImpactRatingCalculator) IsClutchSituation(roundScenario string) bool {
	// Parse round scenario (e.g., "5v4", "1v3", etc.)
	parts := strings.Split(roundScenario, "v")
	if len(parts) != 2 {
		return false
	}

	// Check if one team has 1 player (clutch situation)
	return parts[0] == "1" || parts[1] == "1"
}

// CalculateGunfightImpact calculates the impact for a gunfight event
func (irc *ImpactRatingCalculator) CalculateGunfightImpact(gunfight *types.GunfightEvent, team1Strength, team2Strength float64) {
	// Calculate strength differential
	strengthDifferential := irc.CalculateStrengthDifferential(team1Strength, team2Strength)

	// Calculate base impact multiplier
	baseMultiplier := irc.CalculateBaseImpactMultiplier(strengthDifferential)

	// Determine if this is a clutch situation
	isClutch := irc.IsClutchSituation(gunfight.RoundScenario)

	// For now, assume clutch not won (we'll need to determine this post-parse)
	clutchWon := false

	// Determine context multiplier
	contextMultiplier := irc.DetermineContextMultiplier(
		gunfight.RoundScenario,
		gunfight.IsFirstKill,
		isClutch,
		clutchWon,
	)

	// Calculate final impact multiplier
	finalMultiplier := baseMultiplier * contextMultiplier

	// Calculate impacts
	gunfight.Player1Impact = types.BaseKillImpact * finalMultiplier
	gunfight.Player2Impact = types.BaseDeathImpact * finalMultiplier

	// Debug logging for first few gunfights
	if gunfight.RoundNumber <= 3 {
		// This will be logged by the demo parser
	}

	// Calculate assist impacts
	if gunfight.DamageAssistSteamID != nil {
		gunfight.AssisterImpact = gunfight.Player1Impact * types.AssistWeight
	}

	if gunfight.FlashAssisterSteamID != nil {
		gunfight.FlashAssisterImpact = gunfight.Player1Impact * types.FlashAssistWeight
	}

	// Store team strengths
	gunfight.Player1TeamStrength = team1Strength
	gunfight.Player2TeamStrength = team2Strength

	// Debug logging
	if gunfight.Player1Impact != 0 || gunfight.Player2Impact != 0 {
		// This will be logged by the demo parser
	}
}

// CalculateRoundImpact calculates the total impact for a round
func (irc *ImpactRatingCalculator) CalculateRoundImpact(roundNumber int, gunfights []types.GunfightEvent) (float64, int, float64) {
	var totalImpact float64
	var totalGunfights int

	// Sum up all impacts from gunfights in this round
	for _, gunfight := range gunfights {
		if gunfight.RoundNumber == roundNumber {
			totalImpact += math.Abs(gunfight.Player1Impact) + math.Abs(gunfight.Player2Impact)
			if gunfight.AssisterImpact != 0 {
				totalImpact += math.Abs(gunfight.AssisterImpact)
			}
			if gunfight.FlashAssisterImpact != 0 {
				totalImpact += math.Abs(gunfight.FlashAssisterImpact)
			}
			totalGunfights++
		}
	}

	// Calculate average impact
	var averageImpact float64
	if totalGunfights > 0 {
		averageImpact = totalImpact / float64(totalGunfights)
	}

	return totalImpact, totalGunfights, averageImpact
}

// CalculatePlayerRoundImpact calculates the impact for a specific player in a round
func (irc *ImpactRatingCalculator) CalculatePlayerRoundImpact(playerSteamID string, roundNumber int, gunfights []types.GunfightEvent) float64 {
	var playerImpact float64
	var gunfightsFound int

	// Sum up all impacts where the player was involved
	for _, gunfight := range gunfights {
		if gunfight.RoundNumber == roundNumber {
			gunfightsFound++

			// Check if player was the attacker
			if gunfight.Player1SteamID == playerSteamID {
				playerImpact += gunfight.Player1Impact
			}

			// Check if player was the victim
			if gunfight.Player2SteamID == playerSteamID {
				playerImpact += gunfight.Player2Impact
			}

			// Check if player was the assister
			if gunfight.DamageAssistSteamID != nil && *gunfight.DamageAssistSteamID == playerSteamID {
				playerImpact += gunfight.AssisterImpact
			}

			// Check if player was the flash assister
			if gunfight.FlashAssisterSteamID != nil && *gunfight.FlashAssisterSteamID == playerSteamID {
				playerImpact += gunfight.FlashAssisterImpact
			}
		}
	}

	// Debug logging for first few calculations
	if roundNumber <= 3 && playerSteamID != "" {
		// This will be logged by the demo parser
	}

	return playerImpact
}

// CalculateMatchImpact calculates the total impact for a match
func (irc *ImpactRatingCalculator) CalculateMatchImpact(gunfights []types.GunfightEvent) (float64, float64) {
	var totalImpact float64
	var totalGunfights int

	// Sum up all impacts from all gunfights
	for _, gunfight := range gunfights {
		totalImpact += math.Abs(gunfight.Player1Impact) + math.Abs(gunfight.Player2Impact)
		if gunfight.AssisterImpact != 0 {
			totalImpact += math.Abs(gunfight.AssisterImpact)
		}
		if gunfight.FlashAssisterImpact != 0 {
			totalImpact += math.Abs(gunfight.FlashAssisterImpact)
		}
		totalGunfights++
	}

	// Calculate average impact
	var averageImpact float64
	if totalGunfights > 0 {
		averageImpact = totalImpact / float64(totalGunfights)
	}

	return totalImpact, averageImpact
}

// CalculatePlayerMatchImpact calculates the total impact for a specific player in a match
func (irc *ImpactRatingCalculator) CalculatePlayerMatchImpact(playerSteamID string, gunfights []types.GunfightEvent) float64 {
	var playerImpact float64

	// Sum up all impacts where the player was involved
	for _, gunfight := range gunfights {
		// Check if player was the attacker
		if gunfight.Player1SteamID == playerSteamID {
			playerImpact += gunfight.Player1Impact
		}

		// Check if player was the victim
		if gunfight.Player2SteamID == playerSteamID {
			playerImpact += gunfight.Player2Impact
		}

		// Check if player was the assister
		if gunfight.DamageAssistSteamID != nil && *gunfight.DamageAssistSteamID == playerSteamID {
			playerImpact += gunfight.AssisterImpact
		}

		// Check if player was the flash assister
		if gunfight.FlashAssisterSteamID != nil && *gunfight.FlashAssisterSteamID == playerSteamID {
			playerImpact += gunfight.FlashAssisterImpact
		}
	}

	return playerImpact
}

// CalculateRoundSwingPercent calculates the round swing percentage
func (irc *ImpactRatingCalculator) CalculateRoundSwingPercent(totalImpact float64, maxPossibleImpact float64) float64 {
	if maxPossibleImpact == 0 {
		return 0.0
	}

	return (totalImpact / maxPossibleImpact) * 100.0
}

// CalculateMatchSwingPercent calculates the match swing percentage
func (irc *ImpactRatingCalculator) CalculateMatchSwingPercent(totalImpact float64, maxPossibleImpact float64) float64 {
	if maxPossibleImpact == 0 {
		return 0.0
	}

	return (totalImpact / maxPossibleImpact) * 100.0
}
