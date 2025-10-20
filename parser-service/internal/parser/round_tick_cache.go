package parser

import (
	"context"
	"fmt"
	"time"

	"parser-service/internal/database"
	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
)

// RoundTickCache provides an in-memory cache for player tick data within a round
// This dramatically reduces database queries by loading all tick data for a round once
type RoundTickCache struct {
	playerTickService *database.PlayerTickService
	logger            *logrus.Logger
	matchID           string
	currentRound      int
	roundStartTick    int64
	roundEndTick      int64

	// tickData maps: tick -> playerID -> PlayerTickData
	// This structure allows O(1) lookup by tick and player
	tickData map[int64]map[string]*types.PlayerTickData

	// Metadata for monitoring
	totalRowsLoaded  int
	lastLoadDuration time.Duration
	cacheHits        int
	cacheMisses      int
}

// NewRoundTickCache creates a new round tick cache
func NewRoundTickCache(playerTickService *database.PlayerTickService, logger *logrus.Logger, matchID string) *RoundTickCache {
	return &RoundTickCache{
		playerTickService: playerTickService,
		logger:            logger,
		matchID:           matchID,
		tickData:          make(map[int64]map[string]*types.PlayerTickData),
	}
}

// LoadRound loads all player tick data for a specific round into memory
// This replaces multiple database queries with a single bulk query
func (c *RoundTickCache) LoadRound(ctx context.Context, roundNum int, startTick, endTick int64) error {
	loadStart := time.Now()

	// Handle edge case where endTick is 0 (use a fallback)
	if endTick == 0 || endTick < startTick {
		// Use startTick + estimated round duration (2 minutes max)
		endTick = startTick + (2 * 60 * 128) // 128 tick/sec
		c.logger.WithFields(logrus.Fields{
			"round":             roundNum,
			"start_tick":        startTick,
			"end_tick_fallback": endTick,
		}).Warn("Round end tick is 0, using fallback")
	}

	// Clear previous round data to free memory
	c.tickData = make(map[int64]map[string]*types.PlayerTickData)
	c.currentRound = roundNum
	c.roundStartTick = startTick
	c.roundEndTick = endTick

	// Reset cache stats for this round
	c.cacheHits = 0
	c.cacheMisses = 0

	// Load all tick data for this round from database
	data, err := c.playerTickService.GetPlayerTickDataByRound(ctx, c.matchID, startTick, endTick)
	if err != nil {
		return fmt.Errorf("failed to load round tick data: %w", err)
	}

	// Build in-memory index for O(1) lookups
	for _, tickDataPtr := range data {
		tick := tickDataPtr.Tick
		playerID := tickDataPtr.PlayerID

		// Initialize tick map if needed
		if c.tickData[tick] == nil {
			c.tickData[tick] = make(map[string]*types.PlayerTickData)
		}

		// Store pointer to avoid copying
		c.tickData[tick][playerID] = tickDataPtr
	}

	c.totalRowsLoaded = len(data)
	c.lastLoadDuration = time.Since(loadStart)

	// Log cache loading performance
	c.logger.WithFields(logrus.Fields{
		"round":        roundNum,
		"start_tick":   startTick,
		"end_tick":     endTick,
		"rows_loaded":  c.totalRowsLoaded,
		"duration_ms":  c.lastLoadDuration.Milliseconds(),
		"ticks_cached": len(c.tickData),
	}).Info("Loaded round tick data into cache")

	return nil
}

// GetPlayerTick retrieves player tick data from cache
// Returns nil if data not found
func (c *RoundTickCache) GetPlayerTick(tick int64, playerID string) *types.PlayerTickData {
	if tickMap, exists := c.tickData[tick]; exists {
		if playerData, found := tickMap[playerID]; found {
			c.cacheHits++
			return playerData
		}
	}

	c.cacheMisses++
	return nil
}

// GetAllTickDataForRound returns all cached tick data for the current round
// This is used when the entire dataset is needed (e.g., for aim tracking)
func (c *RoundTickCache) GetAllTickDataForRound() []*types.PlayerTickData {
	result := make([]*types.PlayerTickData, 0, c.totalRowsLoaded)

	for _, playerMap := range c.tickData {
		for _, playerData := range playerMap {
			result = append(result, playerData)
		}
	}

	return result
}

// GetTickDataByTickRange returns all player tick data within a specific tick range
// This is useful for analyzing specific time windows (e.g., smoke duration)
func (c *RoundTickCache) GetTickDataByTickRange(startTick, endTick int64) []*types.PlayerTickData {
	result := make([]*types.PlayerTickData, 0)

	for tick := startTick; tick <= endTick; tick++ {
		if playerMap, exists := c.tickData[tick]; exists {
			for _, playerData := range playerMap {
				result = append(result, playerData)
			}
		}
	}

	return result
}

// GetTickDataForPlayer returns all tick data for a specific player in the cached round
func (c *RoundTickCache) GetTickDataForPlayer(playerID string) []*types.PlayerTickData {
	result := make([]*types.PlayerTickData, 0)

	for _, playerMap := range c.tickData {
		if playerData, exists := playerMap[playerID]; exists {
			result = append(result, playerData)
		}
	}

	return result
}

// GetCacheStats returns statistics about cache performance
func (c *RoundTickCache) GetCacheStats() map[string]interface{} {
	hitRate := 0.0
	totalRequests := c.cacheHits + c.cacheMisses
	if totalRequests > 0 {
		hitRate = float64(c.cacheHits) / float64(totalRequests) * 100
	}

	return map[string]interface{}{
		"round":              c.currentRound,
		"rows_loaded":        c.totalRowsLoaded,
		"cache_hits":         c.cacheHits,
		"cache_misses":       c.cacheMisses,
		"hit_rate_percent":   hitRate,
		"load_duration_ms":   c.lastLoadDuration.Milliseconds(),
		"memory_estimate_mb": float64(c.totalRowsLoaded*141) / 1024 / 1024, // ~141 bytes per row
	}
}

// Clear clears the cache and frees memory
func (c *RoundTickCache) Clear() {
	c.tickData = make(map[int64]map[string]*types.PlayerTickData)
	c.totalRowsLoaded = 0
	c.cacheHits = 0
	c.cacheMisses = 0

	c.logger.WithFields(logrus.Fields{
		"round": c.currentRound,
	}).Debug("Cleared round tick cache")
}

// IsRoundLoaded checks if a round is currently loaded in cache
func (c *RoundTickCache) IsRoundLoaded(roundNum int) bool {
	return c.currentRound == roundNum && c.totalRowsLoaded > 0
}

// GetCurrentRound returns the currently cached round number
func (c *RoundTickCache) GetCurrentRound() int {
	return c.currentRound
}
