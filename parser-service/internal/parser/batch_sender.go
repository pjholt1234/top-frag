package parser

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"time"

	"github.com/sirupsen/logrus"
	"parser-service/internal/api"
	"parser-service/internal/config"
	"parser-service/internal/types"
)

type BatchSender struct {
	config *config.Config
	logger *logrus.Logger
	client *http.Client
	baseURL string
}

func NewBatchSender(cfg *config.Config, logger *logrus.Logger) *BatchSender {
	return &BatchSender{
		config: cfg,
		logger: logger,
		client: &http.Client{
			Timeout: cfg.Batch.HTTPTimeout,
		},
	}
}

func (bs *BatchSender) extractBaseURL(completionURL string) (string, error) {
	parsedURL, err := url.Parse(completionURL)
	if err != nil {
		bs.logger.WithError(err).Error("Failed to parse completion URL")
		return "", fmt.Errorf("failed to parse completion URL: %w", err)
	}
	
	baseURL := fmt.Sprintf("%s://%s", parsedURL.Scheme, parsedURL.Host)
	return baseURL, nil
}

func (bs *BatchSender) SendMatchMetadata(ctx context.Context, jobID string, completionURL string, matchData *types.ParsedDemoData) error {
	baseURL, err := bs.extractBaseURL(completionURL)
	if err != nil {
		return fmt.Errorf("failed to extract base URL: %w", err)
	}
	bs.baseURL = baseURL
	
	metadata := map[string]interface{}{
		"status": "processing_data",
		"match":  matchData.Match,
		"players": matchData.Players,
	}

	bs.logger.WithField("job_id", jobID).Info("Sending match metadata")
	
	if err := bs.sendRequest(ctx, completionURL, metadata); err != nil {
		return fmt.Errorf("failed to send match metadata: %w", err)
	}

	return nil
}

func (bs *BatchSender) SendGunfightEvents(ctx context.Context, jobID string, events []types.GunfightEvent) error {
	if len(events) == 0 {
		return nil
	}

	batchSize := bs.config.Batch.GunfightEventsSize
	totalBatches := (len(events) + batchSize - 1) / batchSize

	bs.logger.WithFields(logrus.Fields{
		"job_id":        jobID,
		"total_events":  len(events),
		"batch_size":    batchSize,
		"total_batches": totalBatches,
	}).Info("Sending gunfight events")

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
				"round_number":           event.RoundNumber,
				"round_time":             event.RoundTime,
				"tick_timestamp":         event.TickTimestamp,
				"player_1_steam_id":      event.Player1SteamID,
				"player_2_steam_id":      event.Player2SteamID,
				"player_1_hp_start":      event.Player1HPStart,
				"player_2_hp_start":      event.Player2HPStart,
				"player_1_armor":         event.Player1Armor,
				"player_2_armor":         event.Player2Armor,
				"player_1_flashed":       event.Player1Flashed,
				"player_2_flashed":       event.Player2Flashed,
				"player_1_weapon":        event.Player1Weapon,
				"player_2_weapon":        event.Player2Weapon,
				"player_1_equipment_value": event.Player1EquipValue,
				"player_2_equipment_value": event.Player2EquipValue,
				"player_1_x":             event.Player1Position.X,
				"player_1_y":             event.Player1Position.Y,
				"player_1_z":             event.Player1Position.Z,
				"player_2_x":             event.Player2Position.X,
				"player_2_y":             event.Player2Position.Y,
				"player_2_z":             event.Player2Position.Z,
				"distance":               event.Distance,
				"headshot":               event.Headshot,
				"wallbang":               event.Wallbang,
				"penetrated_objects":     event.PenetratedObjects,
				"victor_steam_id":        event.VictorSteamID,
				"damage_dealt":           event.DamageDealt,
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
			return fmt.Errorf("failed to send gunfight events batch %d: %w", i+1, err)
		}

		bs.logger.WithFields(logrus.Fields{
			"job_id":      jobID,
			"batch":       i + 1,
			"total_batches": totalBatches,
			"events":      len(batch),
		}).Debug("Sent gunfight events batch")
	}

	return nil
}

func (bs *BatchSender) SendGrenadeEvents(ctx context.Context, jobID string, events []types.GrenadeEvent) error {
	if len(events) == 0 {
		return nil
	}

	batchSize := bs.config.Batch.GrenadeEventsSize
	totalBatches := (len(events) + batchSize - 1) / batchSize

	bs.logger.WithFields(logrus.Fields{
		"job_id":        jobID,
		"total_events":  len(events),
		"batch_size":    batchSize,
		"total_batches": totalBatches,
	}).Info("Sending grenade events")

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
				"round_number":    event.RoundNumber,
				"round_time":      event.RoundTime,
				"tick_timestamp":  event.TickTimestamp,
				"player_steam_id": event.PlayerSteamID,
				"grenade_type":    event.GrenadeType,
				"player_x":        event.PlayerPosition.X,
				"player_y":        event.PlayerPosition.Y,
				"player_z":        event.PlayerPosition.Z,
				"player_aim_x":    event.PlayerAim.X,
				"player_aim_y":    event.PlayerAim.Y,
				"player_aim_z":    event.PlayerAim.Z,
				"damage_dealt":    event.DamageDealt,
				"throw_type":      event.ThrowType,
			}

			if event.GrenadeFinalPosition != nil {
				flatEvent["grenade_final_x"] = event.GrenadeFinalPosition.X
				flatEvent["grenade_final_y"] = event.GrenadeFinalPosition.Y
				flatEvent["grenade_final_z"] = event.GrenadeFinalPosition.Z
			}
			if event.FlashDuration != nil {
				flatEvent["flash_duration"] = *event.FlashDuration
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
			return fmt.Errorf("failed to send grenade events batch %d: %w", i+1, err)
		}

		bs.logger.WithFields(logrus.Fields{
			"job_id":      jobID,
			"batch":       i + 1,
			"total_batches": totalBatches,
			"events":      len(batch),
		}).Debug("Sent grenade events batch")
	}

	return nil
}

func (bs *BatchSender) SendDamageEvents(ctx context.Context, jobID string, events []types.DamageEvent) error {
	if len(events) == 0 {
		return nil
	}

	batchSize := bs.config.Batch.DamageEventsSize
	totalBatches := (len(events) + batchSize - 1) / batchSize

	bs.logger.WithFields(logrus.Fields{
		"job_id":        jobID,
		"total_events":  len(events),
		"batch_size":    batchSize,
		"total_batches": totalBatches,
	}).Info("Sending damage events")

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
			return fmt.Errorf("failed to send damage events batch %d: %w", i+1, err)
		}

		bs.logger.WithFields(logrus.Fields{
			"job_id":      jobID,
			"batch":       i + 1,
			"total_batches": totalBatches,
			"events":      len(batch),
		}).Debug("Sent damage events batch")
	}

	return nil
}

func (bs *BatchSender) SendRoundEvents(ctx context.Context, jobID string, events []types.RoundEvent) error {
	if len(events) == 0 {
		return nil
	}

	bs.logger.WithFields(logrus.Fields{
		"job_id":       jobID,
		"total_events": len(events),
	}).Info("Sending round events")

	flatEvents := make([]map[string]interface{}, len(events))
	for i, event := range events {
		flatEvent := map[string]interface{}{
			"round_number":   event.RoundNumber,
			"tick_timestamp": event.TickTimestamp,
			"event_type":     event.EventType,
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
		return fmt.Errorf("failed to send round events: %w", err)
	}

	bs.logger.WithField("job_id", jobID).Debug("Sent round events")
	return nil
}

func (bs *BatchSender) SendCompletion(ctx context.Context, jobID string, completionURL string) error {
	bs.logger.WithField("job_id", jobID).Info("Sending completion signal")

	payload := map[string]interface{}{
		"status": "completed",
	}

	if err := bs.sendRequest(ctx, completionURL, payload); err != nil {
		return fmt.Errorf("failed to send completion signal: %w", err)
	}

	return nil
}

func (bs *BatchSender) SendError(ctx context.Context, jobID string, completionURL string, errorMsg string) error {
	bs.logger.WithFields(logrus.Fields{
		"job_id": jobID,
		"error":  errorMsg,
	}).Error("Sending error signal")

	payload := map[string]interface{}{
		"status": "failed",
		"error":  errorMsg,
	}

	if err := bs.sendRequest(ctx, completionURL, payload); err != nil {
		return fmt.Errorf("failed to send error signal: %w", err)
	}

	return nil
}

func (bs *BatchSender) sendRequest(ctx context.Context, url string, payload interface{}) error {
	jsonData, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal JSON: %w", err)
	}

	req, err := http.NewRequestWithContext(ctx, "POST", url, bytes.NewBuffer(jsonData))
	if err != nil {
		return fmt.Errorf("failed to create request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	
	// Add API key for Laravel callback endpoints
	if bs.config.Server.APIKey != "" {
		req.Header.Set("X-API-Key", bs.config.Server.APIKey)
	}

	resp, err := bs.client.Do(req)
	if err != nil {
		return fmt.Errorf("failed to send request: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("HTTP request failed with status %d", resp.StatusCode)
	}

	return nil
}

func (bs *BatchSender) sendRequestWithRetry(ctx context.Context, url string, payload interface{}) error {
	var lastErr error

	for attempt := 1; attempt <= bs.config.Batch.RetryAttempts; attempt++ {
		err := bs.sendRequest(ctx, url, payload)
		if err == nil {
			return nil
		}

		lastErr = err
		bs.logger.WithFields(logrus.Fields{
			"url":     url,
			"attempt": attempt,
			"error":   err,
		}).Warn("Request failed, retrying")

		if attempt < bs.config.Batch.RetryAttempts {
			time.Sleep(bs.config.Batch.RetryDelay)
		}
	}

	return fmt.Errorf("request failed after %d attempts: %w", bs.config.Batch.RetryAttempts, lastErr)
} 