package parser

import (
	"math"
	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
)

// RoundHandler handles round-level aggregation of player statistics
type PlayerMatchHandler struct {
	processor *EventProcessor
	logger    *logrus.Logger
}

// NewRoundHandler creates a new round handler
func NewPlayerMatchHandler(processor *EventProcessor, logger *logrus.Logger) *PlayerMatchHandler {
	return &PlayerMatchHandler{
		processor: processor,
		logger:    logger,
	}
}

func (mh *PlayerMatchHandler) aggregatePlayerMatchEvent() {
	for steamID := range mh.processor.matchState.Players {
		playerMatchEvent := mh.createPlayerMatchEvent(steamID)
		mh.processor.matchState.PlayerMatchEvents = append(mh.processor.matchState.PlayerMatchEvents, playerMatchEvent)
	}
}

func (mh *PlayerMatchHandler) createPlayerMatchEvent(playerSteamID string) types.PlayerMatchEvent {
	playerMatchEvent := types.PlayerMatchEvent{
		PlayerSteamID:               playerSteamID,
		Kills:                       0,
		Assists:                     0,
		Deaths:                      0,
		Damage:                      0,
		ADR:                         0,
		Headshots:                   0,
		FirstKills:                  0,
		FirstDeaths:                 0,
		AverageRoundTimeOfDeath:     0,
		KillsWithAWP:                0,
		DamageDealt:                 0,
		FlashesThrown:               0,
		FireGrenadesThrown:          0,
		SmokesThrown:                0,
		HesThrown:                   0,
		DecoysThrown:                0,
		FriendlyFlashDuration:       0,
		EnemyFlashDuration:          0,
		FriendlyPlayersAffected:     0,
		EnemyPlayersAffected:        0,
		FlashesLeadingToKills:       0,
		FlashesLeadingToDeaths:      0,
		AverageGrenadeEffectiveness: 0,
		TotalSuccessfulTrades:       0,
		TotalPossibleTrades:         0,
		TotalTradedDeaths:           0,
		TotalPossibleTradedDeaths:   0,
		ClutchWins1v1:               0,
		ClutchWins1v2:               0,
		ClutchWins1v3:               0,
		ClutchWins1v4:               0,
		ClutchWins1v5:               0,
		ClutchAttempts1v1:           0,
		ClutchAttempts1v2:           0,
		ClutchAttempts1v3:           0,
		ClutchAttempts1v4:           0,
		ClutchAttempts1v5:           0,
		AverageTimeToContact:        0,
		KillsVsEco:                  0,
		KillsVsForceBuy:             0,
		KillsVsFullBuy:              0,
		AverageGrenadeValueLost:     0,
		MatchmakingRank:             nil,
	}

	numberOfRoundsParticipated := 0
	totalRoundTimeOfDeath := 0
	totalGrenadeEffectiveness := 0
	nonZeroGrenadeEffectivenessRounds := 0
	totalTimeToContact := 0.0
	totalGrenadeValueLostOnDeath := 0

	playerMatchEvent.Deaths = 0
	playerMatchEvent.FirstKills = 0
	playerMatchEvent.FirstDeaths = 0

	for _, roundEvent := range mh.processor.matchState.PlayerRoundEvents {
		if roundEvent.PlayerSteamID != playerSteamID {
			continue
		}

		numberOfRoundsParticipated++

		//Gun fight metrics
		playerMatchEvent.PlayerSteamID = roundEvent.PlayerSteamID
		playerMatchEvent.Kills += roundEvent.Kills
		playerMatchEvent.Assists += roundEvent.Assists

		if roundEvent.Died {
			playerMatchEvent.Deaths++
		}

		playerMatchEvent.Damage += roundEvent.Damage
		playerMatchEvent.Headshots += roundEvent.Headshots

		if roundEvent.FirstKill {
			playerMatchEvent.FirstKills++
		}

		if roundEvent.FirstDeath {
			playerMatchEvent.FirstDeaths++
		}

		if roundEvent.RoundTimeOfDeath != nil {
			totalRoundTimeOfDeath += *roundEvent.RoundTimeOfDeath
		}
		playerMatchEvent.KillsWithAWP += roundEvent.KillsWithAWP

		//Grenade metrics
		playerMatchEvent.DamageDealt += roundEvent.DamageDealt
		playerMatchEvent.FlashesThrown += roundEvent.FlashesThrown
		playerMatchEvent.FireGrenadesThrown += roundEvent.FireGrenadesThrown
		playerMatchEvent.SmokesThrown += roundEvent.SmokesThrown
		playerMatchEvent.HesThrown += roundEvent.HesThrown
		playerMatchEvent.DecoysThrown += roundEvent.DecoysThrown
		playerMatchEvent.FriendlyFlashDuration += roundEvent.FriendlyFlashDuration
		playerMatchEvent.EnemyFlashDuration += roundEvent.EnemyFlashDuration
		playerMatchEvent.FriendlyPlayersAffected += roundEvent.FriendlyPlayersAffected
		playerMatchEvent.EnemyPlayersAffected += roundEvent.EnemyPlayersAffected
		playerMatchEvent.FlashesLeadingToKills += roundEvent.FlashesLeadingToKill
		playerMatchEvent.FlashesLeadingToDeaths += roundEvent.FlashesLeadingToDeath
		totalGrenadeEffectiveness += roundEvent.GrenadeEffectiveness

		if roundEvent.GrenadeEffectiveness != 0 {
			nonZeroGrenadeEffectivenessRounds++
		}

		//Round scenario metrics
		playerMatchEvent.TotalSuccessfulTrades += roundEvent.SuccessfulTrades
		playerMatchEvent.TotalPossibleTrades += roundEvent.TotalPossibleTrades
		playerMatchEvent.TotalTradedDeaths += roundEvent.SuccessfulTradedDeaths
		playerMatchEvent.TotalPossibleTradedDeaths += roundEvent.TotalPossibleTradedDeaths
		playerMatchEvent.ClutchWins1v1 += roundEvent.ClutchWins1v1
		playerMatchEvent.ClutchWins1v2 += roundEvent.ClutchWins1v2
		playerMatchEvent.ClutchWins1v3 += roundEvent.ClutchWins1v3
		playerMatchEvent.ClutchWins1v4 += roundEvent.ClutchWins1v4
		playerMatchEvent.ClutchWins1v5 += roundEvent.ClutchWins1v5
		playerMatchEvent.ClutchAttempts1v1 += roundEvent.ClutchAttempts1v1
		playerMatchEvent.ClutchAttempts1v2 += roundEvent.ClutchAttempts1v2
		playerMatchEvent.ClutchAttempts1v3 += roundEvent.ClutchAttempts1v3
		playerMatchEvent.ClutchAttempts1v4 += roundEvent.ClutchAttempts1v4
		playerMatchEvent.ClutchAttempts1v5 += roundEvent.ClutchAttempts1v5
		totalTimeToContact += roundEvent.TimeToContact

		//Economy metrics
		playerMatchEvent.KillsVsEco += roundEvent.KillsVsEco
		playerMatchEvent.KillsVsForceBuy += roundEvent.KillsVsForceBuy
		playerMatchEvent.KillsVsFullBuy += roundEvent.KillsVsFullBuy
		totalGrenadeValueLostOnDeath += roundEvent.GrenadeValueLostOnDeath
	}

	//Average metrics
	playerMatchEvent.AverageRoundTimeOfDeath = float64(totalRoundTimeOfDeath) / float64(numberOfRoundsParticipated)
	playerMatchEvent.AverageGrenadeEffectiveness = int(math.Round((float64(totalGrenadeEffectiveness) / float64(nonZeroGrenadeEffectivenessRounds))))
	playerMatchEvent.AverageTimeToContact = float64(totalTimeToContact) / float64(numberOfRoundsParticipated)
	playerMatchEvent.AverageGrenadeValueLost = float64(totalGrenadeValueLostOnDeath) / float64(numberOfRoundsParticipated)
	playerMatchEvent.ADR = float64(playerMatchEvent.Damage) / float64(numberOfRoundsParticipated)

	// Set the matchmaking rank from the player data
	if player, exists := mh.processor.matchState.Players[playerSteamID]; exists && player.Rank != nil {
		playerMatchEvent.MatchmakingRank = player.Rank
	}

	return playerMatchEvent
}
