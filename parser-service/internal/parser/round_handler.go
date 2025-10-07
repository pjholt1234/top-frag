package parser

import (
	"math"
	"parser-service/internal/types"
	"strconv"
	"strings"

	"github.com/sirupsen/logrus"
)

// RoundHandler handles round-level aggregation of player statistics
type RoundHandler struct {
	processor *EventProcessor
	logger    *logrus.Logger
}

// NewRoundHandler creates a new round handler
func NewRoundHandler(processor *EventProcessor, logger *logrus.Logger) *RoundHandler {
	return &RoundHandler{
		processor: processor,
		logger:    logger,
	}
}

func (rh *RoundHandler) ProcessRoundEnd() error {
	if rh.processor == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "processor is nil", nil).
			WithContext("event_type", "ProcessRoundEnd")
	}

	if rh.processor.matchState == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityCritical, "match state is nil", nil).
			WithContext("event_type", "ProcessRoundEnd")
	}

	roundNumber := rh.processor.matchState.CurrentRound

	if roundNumber <= 0 {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "invalid round number", nil).
			WithContext("round_number", roundNumber).
			WithContext("event_type", "ProcessRoundEnd")
	}

	// Processing round-level player statistics

	playersInRound := rh.getPlayersInRound(roundNumber)

	if len(playersInRound) == 0 {
		return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "no players found in round", nil).
			WithContext("round_number", roundNumber).
			WithContext("event_type", "ProcessRoundEnd")
	}

	for _, playerSteamID := range playersInRound {
		if playerSteamID == "" {
			return types.NewParseErrorWithSeverity(types.ErrorTypeEventProcessing, types.ErrorSeverityWarning, "empty player steam ID found", nil).
				WithContext("round_number", roundNumber).
				WithContext("event_type", "ProcessRoundEnd")
		}

		playerRoundEvent := rh.createPlayerRoundEvent(playerSteamID, roundNumber)
		rh.processor.matchState.PlayerRoundEvents = append(rh.processor.matchState.PlayerRoundEvents, playerRoundEvent)
	}

	// Calculate impact values for gunfight events in this round
	rh.calculateRoundImpact(roundNumber)

	// Completed round-level player statistics processing

	return nil
}

// getPlayersInRound returns all players that are in the match, regardless of event participation
func (rh *RoundHandler) getPlayersInRound(roundNumber int) []string {
	// Get all players from match state - these are all players who connected to the match
	players := make([]string, 0, len(rh.processor.matchState.Players))
	for steamID := range rh.processor.matchState.Players {
		players = append(players, steamID)
	}

	return players
}

// createPlayerRoundEvent creates a comprehensive player round event by aggregating data from all event types
func (rh *RoundHandler) createPlayerRoundEvent(playerSteamID string, roundNumber int) types.PlayerRoundEvent {
	// Initialize the player round event
	playerRoundEvent := types.PlayerRoundEvent{
		PlayerSteamID: playerSteamID,
		RoundNumber:   roundNumber,
	}

	// Basic gun fight metrics
	rh.aggregateGunfightMetrics(&playerRoundEvent, playerSteamID, roundNumber)

	// Economy metrics (needed before grenade metrics for utility loss penalty)
	rh.aggregateEconomyMetrics(&playerRoundEvent, playerSteamID, roundNumber)

	// Grenade metrics
	rh.aggregateGrenadeMetrics(&playerRoundEvent, playerSteamID, roundNumber)

	// Trade metrics
	rh.aggregateTradeMetrics(&playerRoundEvent, playerSteamID, roundNumber)

	// Clutch metrics
	rh.aggregateClutchMetrics(&playerRoundEvent, playerSteamID, roundNumber)

	// Time to Contact
	rh.aggregateTimeToContact(&playerRoundEvent, playerSteamID, roundNumber)

	// Calculate impact values by aggregating from gunfight events
	rh.aggregateImpactMetrics(&playerRoundEvent, playerSteamID, roundNumber)

	return playerRoundEvent
}

func (rh *RoundHandler) aggregateGunfightMetrics(event *types.PlayerRoundEvent, playerSteamID string, roundNumber int) {
	kills := 0
	assists := 0
	died := false
	damage := 0
	headshots := 0
	firstKill := false
	firstDeath := false
	var roundTimeOfDeath *int
	killsWithAWP := 0

	for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
		if gunfightEvent.RoundNumber != roundNumber {
			continue
		}

		if gunfightEvent.Player1SteamID == playerSteamID && gunfightEvent.VictorSteamID != nil && *gunfightEvent.VictorSteamID == playerSteamID {
			kills++

			if gunfightEvent.Headshot {
				headshots++
			}

			if gunfightEvent.IsFirstKill {
				firstKill = true
			}

			weaponName := gunfightEvent.Player1Weapon
			if weaponName == "AWP" {
				killsWithAWP++
			}
		}

		if gunfightEvent.Player2SteamID == playerSteamID && gunfightEvent.VictorSteamID != nil && *gunfightEvent.VictorSteamID != playerSteamID {
			died = true
			timeOfDeath := gunfightEvent.RoundTime
			roundTimeOfDeath = &timeOfDeath

			if rh.processor.matchState.FirstDeathPlayer != nil && *rh.processor.matchState.FirstDeathPlayer == playerSteamID {
				firstDeath = true
			}
		}

		if gunfightEvent.DamageAssistSteamID != nil && *gunfightEvent.DamageAssistSteamID == playerSteamID {
			assists++
		}
	}

	for _, damageEvent := range rh.processor.matchState.DamageEvents {
		if damageEvent.RoundNumber == roundNumber && damageEvent.AttackerSteamID == playerSteamID {
			// Only count damage dealt to enemies, not teammates
			attackerTeam := rh.processor.getAssignedTeam(playerSteamID)
			victimTeam := rh.processor.getAssignedTeam(damageEvent.VictimSteamID)

			if attackerTeam != victimTeam {
				damage += damageEvent.HealthDamage
			}
		}
	}

	// Set the aggregated values
	event.Kills = kills
	event.Assists = assists
	event.Died = died
	event.Damage = damage
	event.Headshots = headshots
	event.FirstKill = firstKill
	event.FirstDeath = firstDeath
	event.RoundTimeOfDeath = roundTimeOfDeath
	event.KillsWithAWP = killsWithAWP
}

// aggregateGrenadeMetrics aggregates grenade-related statistics from grenade events
func (rh *RoundHandler) aggregateGrenadeMetrics(event *types.PlayerRoundEvent, playerSteamID string, roundNumber int) {
	damageDealt := 0
	flashesThrown := 0
	fireGrenadesThrown := 0
	smokesThrown := 0
	hesThrown := 0
	decoysThrown := 0
	friendlyFlashDuration := 0.0
	enemyFlashDuration := 0.0
	friendlyPlayersAffected := 0
	enemyPlayersAffected := 0
	flashesLeadingToKill := 0
	flashesLeadingToDeath := 0
	smokeBlockingDuration := 0

	// For right now we're only counting the effectiveness flashbangs + fire grenades
	totalEffectivenessRating := 0
	totalNumberOfMeasuredGrenades := 0

	for _, grenadeEvent := range rh.processor.matchState.GrenadeEvents {
		if grenadeEvent.RoundNumber != roundNumber || grenadeEvent.PlayerSteamID != playerSteamID {
			continue
		}

		// Count different grenade types
		if grenadeEvent.GrenadeType == types.GrenadeTypeHE || grenadeEvent.GrenadeType == "HE Grenade" {
			hesThrown++
			damageDealt += grenadeEvent.DamageDealt
			totalEffectivenessRating += grenadeEvent.EffectivenessRating
			totalNumberOfMeasuredGrenades++
		}

		if grenadeEvent.GrenadeType == types.GrenadeTypeMolotov || grenadeEvent.GrenadeType == "Molotov" ||
			grenadeEvent.GrenadeType == types.GrenadeTypeIncendiary || grenadeEvent.GrenadeType == "Incendiary Grenade" {
			fireGrenadesThrown++
			damageDealt += grenadeEvent.DamageDealt
			totalEffectivenessRating += grenadeEvent.EffectivenessRating
			totalNumberOfMeasuredGrenades++
		}

		if grenadeEvent.GrenadeType == types.GrenadeTypeSmoke || grenadeEvent.GrenadeType == "Smoke Grenade" {
			smokesThrown++
			// Include smoke effectiveness in total rating
			totalEffectivenessRating += grenadeEvent.EffectivenessRating
			totalNumberOfMeasuredGrenades++
			// Aggregate smoke blocking duration
			smokeBlockingDuration += grenadeEvent.SmokeBlockingDuration
		}

		if grenadeEvent.GrenadeType == types.GrenadeTypeDecoy || grenadeEvent.GrenadeType == "Decoy Grenade" {
			decoysThrown++
		}

		if grenadeEvent.GrenadeType == types.GrenadeTypeFlash || grenadeEvent.GrenadeType == "Flashbang" {
			// Count flashbangs thrown
			flashesThrown++

			totalEffectivenessRating += grenadeEvent.EffectivenessRating
			totalNumberOfMeasuredGrenades++

			// Flash duration on enemies (total time this player's flashes affected enemies)
			if grenadeEvent.EnemyFlashDuration != nil {
				enemyFlashDuration += *grenadeEvent.EnemyFlashDuration
			}

			// Flash duration on friendlies (total time this player's flashes affected teammates)
			if grenadeEvent.FriendlyFlashDuration != nil {
				friendlyFlashDuration += *grenadeEvent.FriendlyFlashDuration
			}

			// Count of players affected
			friendlyPlayersAffected += grenadeEvent.FriendlyPlayersAffected
			enemyPlayersAffected += grenadeEvent.EnemyPlayersAffected

			// Flash effectiveness
			if grenadeEvent.FlashLeadsToKill {
				flashesLeadingToKill++
			}
			if grenadeEvent.FlashLeadsToDeath {
				flashesLeadingToDeath++
			}
		}

	}

	// New grenade effectiveness calculation
	// Formula: (10 * grenades_thrown) + utility_management_score + (sum of grenade_effectiveness / total_grenades_with_effectiveness)

	// Calculate grenade used bonus (10 points per grenade thrown)
	grenadeUsedBonus := float64(flashesThrown+fireGrenadesThrown+hesThrown+smokesThrown+decoysThrown) * 10.0

	// Calculate utility management score (0-20 points based on utility lost)
	utilityManagementScore := 20.0
	if event.GrenadeValueLostOnDeath > 0 {
		// If they lose 1300 utility, they get 0 points
		// If they lose 0 utility, they get 20 points
		utilityLossPercentage := float64(event.GrenadeValueLostOnDeath) / 1300.0
		if utilityLossPercentage > 1.0 {
			utilityLossPercentage = 1.0 // Cap at 100%
		}
		utilityManagementScore = 20.0 * (1.0 - utilityLossPercentage)
	}

	// Calculate individual grenade effectiveness average
	grenadeEffectivenessAverage := 0.0
	if totalNumberOfMeasuredGrenades > 0 {
		grenadeEffectivenessAverage = float64(totalEffectivenessRating) / float64(totalNumberOfMeasuredGrenades)
	}

	// Calculate final grenade effectiveness
	finalGrenadeEffectiveness := int(math.Round(grenadeUsedBonus + utilityManagementScore + grenadeEffectivenessAverage))

	// Cap the final score at 100
	if finalGrenadeEffectiveness > 100 {
		finalGrenadeEffectiveness = 100
	}

	// Debug logging for final grenade effectiveness calculation
	// Set the aggregated values
	event.DamageDealt = damageDealt
	event.FlashesThrown = flashesThrown
	event.FireGrenadesThrown = fireGrenadesThrown
	event.SmokesThrown = smokesThrown
	event.HesThrown = hesThrown
	event.DecoysThrown = decoysThrown
	event.FriendlyFlashDuration = friendlyFlashDuration
	event.EnemyFlashDuration = enemyFlashDuration
	event.FriendlyPlayersAffected = friendlyPlayersAffected
	event.EnemyPlayersAffected = enemyPlayersAffected
	event.FlashesLeadingToKill = flashesLeadingToKill
	event.FlashesLeadingToDeath = flashesLeadingToDeath
	event.GrenadeEffectiveness = finalGrenadeEffectiveness
	event.SmokeBlockingDuration = smokeBlockingDuration
}

// aggregateTradeMetrics aggregates trade-related statistics from gunfight events
func (rh *RoundHandler) aggregateTradeMetrics(event *types.PlayerRoundEvent, playerSteamID string, roundNumber int) {
	successfulTrades := 0
	totalPossibleTrades := 0
	successfulTradedDeaths := 0
	totalPossibleTradedDeaths := 0

	// Create a list of all deaths in this round with timestamps and positions
	type DeathInfo struct {
		VictimSteamID  string
		KillerSteamID  string
		DeathTime      int64
		DeathPosition  types.Position
		DeathRoundTime int
	}

	var roundDeaths []DeathInfo

	// Collect all deaths in this round
	for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
		if gunfightEvent.RoundNumber != roundNumber || gunfightEvent.VictorSteamID == nil {
			continue
		}

		// Someone died in this gunfight
		if gunfightEvent.Player1SteamID != *gunfightEvent.VictorSteamID {
			// Player 1 died
			roundDeaths = append(roundDeaths, DeathInfo{
				VictimSteamID:  gunfightEvent.Player1SteamID,
				KillerSteamID:  *gunfightEvent.VictorSteamID,
				DeathTime:      gunfightEvent.TickTimestamp,
				DeathPosition:  gunfightEvent.Player1Position,
				DeathRoundTime: gunfightEvent.RoundTime,
			})
		} else if gunfightEvent.Player2SteamID != *gunfightEvent.VictorSteamID {
			// Player 2 died
			roundDeaths = append(roundDeaths, DeathInfo{
				VictimSteamID:  gunfightEvent.Player2SteamID,
				KillerSteamID:  *gunfightEvent.VictorSteamID,
				DeathTime:      gunfightEvent.TickTimestamp,
				DeathPosition:  gunfightEvent.Player2Position,
				DeathRoundTime: gunfightEvent.RoundTime,
			})
		}
	}

	// Check for possible trades for this player
	for _, death := range roundDeaths {
		// Skip if this player died (we'll handle that separately)
		if death.VictimSteamID == playerSteamID {
			continue
		}

		// Check if the dead player was a teammate
		deadPlayerTeam := rh.processor.getAssignedTeam(death.VictimSteamID)
		thisPlayerTeam := rh.processor.getAssignedTeam(playerSteamID)

		if deadPlayerTeam != thisPlayerTeam {
			continue // Not a teammate
		}

		// Get this player's position at the time of the teammate's death
		thisPlayerPosition := rh.getPlayerPositionAtTime(playerSteamID, death.DeathTime)
		if thisPlayerPosition == nil {
			continue // Couldn't determine player position
		}

		// Check if this player was within trade distance
		distance := types.CalculateDistance(death.DeathPosition, *thisPlayerPosition)
		if distance <= types.TradeDistanceThreshold {
			// This was a possible trade opportunity
			totalPossibleTrades++

			// Check if this player actually got the trade kill
			tradeTimeWindow := death.DeathTime + int64(types.TradeTimeWindowSeconds*64) // Convert seconds to ticks (approximate)

			for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
				if gunfightEvent.RoundNumber != roundNumber || gunfightEvent.VictorSteamID == nil {
					continue
				}

				// Check if this player got a kill on the original killer within the time window
				if *gunfightEvent.VictorSteamID == playerSteamID &&
					gunfightEvent.TickTimestamp >= death.DeathTime &&
					gunfightEvent.TickTimestamp <= tradeTimeWindow {

					// Check if the victim was the original killer
					if (gunfightEvent.Player1SteamID == death.KillerSteamID && gunfightEvent.Player1SteamID != *gunfightEvent.VictorSteamID) ||
						(gunfightEvent.Player2SteamID == death.KillerSteamID && gunfightEvent.Player2SteamID != *gunfightEvent.VictorSteamID) {
						successfulTrades++
						break // Found the trade, no need to check more
					}
				}
			}
		}
	}

	// Check if this player's deaths were traded
	for _, death := range roundDeaths {
		if death.VictimSteamID != playerSteamID {
			continue // Not this player's death
		}

		// Check if any teammates were in position to trade
		tradeTimeWindow := death.DeathTime + int64(types.TradeTimeWindowSeconds*64) // Convert seconds to ticks

		// Find teammates who could have traded
		for _, otherPlayerID := range rh.getPlayersInRound(roundNumber) {
			if otherPlayerID == playerSteamID {
				continue // Skip self
			}

			// Check if they're teammates
			otherPlayerTeam := rh.processor.getAssignedTeam(otherPlayerID)
			thisPlayerTeam := rh.processor.getAssignedTeam(playerSteamID)

			if otherPlayerTeam != thisPlayerTeam {
				continue // Not a teammate
			}

			// Get teammate's position at time of this player's death
			teammatePosition := rh.getPlayerPositionAtTime(otherPlayerID, death.DeathTime)
			if teammatePosition == nil {
				continue
			}

			// Check if teammate was within trade distance
			distance := types.CalculateDistance(death.DeathPosition, *teammatePosition)
			if distance <= types.TradeDistanceThreshold {
				// This was a possible traded death opportunity
				totalPossibleTradedDeaths++

				// Check if the teammate actually got the trade
				for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
					if gunfightEvent.RoundNumber != roundNumber || gunfightEvent.VictorSteamID == nil {
						continue
					}

					// Check if the teammate got a kill on the original killer within the time window
					if *gunfightEvent.VictorSteamID == otherPlayerID &&
						gunfightEvent.TickTimestamp >= death.DeathTime &&
						gunfightEvent.TickTimestamp <= tradeTimeWindow {

						// Check if the victim was the original killer
						if (gunfightEvent.Player1SteamID == death.KillerSteamID && gunfightEvent.Player1SteamID != *gunfightEvent.VictorSteamID) ||
							(gunfightEvent.Player2SteamID == death.KillerSteamID && gunfightEvent.Player2SteamID != *gunfightEvent.VictorSteamID) {
							successfulTradedDeaths++
							break // Found the trade, no need to check more teammates
						}
					}
				}
				break // Only count one possible trade per death
			}
		}
	}

	// Set the aggregated values
	event.SuccessfulTrades = successfulTrades
	event.TotalPossibleTrades = totalPossibleTrades
	event.SuccessfulTradedDeaths = successfulTradedDeaths
	event.TotalPossibleTradedDeaths = totalPossibleTradedDeaths
}

// getPlayerPositionAtTime attempts to get a player's position at a specific time
// This is a simplified implementation - in a real scenario, you might need more sophisticated position tracking
func (rh *RoundHandler) getPlayerPositionAtTime(playerSteamID string, targetTime int64) *types.Position {
	// Look for the closest gunfight event to get player position
	var closestEvent *types.GunfightEvent
	var closestTimeDiff int64 = int64(^uint64(0) >> 1) // Max int64

	for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
		if gunfightEvent.Player1SteamID == playerSteamID || gunfightEvent.Player2SteamID == playerSteamID {
			timeDiff := targetTime - gunfightEvent.TickTimestamp
			if timeDiff < 0 {
				timeDiff = -timeDiff // Make it positive
			}

			if timeDiff < closestTimeDiff {
				closestTimeDiff = timeDiff
				closestEvent = &gunfightEvent
			}
		}
	}

	if closestEvent != nil {
		if closestEvent.Player1SteamID == playerSteamID {
			return &closestEvent.Player1Position
		} else if closestEvent.Player2SteamID == playerSteamID {
			return &closestEvent.Player2Position
		}
	}

	return nil // Couldn't determine position
}

// aggregateClutchMetrics detects clutch scenarios and outcomes
func (rh *RoundHandler) aggregateClutchMetrics(event *types.PlayerRoundEvent, playerSteamID string, roundNumber int) {
	// Initialize clutch counters
	clutchAttempts := map[int]int{1: 0, 2: 0, 3: 0, 4: 0, 5: 0}
	clutchWins := map[int]int{1: 0, 2: 0, 3: 0, 4: 0, 5: 0}

	// Build timeline of deaths in chronological order
	type DeathEvent struct {
		VictimSteamID string
		KillerSteamID string
		TickTimestamp int64
		RoundTime     int
	}

	var deaths []DeathEvent

	// Collect all deaths in chronological order
	for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
		if gunfightEvent.RoundNumber != roundNumber || gunfightEvent.VictorSteamID == nil {
			continue
		}

		// Determine who died
		var victimSteamID string
		if gunfightEvent.Player1SteamID != *gunfightEvent.VictorSteamID {
			victimSteamID = gunfightEvent.Player1SteamID
		} else if gunfightEvent.Player2SteamID != *gunfightEvent.VictorSteamID {
			victimSteamID = gunfightEvent.Player2SteamID
		} else {
			continue // No one died
		}

		deaths = append(deaths, DeathEvent{
			VictimSteamID: victimSteamID,
			KillerSteamID: *gunfightEvent.VictorSteamID,
			TickTimestamp: gunfightEvent.TickTimestamp,
			RoundTime:     gunfightEvent.RoundTime,
		})
	}

	// Sort deaths by timestamp
	for i := 0; i < len(deaths)-1; i++ {
		for j := i + 1; j < len(deaths); j++ {
			if deaths[i].TickTimestamp > deaths[j].TickTimestamp {
				deaths[i], deaths[j] = deaths[j], deaths[i]
			}
		}
	}

	// Get all players in round and their teams
	allPlayers := rh.getPlayersInRound(roundNumber)
	playerTeams := make(map[string]string)
	for _, player := range allPlayers {
		playerTeams[player] = rh.processor.getAssignedTeam(player)
	}

	thisPlayerTeam := rh.processor.getAssignedTeam(playerSteamID)

	// Track alive players throughout the round
	alivePlayers := make(map[string]bool)
	for _, player := range allPlayers {
		alivePlayers[player] = true
	}

	// Track clutch scenarios for this player
	var activeClutchScenario *int // nil = no clutch, otherwise pointer to enemy count

	// Process each death and check for clutch scenarios
	for _, death := range deaths {
		// Remove the dead player from alive players
		alivePlayers[death.VictimSteamID] = false

		// Count alive players by team
		aliveTeammates := 0
		aliveEnemies := 0

		for player, isAlive := range alivePlayers {
			if !isAlive {
				continue
			}

			if playerTeams[player] == thisPlayerTeam {
				if player == playerSteamID {
					// This player is alive
					continue
				}
				aliveTeammates++
			} else {
				aliveEnemies++
			}
		}

		// Check if this player is still alive
		playerStillAlive := alivePlayers[playerSteamID]
		if !playerStillAlive {
			// Player died, end any active clutch
			if activeClutchScenario != nil {
				// Player failed the clutch
				activeClutchScenario = nil
			}
			continue
		}

		// Check if this creates a clutch scenario (player alone vs enemies)
		if aliveTeammates == 0 && aliveEnemies > 0 && aliveEnemies <= 5 {
			// This player is now in a clutch scenario
			if activeClutchScenario == nil {
				// New clutch scenario started
				clutchAttempts[aliveEnemies]++
				activeClutchScenario = &aliveEnemies

				// Clutch scenario detected
			} else if *activeClutchScenario != aliveEnemies {
				// Clutch scenario changed (enemy died)
				activeClutchScenario = &aliveEnemies
			}
		} else if activeClutchScenario != nil {
			// Clutch scenario ended
			if aliveEnemies == 0 {
				// Player won the clutch!
				clutchWins[*activeClutchScenario]++

				// Clutch won
			}
			activeClutchScenario = nil
		}
	}

	// Check final round state in case the round ended with the clutch player still alive
	if activeClutchScenario != nil {
		// Count final alive enemies
		finalAliveEnemies := 0
		for player, isAlive := range alivePlayers {
			if isAlive && playerTeams[player] != thisPlayerTeam {
				finalAliveEnemies++
			}
		}

		if finalAliveEnemies == 0 {
			// Player won the final clutch
			clutchWins[*activeClutchScenario]++

			// Final clutch won
		}
	}

	// Set the clutch values
	event.ClutchAttempts1v1 = clutchAttempts[1]
	event.ClutchAttempts1v2 = clutchAttempts[2]
	event.ClutchAttempts1v3 = clutchAttempts[3]
	event.ClutchAttempts1v4 = clutchAttempts[4]
	event.ClutchAttempts1v5 = clutchAttempts[5]

	event.ClutchWins1v1 = clutchWins[1]
	event.ClutchWins1v2 = clutchWins[2]
	event.ClutchWins1v3 = clutchWins[3]
	event.ClutchWins1v4 = clutchWins[4]
	event.ClutchWins1v5 = clutchWins[5]
}

// aggregateTimeToContact calculates the time from round start to first damage given or received
func (rh *RoundHandler) aggregateTimeToContact(event *types.PlayerRoundEvent, playerSteamID string, roundNumber int) {
	timeToContact := 0.0

	// Find the earliest damage event involving this player
	var earliestContactTick int64 = -1

	// Check damage events (both as attacker and victim)
	for _, damageEvent := range rh.processor.matchState.DamageEvents {
		if damageEvent.RoundNumber != roundNumber {
			continue
		}

		// Check if this player was involved in the damage event
		if damageEvent.AttackerSteamID == playerSteamID || damageEvent.VictimSteamID == playerSteamID {
			// Make sure this involves an enemy (not self-damage or teammate damage)
			var otherPlayerSteamID string
			if damageEvent.AttackerSteamID == playerSteamID {
				otherPlayerSteamID = damageEvent.VictimSteamID
			} else {
				otherPlayerSteamID = damageEvent.AttackerSteamID
			}

			// Check if the other player is an enemy
			thisPlayerTeam := rh.processor.getAssignedTeam(playerSteamID)
			otherPlayerTeam := rh.processor.getAssignedTeam(otherPlayerSteamID)

			if thisPlayerTeam != otherPlayerTeam && otherPlayerSteamID != playerSteamID {
				// This is enemy contact
				if earliestContactTick == -1 || damageEvent.TickTimestamp < earliestContactTick {
					earliestContactTick = damageEvent.TickTimestamp
				}
			}
		}
	}

	// Also check gunfight events as a backup/verification
	for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
		if gunfightEvent.RoundNumber != roundNumber {
			continue
		}

		// Check if this player was involved
		if gunfightEvent.Player1SteamID == playerSteamID || gunfightEvent.Player2SteamID == playerSteamID {
			// Verify this is enemy contact
			var otherPlayerSteamID string
			if gunfightEvent.Player1SteamID == playerSteamID {
				otherPlayerSteamID = gunfightEvent.Player2SteamID
			} else {
				otherPlayerSteamID = gunfightEvent.Player1SteamID
			}

			thisPlayerTeam := rh.processor.getAssignedTeam(playerSteamID)
			otherPlayerTeam := rh.processor.getAssignedTeam(otherPlayerSteamID)

			if thisPlayerTeam != otherPlayerTeam {
				// This is enemy contact - use if earlier than damage events or if no damage events found
				if earliestContactTick == -1 || gunfightEvent.TickTimestamp < earliestContactTick {
					earliestContactTick = gunfightEvent.TickTimestamp
				}
			}
		}
	}

	// Calculate time to contact using round time instead of ticks
	if earliestContactTick != -1 {
		// Find the earliest round time for this round to use as baseline
		var earliestRoundTime int = -1

		// Find the earliest event in this round to establish round start time
		for _, damageEvent := range rh.processor.matchState.DamageEvents {
			if damageEvent.RoundNumber == roundNumber {
				if earliestRoundTime == -1 || damageEvent.RoundTime < earliestRoundTime {
					earliestRoundTime = damageEvent.RoundTime
				}
			}
		}

		for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
			if gunfightEvent.RoundNumber == roundNumber {
				if earliestRoundTime == -1 || gunfightEvent.RoundTime < earliestRoundTime {
					earliestRoundTime = gunfightEvent.RoundTime
				}
			}
		}

		// Find the round time of the earliest contact
		var contactRoundTime int = -1
		for _, damageEvent := range rh.processor.matchState.DamageEvents {
			if damageEvent.TickTimestamp == earliestContactTick {
				contactRoundTime = damageEvent.RoundTime
				break
			}
		}

		if contactRoundTime == -1 {
			for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
				if gunfightEvent.TickTimestamp == earliestContactTick {
					contactRoundTime = gunfightEvent.RoundTime
					break
				}
			}
		}

		// Calculate time to contact
		if contactRoundTime != -1 {
			// The round time already represents seconds from round start
			timeToContact = float64(contactRoundTime)

			// Ensure reasonable bounds (round times should be between 0 and 120 seconds)
			if timeToContact < 0 {
				timeToContact = 0
			}
			if timeToContact > 120 {
				timeToContact = 120
			}
		}

		// Time to contact calculated
	}

	event.TimeToContact = timeToContact
}

// aggregateEconomyMetrics calculates economy-related statistics
func (rh *RoundHandler) aggregateEconomyMetrics(event *types.PlayerRoundEvent, playerSteamID string, roundNumber int) {
	// Get this player's equipment value from gunfight events (if available)
	playerEquipValue := rh.getPlayerEquipmentValueInRound(playerSteamID, roundNumber)

	// Determine this player's buy type using constants
	isEco := playerEquipValue <= types.EcoThreshold
	isForceBuy := playerEquipValue > types.EcoThreshold && playerEquipValue <= types.ForceBuyThreshold
	isFullBuy := playerEquipValue > types.ForceBuyThreshold

	// Exception: Pistol rounds (rounds 1 and 13) should be classified as full buys
	// Players start with $800 and this is considered their "full buy" for pistol rounds
	if roundNumber == 1 || roundNumber == 13 {
		isEco = false
		isForceBuy = false
		isFullBuy = true
	}

	// Count kills vs different buy types
	killsVsEco := 0
	killsVsForceBuy := 0
	killsVsFullBuy := 0
	grenadeValueLostOnDeath := 0

	// Analyze kills this player made and categorize victims by their equipment value
	for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
		if gunfightEvent.RoundNumber != roundNumber || gunfightEvent.VictorSteamID == nil {
			continue
		}

		// Check if this player got the kill
		if *gunfightEvent.VictorSteamID == playerSteamID {
			var victimEquipValue int
			if gunfightEvent.Player1SteamID == playerSteamID {
				// Player killed Player2
				victimEquipValue = gunfightEvent.Player2EquipValue
			} else if gunfightEvent.Player2SteamID == playerSteamID {
				// Player killed Player1
				victimEquipValue = gunfightEvent.Player1EquipValue
			} else {
				continue
			}

			// Categorize the victim's buy type
			if victimEquipValue <= types.EcoThreshold {
				killsVsEco++
			} else if victimEquipValue <= types.ForceBuyThreshold {
				killsVsForceBuy++
			} else {
				killsVsFullBuy++
			}
		} else {
			if gunfightEvent.Player1SteamID == playerSteamID {
				// Player was killed by Player2, so Player2 gets the grenades
				grenadeValueLostOnDeath = gunfightEvent.Player2GrenadeValue
				// Player died - grenade value lost calculated
			} else if gunfightEvent.Player2SteamID == playerSteamID {
				// Player was killed by Player1, so Player1 gets the grenades
				grenadeValueLostOnDeath = gunfightEvent.Player1GrenadeValue
				// Player died - grenade value lost calculated
			} else {
				continue
			}
		}
	}

	// Set economy values
	event.IsEco = isEco
	event.IsForceBuy = isForceBuy
	event.IsFullBuy = isFullBuy
	event.KillsVsEco = killsVsEco
	event.KillsVsForceBuy = killsVsForceBuy
	event.KillsVsFullBuy = killsVsFullBuy
	event.GrenadeValueLostOnDeath = grenadeValueLostOnDeath

	// Final grenade value lost on death set
}

// getPlayerEquipmentValueInRound gets the player's equipment value for this round
func (rh *RoundHandler) getPlayerEquipmentValueInRound(playerSteamID string, roundNumber int) int {
	// Look for the earliest gunfight event involving this player to get their equipment value
	for _, gunfightEvent := range rh.processor.matchState.GunfightEvents {
		if gunfightEvent.RoundNumber != roundNumber {
			continue
		}

		if gunfightEvent.Player1SteamID == playerSteamID {
			return gunfightEvent.Player1EquipValue
		} else if gunfightEvent.Player2SteamID == playerSteamID {
			return gunfightEvent.Player2EquipValue
		}
	}

	return 0 // Default if no gunfight events found
}

// calculateRoundImpact calculates impact values for gunfight events in a specific round
func (rh *RoundHandler) calculateRoundImpact(roundNumber int) {
	// Create impact calculator
	calculator := NewImpactRatingCalculator()

	// Calculate impact for each gunfight event in this round
	for i := range rh.processor.matchState.GunfightEvents {
		gunfight := &rh.processor.matchState.GunfightEvents[i]
		if gunfight.RoundNumber != roundNumber {
			continue
		}

		// Calculate team strengths
		team1Strength := calculator.CalculateTeamStrength(
			rh.parseManCountFromScenario(gunfight.RoundScenario, true), // team 1 count
			gunfight.Player1EquipValue,
		)
		team2Strength := calculator.CalculateTeamStrength(
			rh.parseManCountFromScenario(gunfight.RoundScenario, false), // team 2 count
			gunfight.Player2EquipValue,
		)

		// Calculate impact for this gunfight
		calculator.CalculateGunfightImpact(gunfight, team1Strength, team2Strength)
	}

	// Update PlayerRoundEvents with calculated impact values
	for i := range rh.processor.matchState.PlayerRoundEvents {
		playerRound := &rh.processor.matchState.PlayerRoundEvents[i]
		if playerRound.RoundNumber != roundNumber {
			continue
		}

		// Recalculate impact for this player in this round
		rh.aggregateImpactMetrics(playerRound, playerRound.PlayerSteamID, roundNumber)
	}

	// Update RoundEvents with calculated impact values
	for i := range rh.processor.matchState.RoundEvents {
		roundEvent := &rh.processor.matchState.RoundEvents[i]
		if roundEvent.RoundNumber != roundNumber || roundEvent.EventType != "end" {
			continue
		}

		// Calculate round-level impact
		rh.calculateRoundEventImpact(roundEvent, roundNumber)
	}
}

// parseManCountFromScenario extracts man count from round scenario string
func (rh *RoundHandler) parseManCountFromScenario(scenario string, isTeam1 bool) int {
	// Parse scenario like "5v4" to get team counts
	parts := strings.Split(scenario, "v")
	if len(parts) != 2 {
		return 5 // Default to 5v5 if parsing fails
	}

	if isTeam1 {
		if count, err := strconv.Atoi(parts[0]); err == nil {
			return count
		}
	} else {
		if count, err := strconv.Atoi(parts[1]); err == nil {
			return count
		}
	}

	return 5 // Default fallback
}

// aggregateImpactMetrics calculates impact values by aggregating from gunfight events
func (rh *RoundHandler) aggregateImpactMetrics(event *types.PlayerRoundEvent, playerSteamID string, roundNumber int) {
	// Initialize impact values
	event.TotalImpact = 0
	event.AverageImpact = 0
	event.RoundSwingPercent = 0

	gunfightCount := 0

	// Aggregate impact from gunfight events for this round
	for _, gunfight := range rh.processor.matchState.GunfightEvents {
		if gunfight.RoundNumber != roundNumber {
			continue
		}

		playerInvolved := false

		// Player 1 (attacker) impact
		if gunfight.Player1SteamID == playerSteamID {
			event.TotalImpact += gunfight.Player1Impact
			playerInvolved = true
		}

		// Player 2 (victim) impact
		if gunfight.Player2SteamID == playerSteamID {
			event.TotalImpact += gunfight.Player2Impact
			playerInvolved = true
		}

		// Assister impact
		if gunfight.DamageAssistSteamID != nil && *gunfight.DamageAssistSteamID == playerSteamID {
			event.TotalImpact += gunfight.AssisterImpact
			playerInvolved = true
		}

		// Flash assister impact
		if gunfight.FlashAssisterSteamID != nil && *gunfight.FlashAssisterSteamID == playerSteamID {
			event.TotalImpact += gunfight.FlashAssisterImpact
			playerInvolved = true
		}

		// Count gunfight events the player was involved in (don't double count)
		if playerInvolved {
			gunfightCount++
		}
	}

	// Calculate average impact per gunfight event
	if gunfightCount > 0 {
		event.AverageImpact = event.TotalImpact / float64(gunfightCount)
	}

	// Calculate round swing percentage with outcome bonus
	event.RoundSwingPercent = rh.calculateRoundSwingPercent(event.TotalImpact, roundNumber, playerSteamID)

	// Calculate impact percentage using practical maximum
	event.ImpactPercentage = (event.TotalImpact / types.MaxPracticalImpact) * 100.0
}

// calculateRoundEventImpact calculates impact values for a round event
func (rh *RoundHandler) calculateRoundEventImpact(roundEvent *types.RoundEvent, roundNumber int) {
	// Calculate total impact for this round
	totalImpact := 0.0
	totalGunfights := 0

	for _, gunfight := range rh.processor.matchState.GunfightEvents {
		if gunfight.RoundNumber != roundNumber {
			continue
		}

		totalImpact += gunfight.Player1Impact + gunfight.Player2Impact + gunfight.AssisterImpact + gunfight.FlashAssisterImpact
		totalGunfights++
	}

	// Set round event impact values
	roundEvent.TotalImpact = totalImpact
	roundEvent.TotalGunfights = totalGunfights
	roundEvent.AverageImpact = totalImpact / float64(totalGunfights)

	// Calculate round swing percentage with outcome bonus (use 0 for team-level calculation)
	roundEvent.RoundSwingPercent = rh.calculateRoundSwingPercent(totalImpact, roundNumber, "")

	// Calculate impact percentage using practical maximum
	roundEvent.ImpactPercentage = (totalImpact / types.MaxPracticalImpact) * 100.0
}

// calculateRoundSwingPercent calculates round swing percentage with outcome bonus
func (rh *RoundHandler) calculateRoundSwingPercent(playerImpact float64, roundNumber int, playerSteamID string) float64 {
	// Find the round outcome
	var roundWinner *string
	for _, roundEvent := range rh.processor.matchState.RoundEvents {
		if roundEvent.RoundNumber == roundNumber && roundEvent.EventType == "end" {
			roundWinner = roundEvent.Winner
			break
		}
	}

	// Determine outcome bonus
	outcomeBonus := 0.0
	if roundWinner != nil && playerSteamID != "" {
		// Get player's team to determine if they won
		playerTeam := rh.processor.getAssignedTeam(playerSteamID)
		if playerTeam == *roundWinner {
			outcomeBonus = types.RoundWinOutcomeBonus
		} else {
			outcomeBonus = types.RoundLossOutcomePenalty
		}
	}

	// Calculate round swing percentage: (Player Impact / Team Max) × (1 + Outcome Bonus) × 100
	return (playerImpact / types.TeamMaxImpactPerRound) * (1 + outcomeBonus) * 100.0
}
