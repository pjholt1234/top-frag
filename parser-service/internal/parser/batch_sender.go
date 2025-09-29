package parser

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"time"

	"parser-service/internal/api"
	"parser-service/internal/config"
	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
)

type BatchSender struct {
	config          *config.Config
	logger          *logrus.Logger
	client          *http.Client
	baseURL         string
	progressManager *ProgressManager
}

func NewBatchSender(cfg *config.Config, logger *logrus.Logger, progressManager *ProgressManager) *BatchSender {
	return &BatchSender{
		config:          cfg,
		logger:          logger,
		progressManager: progressManager,
		client: &http.Client{
			Timeout: cfg.Batch.HTTPTimeout,
		},
	}
}

func (bs *BatchSender) extractBaseURL(completionURL string) (string, error) {
	if completionURL == "" {
		return "", types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, "empty completion URL", nil)
	}

	parsedURL, err := url.Parse(completionURL)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, "failed to parse completion URL", err)
		bs.progressManager.ReportParseError(parseError)
		return "", parseError
	}

	// Check if the URL has a valid scheme and host
	if parsedURL.Scheme == "" || parsedURL.Host == "" {
		return "", types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityError, "invalid URL: missing scheme or host", nil)
	}

	baseURL := fmt.Sprintf("%s://%s", parsedURL.Scheme, parsedURL.Host)
	return baseURL, nil
}

func (bs *BatchSender) SendGunfightEvents(ctx context.Context, jobID string, completionURL string, events []types.GunfightEvent) error {
	if len(events) == 0 {
		return nil
	}

	// Extract base URL from completion URL
	baseURL, err := bs.extractBaseURL(completionURL)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send gunfight events", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}
	bs.baseURL = baseURL

	batchSize := bs.config.Batch.GunfightEventsSize
	totalBatches := (len(events) + batchSize - 1) / batchSize

	// Sending gunfight events

	for i := 0; i < totalBatches; i++ {
		start := i * batchSize
		end := start + batchSize
		if end > len(events) {
			end = len(events)
		}

		batch := events[start:end]
		isLast := i == totalBatches-1

		flatEvents := make([]map[string]interface{}, len(batch))
		for j, event := range batch {
			flatEvents[j] = map[string]interface{}{
				"round_number":             event.RoundNumber,
				"round_time":               event.RoundTime,
				"tick_timestamp":           event.TickTimestamp,
				"player_1_steam_id":        event.Player1SteamID,
				"player_1_side":            event.Player1Side,
				"player_2_steam_id":        event.Player2SteamID,
				"player_2_side":            event.Player2Side,
				"player_1_hp_start":        event.Player1HPStart,
				"player_2_hp_start":        event.Player2HPStart,
				"player_1_armor":           event.Player1Armor,
				"player_2_armor":           event.Player2Armor,
				"player_1_flashed":         event.Player1Flashed,
				"player_2_flashed":         event.Player2Flashed,
				"player_1_weapon":          event.Player1Weapon,
				"player_2_weapon":          event.Player2Weapon,
				"player_1_equipment_value": event.Player1EquipValue,
				"player_2_equipment_value": event.Player2EquipValue,
				"player_1_grenade_value":   event.Player1GrenadeValue,
				"player_2_grenade_value":   event.Player2GrenadeValue,
				"player_1_x":               event.Player1Position.X,
				"player_1_y":               event.Player1Position.Y,
				"player_1_z":               event.Player1Position.Z,
				"player_2_x":               event.Player2Position.X,
				"player_2_y":               event.Player2Position.Y,
				"player_2_z":               event.Player2Position.Z,
				"distance":                 event.Distance,
				"headshot":                 event.Headshot,
				"wallbang":                 event.Wallbang,
				"penetrated_objects":       event.PenetratedObjects,
				"victor_steam_id":          event.VictorSteamID,
				"damage_dealt":             event.DamageDealt,
				"is_first_kill":            event.IsFirstKill,
				"flash_assister_steam_id":  event.FlashAssisterSteamID,
				"damage_assist_steam_id":   event.DamageAssistSteamID,
				"round_scenario":           event.RoundScenario,

				// Impact Rating Fields
				"player_1_team_strength": event.Player1TeamStrength,
				"player_2_team_strength": event.Player2TeamStrength,
				"player_1_impact":        event.Player1Impact,
				"player_2_impact":        event.Player2Impact,
				"assister_impact":        event.AssisterImpact,
				"flash_assister_impact":  event.FlashAssisterImpact,
			}
		}

		payload := map[string]interface{}{
			"batch_index":   i + 1,
			"is_last":       isLast,
			"total_batches": totalBatches,
			"data":          flatEvents,
		}

		url := bs.baseURL + fmt.Sprintf(api.JobEventEndpoint, jobID, api.EventTypeGunfight)
		if err := bs.sendRequestWithRetry(ctx, url, payload); err != nil {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send gunfight events", err)
			parseError = parseError.WithContext("job_id", jobID)
			parseError = parseError.WithContext("batch_number", i+1)
			parseError = parseError.WithContext("url", url)
			bs.progressManager.ReportParseError(parseError)
			return parseError
		}

		// Log impact values for first batch
		if i == 0 && len(batch) > 0 {
			// Sending gunfight events with impact values
		}

		// Sent gunfight events batch
	}

	return nil
}

func (bs *BatchSender) SendGrenadeEvents(ctx context.Context, jobID string, completionURL string, events []types.GrenadeEvent) error {
	if len(events) == 0 {
		return nil
	}

	// Extract base URL from completion URL
	baseURL, err := bs.extractBaseURL(completionURL)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send grenade events", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}
	bs.baseURL = baseURL

	batchSize := bs.config.Batch.GrenadeEventsSize
	totalBatches := (len(events) + batchSize - 1) / batchSize

	// Sending grenade events

	for i := 0; i < totalBatches; i++ {
		start := i * batchSize
		end := start + batchSize
		if end > len(events) {
			end = len(events)
		}

		batch := events[start:end]
		isLast := i == totalBatches-1

		flatEvents := make([]map[string]interface{}, len(batch))
		for j, event := range batch {
			flatEvent := map[string]interface{}{
				"round_number":         event.RoundNumber,
				"round_time":           event.RoundTime,
				"tick_timestamp":       event.TickTimestamp,
				"player_steam_id":      event.PlayerSteamID,
				"player_side":          event.PlayerSide,
				"grenade_type":         event.GrenadeType,
				"player_x":             event.PlayerPosition.X,
				"player_y":             event.PlayerPosition.Y,
				"player_z":             event.PlayerPosition.Z,
				"player_aim_x":         event.PlayerAim.X,
				"player_aim_y":         event.PlayerAim.Y,
				"player_aim_z":         event.PlayerAim.Z,
				"damage_dealt":         event.DamageDealt,
				"throw_type":           event.ThrowType,
				"effectiveness_rating": event.EffectivenessRating,
			}

			if event.GrenadeFinalPosition != nil {
				flatEvent["grenade_final_x"] = event.GrenadeFinalPosition.X
				flatEvent["grenade_final_y"] = event.GrenadeFinalPosition.Y
				flatEvent["grenade_final_z"] = event.GrenadeFinalPosition.Z
			}
			if event.FlashDuration != nil {
				flatEvent["flash_duration"] = *event.FlashDuration
			}
			if event.FriendlyFlashDuration != nil {
				flatEvent["friendly_flash_duration"] = *event.FriendlyFlashDuration
			}
			if event.EnemyFlashDuration != nil {
				flatEvent["enemy_flash_duration"] = *event.EnemyFlashDuration
			}
			flatEvent["friendly_players_affected"] = event.FriendlyPlayersAffected
			flatEvent["enemy_players_affected"] = event.EnemyPlayersAffected
			flatEvent["flash_leads_to_kill"] = event.FlashLeadsToKill
			flatEvent["flash_leads_to_death"] = event.FlashLeadsToDeath
			flatEvent["smoke_blocking_duration"] = event.SmokeBlockingDuration

			// Log smoke grenade events with blocking duration
			if event.GrenadeType == "Smoke Grenade" {
				bs.logger.WithFields(logrus.Fields{
					"player_steam_id":         event.PlayerSteamID,
					"grenade_type":            event.GrenadeType,
					"smoke_blocking_duration": event.SmokeBlockingDuration,
					"effectiveness_rating":    event.EffectivenessRating,
					"round_number":            event.RoundNumber,
				}).Info("Sending smoke grenade event with blocking duration")
			}

			if len(event.AffectedPlayers) > 0 {
				flatEvent["affected_players"] = event.AffectedPlayers
			}

			flatEvents[j] = flatEvent
		}

		payload := map[string]interface{}{
			"batch_index":   i + 1,
			"is_last":       isLast,
			"total_batches": totalBatches,
			"data":          flatEvents,
		}

		url := bs.baseURL + fmt.Sprintf(api.JobEventEndpoint, jobID, api.EventTypeGrenade)
		if err := bs.sendRequestWithRetry(ctx, url, payload); err != nil {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send grenade events", err)
			parseError = parseError.WithContext("job_id", jobID)
			parseError = parseError.WithContext("batch_number", i+1)
			parseError = parseError.WithContext("url", url)
			bs.progressManager.ReportParseError(parseError)
			return parseError
		}

		// Sent grenade events batch
	}

	return nil
}

func (bs *BatchSender) SendDamageEvents(ctx context.Context, jobID string, completionURL string, events []types.DamageEvent) error {
	if len(events) == 0 {
		return nil
	}

	// Extract base URL from completion URL
	baseURL, err := bs.extractBaseURL(completionURL)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send damage events", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}
	bs.baseURL = baseURL

	batchSize := bs.config.Batch.DamageEventsSize
	totalBatches := (len(events) + batchSize - 1) / batchSize

	// Sending damage events

	for i := 0; i < totalBatches; i++ {
		start := i * batchSize
		end := start + batchSize
		if end > len(events) {
			end = len(events)
		}

		batch := events[start:end]
		isLast := i == totalBatches-1

		flatEvents := make([]map[string]interface{}, len(batch))
		for j, event := range batch {
			flatEvents[j] = map[string]interface{}{
				"round_number":      event.RoundNumber,
				"round_time":        event.RoundTime,
				"tick_timestamp":    event.TickTimestamp,
				"attacker_steam_id": event.AttackerSteamID,
				"victim_steam_id":   event.VictimSteamID,
				"damage":            event.Damage,
				"armor_damage":      event.ArmorDamage,
				"health_damage":     event.HealthDamage,
				"headshot":          event.Headshot,
				"weapon":            event.Weapon,
			}
		}

		payload := map[string]interface{}{
			"batch_index":   i + 1,
			"is_last":       isLast,
			"total_batches": totalBatches,
			"data":          flatEvents,
		}

		url := bs.baseURL + fmt.Sprintf(api.JobEventEndpoint, jobID, api.EventTypeDamage)
		if err := bs.sendRequestWithRetry(ctx, url, payload); err != nil {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send damage events", err)
			parseError = parseError.WithContext("job_id", jobID)
			parseError = parseError.WithContext("batch_number", i+1)
			parseError = parseError.WithContext("url", url)
			bs.progressManager.ReportParseError(parseError)
			return parseError
		}

		// Sent damage events batch
	}

	return nil
}

func (bs *BatchSender) SendRoundEvents(ctx context.Context, jobID string, completionURL string, events []types.RoundEvent) error {
	if len(events) == 0 {
		return nil
	}

	// Extract base URL from completion URL
	baseURL, err := bs.extractBaseURL(completionURL)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send round events", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}
	bs.baseURL = baseURL

	// Sending round events

	flatEvents := make([]map[string]interface{}, len(events))
	for i, event := range events {
		flatEvent := map[string]interface{}{
			"round_number":   event.RoundNumber,
			"tick_timestamp": event.TickTimestamp,
			"event_type":     event.EventType,

			// Impact Rating Fields
			"total_impact":        event.TotalImpact,
			"total_gunfights":     event.TotalGunfights,
			"average_impact":      event.AverageImpact,
			"round_swing_percent": event.RoundSwingPercent,
			"impact_percentage":   event.ImpactPercentage,
		}

		if event.Winner != nil {
			flatEvent["winner"] = *event.Winner
		}
		if event.Duration != nil {
			flatEvent["duration"] = *event.Duration
		}

		flatEvents[i] = flatEvent
	}

	payload := map[string]interface{}{
		"data": flatEvents,
	}

	url := bs.baseURL + fmt.Sprintf(api.JobEventEndpoint, jobID, api.EventTypeRound)
	if err := bs.sendRequestWithRetry(ctx, url, payload); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send round events", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("url", url)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}

	// Sent round events
	return nil
}

func (bs *BatchSender) SendPlayerRoundEvents(ctx context.Context, jobID string, completionURL string, events []types.PlayerRoundEvent) error {
	if len(events) == 0 {
		return nil
	}

	// Extract base URL from completion URL
	baseURL, err := bs.extractBaseURL(completionURL)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send player round events", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}
	bs.baseURL = baseURL

	batchSize := 12 // Reuse gunfight batch size for player round events
	totalBatches := (len(events) + batchSize - 1) / batchSize

	// Sending player round events

	for i := 0; i < totalBatches; i++ {
		start := i * batchSize
		end := start + batchSize
		if end > len(events) {
			end = len(events)
		}

		batch := events[start:end]
		isLast := i == totalBatches-1

		flatEvents := make([]map[string]interface{}, len(batch))
		for j, event := range batch {
			flatEvent := map[string]interface{}{
				"player_steam_id": event.PlayerSteamID,
				"round_number":    event.RoundNumber,

				// Gun Fights
				"kills":          event.Kills,
				"assists":        event.Assists,
				"died":           event.Died,
				"damage":         event.Damage,
				"headshots":      event.Headshots,
				"first_kill":     event.FirstKill,
				"first_death":    event.FirstDeath,
				"kills_with_awp": event.KillsWithAWP,

				// Grenades
				"damage_dealt":              event.DamageDealt,
				"friendly_flash_duration":   event.FriendlyFlashDuration,
				"enemy_flash_duration":      event.EnemyFlashDuration,
				"friendly_players_affected": event.FriendlyPlayersAffected,
				"enemy_players_affected":    event.EnemyPlayersAffected,
				"flashes_thrown":            event.FlashesThrown,
				"fire_grenades_thrown":      event.FireGrenadesThrown,
				"smokes_thrown":             event.SmokesThrown,
				"hes_thrown":                event.HesThrown,
				"decoys_thrown":             event.DecoysThrown,
				"flashes_leading_to_kill":   event.FlashesLeadingToKill,
				"flashes_leading_to_death":  event.FlashesLeadingToDeath,
				"grenade_effectiveness":     event.GrenadeEffectiveness,
				"smoke_blocking_duration":   event.SmokeBlockingDuration,

				// Trade Details
				"successful_trades":            event.SuccessfulTrades,
				"total_possible_trades":        event.TotalPossibleTrades,
				"successful_traded_deaths":     event.SuccessfulTradedDeaths,
				"total_possible_traded_deaths": event.TotalPossibleTradedDeaths,

				// Clutch attempts and wins
				"clutch_attempts_1v1": event.ClutchAttempts1v1,
				"clutch_attempts_1v2": event.ClutchAttempts1v2,
				"clutch_attempts_1v3": event.ClutchAttempts1v3,
				"clutch_attempts_1v4": event.ClutchAttempts1v4,
				"clutch_attempts_1v5": event.ClutchAttempts1v5,
				"clutch_wins_1v1":     event.ClutchWins1v1,
				"clutch_wins_1v2":     event.ClutchWins1v2,
				"clutch_wins_1v3":     event.ClutchWins1v3,
				"clutch_wins_1v4":     event.ClutchWins1v4,
				"clutch_wins_1v5":     event.ClutchWins1v5,

				"time_to_contact": event.TimeToContact,

				// Economy
				"is_eco":                      event.IsEco,
				"is_force_buy":                event.IsForceBuy,
				"is_full_buy":                 event.IsFullBuy,
				"kills_vs_eco":                event.KillsVsEco,
				"kills_vs_force_buy":          event.KillsVsForceBuy,
				"kills_vs_full_buy":           event.KillsVsFullBuy,
				"grenade_value_lost_on_death": event.GrenadeValueLostOnDeath,

				// Impact Rating Fields
				"total_impact":        event.TotalImpact,
				"average_impact":      event.AverageImpact,
				"round_swing_percent": event.RoundSwingPercent,
				"impact_percentage":   event.ImpactPercentage,
			}

			// Handle optional fields
			if event.RoundTimeOfDeath != nil {
				flatEvent["round_time_of_death"] = *event.RoundTimeOfDeath
			}

			flatEvents[j] = flatEvent
		}

		payload := map[string]interface{}{
			"batch_index":   i + 1,
			"is_last":       isLast,
			"total_batches": totalBatches,
			"data":          flatEvents,
		}

		url := bs.baseURL + fmt.Sprintf(api.JobEventEndpoint, jobID, api.EventTypePlayerRound)
		if err := bs.sendRequestWithRetry(ctx, url, payload); err != nil {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send player round events", err)
			parseError = parseError.WithContext("job_id", jobID)
			parseError = parseError.WithContext("batch_number", i+1)
			parseError = parseError.WithContext("url", url)
			bs.progressManager.ReportParseError(parseError)
			return parseError
		}

		// Log impact values for first batch
		if i == 0 && len(batch) > 0 {
			// Sending player round events with impact values
		}

		// Sent player round events batch
	}

	return nil
}

func (bs *BatchSender) SendPlayerMatchEvents(ctx context.Context, jobID string, completionURL string, events []types.PlayerMatchEvent) error {
	if len(events) == 0 {
		return nil
	}

	// Extract base URL from completion URL
	baseURL, err := bs.extractBaseURL(completionURL)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send player match events", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}
	bs.baseURL = baseURL

	batchSize := 10
	totalBatches := (len(events) + batchSize - 1) / batchSize

	// Sending player round events

	for i := 0; i < totalBatches; i++ {
		start := i * batchSize
		end := start + batchSize
		if end > len(events) {
			end = len(events)
		}

		batch := events[start:end]
		isLast := i == totalBatches-1

		flatEvents := make([]map[string]interface{}, len(batch))
		for j, event := range batch {
			flatEvent := map[string]interface{}{
				"player_steam_id":               event.PlayerSteamID,
				"kills":                         event.Kills,
				"assists":                       event.Assists,
				"deaths":                        event.Deaths,
				"damage":                        event.Damage,
				"adr":                           event.ADR,
				"headshots":                     event.Headshots,
				"first_kills":                   event.FirstKills,
				"first_deaths":                  event.FirstDeaths,
				"average_round_time_of_death":   event.AverageRoundTimeOfDeath,
				"kills_with_awp":                event.KillsWithAWP,
				"damage_dealt":                  event.DamageDealt,
				"flashes_thrown":                event.FlashesThrown,
				"fire_grenades_thrown":          event.FireGrenadesThrown,
				"smokes_thrown":                 event.SmokesThrown,
				"hes_thrown":                    event.HesThrown,
				"decoys_thrown":                 event.DecoysThrown,
				"friendly_flash_duration":       event.FriendlyFlashDuration,
				"enemy_flash_duration":          event.EnemyFlashDuration,
				"friendly_players_affected":     event.FriendlyPlayersAffected,
				"enemy_players_affected":        event.EnemyPlayersAffected,
				"flashes_leading_to_kills":      event.FlashesLeadingToKills,
				"flashes_leading_to_deaths":     event.FlashesLeadingToDeaths,
				"average_grenade_effectiveness": event.AverageGrenadeEffectiveness,
				"smoke_blocking_duration":       event.SmokeBlockingDuration,
				"average_grenade_value_lost":    event.AverageGrenadeValueLost,
				"total_successful_trades":       event.TotalSuccessfulTrades,
				"total_possible_trades":         event.TotalPossibleTrades,
				"total_traded_deaths":           event.TotalTradedDeaths,
				"total_possible_traded_deaths":  event.TotalPossibleTradedDeaths,
				"clutch_wins_1v1":               event.ClutchWins1v1,
				"clutch_wins_1v2":               event.ClutchWins1v2,
				"clutch_wins_1v3":               event.ClutchWins1v3,
				"clutch_wins_1v4":               event.ClutchWins1v4,
				"clutch_wins_1v5":               event.ClutchWins1v5,
				"clutch_attempts_1v1":           event.ClutchAttempts1v1,
				"clutch_attempts_1v2":           event.ClutchAttempts1v2,
				"clutch_attempts_1v3":           event.ClutchAttempts1v3,
				"clutch_attempts_1v4":           event.ClutchAttempts1v4,
				"clutch_attempts_1v5":           event.ClutchAttempts1v5,
				"average_time_to_contact":       event.AverageTimeToContact,
				"kills_vs_eco":                  event.KillsVsEco,
				"kills_vs_force_buy":            event.KillsVsForceBuy,
				"kills_vs_full_buy":             event.KillsVsFullBuy,
				"matchmaking_rank":              event.MatchmakingRank,
				"rank_type":                     event.RankType,
				"rank_value":                    event.RankValue,

				// Impact Rating Fields
				"total_impact":        event.TotalImpact,
				"average_impact":      event.AverageImpact,
				"match_swing_percent": event.MatchSwingPercent,
				"impact_percentage":   event.ImpactPercentage,
			}

			flatEvents[j] = flatEvent
		}

		payload := map[string]interface{}{
			"batch_index":   i + 1,
			"is_last":       isLast,
			"total_batches": totalBatches,
			"data":          flatEvents,
		}

		url := bs.baseURL + fmt.Sprintf(api.JobEventEndpoint, jobID, api.EventTypePlayerMatch)
		if err := bs.sendRequestWithRetry(ctx, url, payload); err != nil {
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send player round events", err)
			parseError = parseError.WithContext("job_id", jobID)
			parseError = parseError.WithContext("batch_number", i+1)
			parseError = parseError.WithContext("url", url)
			bs.progressManager.ReportParseError(parseError)
			return parseError
		}

		// Sent player round events batch
	}

	return nil
}

func (bs *BatchSender) SendMatchData(ctx context.Context, jobID string, completionURL string, match types.Match) error {
	// Sending match data

	// Extract base URL from completion URL
	baseURL, err := bs.extractBaseURL(completionURL)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send match data", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}
	bs.baseURL = baseURL

	payload := map[string]interface{}{
		"job_id": jobID,
		"data":   match,
	}

	url := bs.baseURL + fmt.Sprintf(api.JobEventEndpoint, jobID, api.EventTypeMatch)
	if err := bs.sendRequestWithRetry(ctx, url, payload); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send match data", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}

	// Successfully sent match data

	return nil
}

func (bs *BatchSender) SendCompletion(ctx context.Context, jobID string, completionURL string) error {
	// Sending completion signal

	payload := map[string]interface{}{
		"job_id": jobID,
		"status": types.StatusCompleted,
	}

	if err := bs.sendRequest(ctx, completionURL, payload); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send completion signal", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}

	return nil
}

func (bs *BatchSender) SendError(ctx context.Context, jobID string, completionURL string, errorMsg string) error {
	bs.logger.WithFields(logrus.Fields{
		"job_id": jobID,
		"error":  errorMsg,
	}).Error("Sending error signal")

	payload := map[string]interface{}{
		"job_id": jobID,
		"status": types.StatusFailed,
		"error":  errorMsg,
	}

	if err := bs.sendRequest(ctx, completionURL, payload); err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, "failed to send error signal", err)
		parseError = parseError.WithContext("job_id", jobID)
		parseError = parseError.WithContext("completion_url", completionURL)
		parseError = parseError.WithContext("error_message", errorMsg)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}

	return nil
}

func (bs *BatchSender) sendRequest(ctx context.Context, url string, payload interface{}) error {
	jsonData, err := json.Marshal(payload)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeUnknown, types.ErrorSeverityCritical, "failed to marshal JSON", err)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}

	req, err := http.NewRequestWithContext(ctx, "POST", url, bytes.NewBuffer(jsonData))
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeUnknown, types.ErrorSeverityCritical, "failed to create request", err)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}

	req.Header.Set("Content-Type", "application/json")

	// Add API key for Laravel callback endpoints
	if bs.config.Server.APIKey != "" {
		req.Header.Set("X-API-Key", bs.config.Server.APIKey)
	}

	resp, err := bs.client.Do(req)
	if err != nil {
		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityCritical, "failed to send request", err)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		// Determine severity based on status code
		var severity types.ErrorSeverity
		if resp.StatusCode >= 500 {
			severity = types.ErrorSeverityCritical // 5xx errors are CRITICAL
		} else {
			severity = types.ErrorSeverityError // 4xx errors are ERROR
		}

		parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, severity, fmt.Sprintf("HTTP request failed with status %d", resp.StatusCode), nil)
		parseError = parseError.WithContext("status_code", resp.StatusCode)
		parseError = parseError.WithContext("url", url)
		bs.progressManager.ReportParseError(parseError)
		return parseError
	}

	return nil
}

func (bs *BatchSender) sendRequestWithRetry(ctx context.Context, url string, payload interface{}) error {
	var lastErr error
	maxRetries := 2 // Limit to 2 retry attempts as per requirements

	for attempt := 1; attempt <= maxRetries+1; attempt++ { // +1 for initial attempt
		err := bs.sendRequest(ctx, url, payload)
		if err == nil {
			return nil
		}

		lastErr = err

		// Log retry attempts with appropriate severity
		if attempt <= maxRetries {
			// First 2 attempts are WARNING level
			parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityWarning, "Request failed, retrying", err)
			parseError = parseError.WithContext("url", url)
			parseError = parseError.WithContext("attempt", attempt)
			parseError = parseError.WithContext("max_retries", maxRetries)
			bs.progressManager.ReportParseError(parseError)

			bs.logger.WithFields(logrus.Fields{
				"url":     url,
				"attempt": attempt,
				"error":   err,
			}).Warn("Request failed, retrying")

			time.Sleep(bs.config.Batch.RetryDelay)
		}
	}

	// Final failure is ERROR level
	parseError := types.NewParseErrorWithSeverity(types.ErrorTypeNetwork, types.ErrorSeverityError, fmt.Sprintf("request failed after %d attempts", maxRetries+1), lastErr)
	parseError = parseError.WithContext("url", url)
	parseError = parseError.WithContext("total_attempts", maxRetries+1)
	bs.progressManager.ReportParseError(parseError)

	return parseError
}
