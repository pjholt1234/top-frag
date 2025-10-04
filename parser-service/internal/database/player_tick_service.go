package database

import (
	"context"
	"fmt"
	"time"

	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
	"gorm.io/gorm"
)

type PlayerTickService struct {
	db     *gorm.DB
	logger *logrus.Logger
}

// NewPlayerTickService creates a new player tick service
func NewPlayerTickService(db *gorm.DB, logger *logrus.Logger) *PlayerTickService {
	return &PlayerTickService{
		db:     db,
		logger: logger,
	}
}

// SavePlayerTickData saves a single player tick data record
func (s *PlayerTickService) SavePlayerTickData(ctx context.Context, data *types.PlayerTickData) error {
	if err := s.db.WithContext(ctx).Create(data).Error; err != nil {
		s.logger.WithFields(logrus.Fields{
			"match_id":  data.MatchID,
			"tick":      data.Tick,
			"player_id": data.PlayerID,
			"error":     err,
		}).Error("Failed to save player tick data")
		return fmt.Errorf("failed to save player tick data: %w", err)
	}
	return nil
}

// SavePlayerTickDataBatch saves multiple player tick data records in a batch
func (s *PlayerTickService) SavePlayerTickDataBatch(ctx context.Context, data []*types.PlayerTickData) error {
	if len(data) == 0 {
		return nil
	}

	// Use batch insert for better performance
	if err := s.db.WithContext(ctx).CreateInBatches(data, 1000).Error; err != nil {
		s.logger.WithFields(logrus.Fields{
			"batch_size": len(data),
			"error":      err,
		}).Error("Failed to save player tick data batch")
		return fmt.Errorf("failed to save player tick data batch: %w", err)
	}

	s.logger.WithField("batch_size", len(data)).Debug("Successfully saved player tick data batch")
	return nil
}

// GetPlayerTickDataByMatch retrieves all player tick data for a specific match
func (s *PlayerTickService) GetPlayerTickDataByMatch(ctx context.Context, matchID string) ([]*types.PlayerTickData, error) {
	var data []*types.PlayerTickData

	if err := s.db.WithContext(ctx).
		Where("match_id = ?", matchID).
		Order("tick ASC, player_id ASC").
		Find(&data).Error; err != nil {
		s.logger.WithFields(logrus.Fields{
			"match_id": matchID,
			"error":    err,
		}).Error("Failed to get player tick data by match")
		return nil, fmt.Errorf("failed to get player tick data by match: %w", err)
	}

	return data, nil
}

// GetPlayerTickDataByPlayer retrieves all player tick data for a specific player in a match
func (s *PlayerTickService) GetPlayerTickDataByPlayer(ctx context.Context, matchID, playerID string) ([]*types.PlayerTickData, error) {
	var data []*types.PlayerTickData

	if err := s.db.WithContext(ctx).
		Where("match_id = ? AND player_id = ?", matchID, playerID).
		Order("tick ASC").
		Find(&data).Error; err != nil {
		s.logger.WithFields(logrus.Fields{
			"match_id":  matchID,
			"player_id": playerID,
			"error":     err,
		}).Error("Failed to get player tick data by player")
		return nil, fmt.Errorf("failed to get player tick data by player: %w", err)
	}

	return data, nil
}

// GetPlayerTickDataByTickRange retrieves player tick data within a specific tick range
func (s *PlayerTickService) GetPlayerTickDataByTickRange(ctx context.Context, matchID string, startTick, endTick int64) ([]*types.PlayerTickData, error) {
	var data []*types.PlayerTickData

	if err := s.db.WithContext(ctx).
		Where("match_id = ? AND tick >= ? AND tick <= ?", matchID, startTick, endTick).
		Order("tick ASC, player_id ASC").
		Find(&data).Error; err != nil {
		s.logger.WithFields(logrus.Fields{
			"match_id":   matchID,
			"start_tick": startTick,
			"end_tick":   endTick,
			"error":      err,
		}).Error("Failed to get player tick data by tick range")
		return nil, fmt.Errorf("failed to get player tick data by tick range: %w", err)
	}

	return data, nil
}

// DeletePlayerTickDataByMatch deletes all player tick data for a specific match
func (s *PlayerTickService) DeletePlayerTickDataByMatch(ctx context.Context, matchID string) error {
	if err := s.db.WithContext(ctx).
		Where("match_id = ?", matchID).
		Delete(&types.PlayerTickData{}).Error; err != nil {
		s.logger.WithFields(logrus.Fields{
			"match_id": matchID,
			"error":    err,
		}).Error("Failed to delete player tick data by match")
		return fmt.Errorf("failed to delete player tick data by match: %w", err)
	}

	s.logger.WithField("match_id", matchID).Info("Successfully deleted player tick data for match")
	return nil
}

// GetPlayerTickDataByRound retrieves player tick data for a specific round
func (s *PlayerTickService) GetPlayerTickDataByRound(ctx context.Context, matchID string, roundStartTick, roundEndTick int64) ([]*types.PlayerTickData, error) {
	var data []*types.PlayerTickData

	if err := s.db.WithContext(ctx).
		Where("match_id = ? AND tick >= ? AND tick <= ?", matchID, roundStartTick, roundEndTick).
		Order("tick ASC, player_id ASC").
		Find(&data).Error; err != nil {
		s.logger.WithFields(logrus.Fields{
			"match_id":         matchID,
			"round_start_tick": roundStartTick,
			"round_end_tick":   roundEndTick,
			"error":            err,
		}).Error("Failed to get player tick data by round")
		return nil, fmt.Errorf("failed to get player tick data by round: %w", err)
	}

	s.logger.WithFields(logrus.Fields{
		"match_id":         matchID,
		"round_start_tick": roundStartTick,
		"round_end_tick":   roundEndTick,
		"records_found":    len(data),
	}).Debug("Retrieved player tick data for round")

	return data, nil
}

// GetPlayerTickDataStats returns statistics about player tick data for a match
func (s *PlayerTickService) GetPlayerTickDataStats(ctx context.Context, matchID string) (map[string]interface{}, error) {
	var stats struct {
		TotalRecords  int64 `json:"total_records"`
		UniqueTicks   int64 `json:"unique_ticks"`
		UniquePlayers int64 `json:"unique_players"`
		MinTick       int64 `json:"min_tick"`
		MaxTick       int64 `json:"max_tick"`
	}

	// Get total records
	if err := s.db.WithContext(ctx).
		Model(&types.PlayerTickData{}).
		Where("match_id = ?", matchID).
		Count(&stats.TotalRecords).Error; err != nil {
		return nil, fmt.Errorf("failed to get total records: %w", err)
	}

	// Get unique ticks count
	var uniqueTicks []int64
	if err := s.db.WithContext(ctx).
		Model(&types.PlayerTickData{}).
		Where("match_id = ?", matchID).
		Distinct("tick").
		Pluck("tick", &uniqueTicks).Error; err != nil {
		return nil, fmt.Errorf("failed to get unique ticks count: %w", err)
	}
	stats.UniqueTicks = int64(len(uniqueTicks))

	// Get unique players count
	var uniquePlayers []string
	if err := s.db.WithContext(ctx).
		Model(&types.PlayerTickData{}).
		Where("match_id = ?", matchID).
		Distinct("player_id").
		Pluck("player_id", &uniquePlayers).Error; err != nil {
		return nil, fmt.Errorf("failed to get unique players count: %w", err)
	}
	stats.UniquePlayers = int64(len(uniquePlayers))

	// Get min and max ticks
	var tickRange struct {
		MinTick int64 `json:"min_tick"`
		MaxTick int64 `json:"max_tick"`
	}
	if err := s.db.WithContext(ctx).
		Model(&types.PlayerTickData{}).
		Where("match_id = ?", matchID).
		Select("MIN(tick) as min_tick, MAX(tick) as max_tick").
		Scan(&tickRange).Error; err != nil {
		return nil, fmt.Errorf("failed to get tick range: %w", err)
	}
	stats.MinTick = tickRange.MinTick
	stats.MaxTick = tickRange.MaxTick

	return map[string]interface{}{
		"total_records":  stats.TotalRecords,
		"unique_ticks":   stats.UniqueTicks,
		"unique_players": stats.UniquePlayers,
		"min_tick":       stats.MinTick,
		"max_tick":       stats.MaxTick,
		"generated_at":   time.Now(),
	}, nil
}
