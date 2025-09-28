package database

import (
	"context"
	"testing"
	"time"

	"parser-service/internal/types"

	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
	"gorm.io/driver/sqlite"
	"gorm.io/gorm"
)

func TestNewPlayerTickService(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	assert.NotNil(t, service)
	assert.Equal(t, db, service.db)
	assert.Equal(t, logger, service.logger)
}

func TestPlayerTickService_SavePlayerTickData(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Create test data
	data := &types.PlayerTickData{
		MatchID:   "test-match-123",
		PlayerID:  "test-player-456",
		Tick:      1000,
		Team:      "CT",
		PositionX: 100.0,
		PositionY: 200.0,
		PositionZ: 50.0,
		AimX:      0.0,
		AimY:      90.0,
		CreatedAt: time.Now(),
	}

	// Test saving data
	ctx := context.Background()
	err = service.SavePlayerTickData(ctx, data)
	assert.NoError(t, err)

	// Verify data was saved
	var savedData types.PlayerTickData
	err = db.Where("match_id = ? AND player_id = ? AND tick = ?",
		data.MatchID, data.PlayerID, data.Tick).First(&savedData).Error
	assert.NoError(t, err)
	assert.Equal(t, data.MatchID, savedData.MatchID)
	assert.Equal(t, data.PlayerID, savedData.PlayerID)
	assert.Equal(t, data.Tick, savedData.Tick)
	assert.Equal(t, data.Team, savedData.Team)
	assert.Equal(t, data.PositionX, savedData.PositionX)
	assert.Equal(t, data.PositionY, savedData.PositionY)
	assert.Equal(t, data.PositionZ, savedData.PositionZ)
	assert.Equal(t, data.AimX, savedData.AimX)
	assert.Equal(t, data.AimY, savedData.AimY)
}

func TestPlayerTickService_SavePlayerTickDataBatch(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Create test data batch
	var dataBatch []*types.PlayerTickData
	for i := 0; i < 5; i++ {
		data := &types.PlayerTickData{
			MatchID:   "test-match-123",
			PlayerID:  "test-player-456",
			Tick:      int64(1000 + i),
			Team:      "CT",
			PositionX: float64(100 + i),
			PositionY: float64(200 + i),
			PositionZ: float64(50 + i),
			AimX:      0.0,
			AimY:      float64(90 + i),
			CreatedAt: time.Now(),
		}
		dataBatch = append(dataBatch, data)
	}

	// Test saving batch
	ctx := context.Background()
	err = service.SavePlayerTickDataBatch(ctx, dataBatch)
	assert.NoError(t, err)

	// Verify all data was saved
	var count int64
	err = db.Model(&types.PlayerTickData{}).Where("match_id = ?", "test-match-123").Count(&count).Error
	assert.NoError(t, err)
	assert.Equal(t, int64(5), count)
}

func TestPlayerTickService_SavePlayerTickDataBatch_EmptyBatch(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Test saving empty batch
	ctx := context.Background()
	err = service.SavePlayerTickDataBatch(ctx, []*types.PlayerTickData{})
	assert.NoError(t, err)
}

func TestPlayerTickService_GetPlayerTickDataByMatch(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Create test data
	matchID := "test-match-123"
	var testData []*types.PlayerTickData
	for i := 0; i < 3; i++ {
		data := &types.PlayerTickData{
			MatchID:   matchID,
			PlayerID:  "test-player-456",
			Tick:      int64(1000 + i),
			Team:      "CT",
			PositionX: float64(100 + i),
			PositionY: float64(200 + i),
			PositionZ: float64(50 + i),
			AimX:      0.0,
			AimY:      float64(90 + i),
			CreatedAt: time.Now(),
		}
		testData = append(testData, data)
	}

	// Save test data
	ctx := context.Background()
	err = service.SavePlayerTickDataBatch(ctx, testData)
	assert.NoError(t, err)

	// Test retrieving data by match
	retrievedData, err := service.GetPlayerTickDataByMatch(ctx, matchID)
	assert.NoError(t, err)
	assert.Len(t, retrievedData, 3)

	// Verify data is ordered by tick
	for i := 0; i < len(retrievedData)-1; i++ {
		assert.LessOrEqual(t, retrievedData[i].Tick, retrievedData[i+1].Tick)
	}
}

func TestPlayerTickService_GetPlayerTickDataByPlayer(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Create test data
	matchID := "test-match-123"
	playerID := "test-player-456"
	var testData []*types.PlayerTickData
	for i := 0; i < 3; i++ {
		data := &types.PlayerTickData{
			MatchID:   matchID,
			PlayerID:  playerID,
			Tick:      int64(1000 + i),
			Team:      "CT",
			PositionX: float64(100 + i),
			PositionY: float64(200 + i),
			PositionZ: float64(50 + i),
			AimX:      0.0,
			AimY:      float64(90 + i),
			CreatedAt: time.Now(),
		}
		testData = append(testData, data)
	}

	// Save test data
	ctx := context.Background()
	err = service.SavePlayerTickDataBatch(ctx, testData)
	assert.NoError(t, err)

	// Test retrieving data by player
	retrievedData, err := service.GetPlayerTickDataByPlayer(ctx, matchID, playerID)
	assert.NoError(t, err)
	assert.Len(t, retrievedData, 3)

	// Verify all data belongs to the correct player
	for _, data := range retrievedData {
		assert.Equal(t, matchID, data.MatchID)
		assert.Equal(t, playerID, data.PlayerID)
	}
}

func TestPlayerTickService_GetPlayerTickDataByTickRange(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Create test data
	matchID := "test-match-123"
	var testData []*types.PlayerTickData
	for i := 0; i < 10; i++ {
		data := &types.PlayerTickData{
			MatchID:   matchID,
			PlayerID:  "test-player-456",
			Tick:      int64(1000 + i),
			Team:      "CT",
			PositionX: float64(100 + i),
			PositionY: float64(200 + i),
			PositionZ: float64(50 + i),
			AimX:      0.0,
			AimY:      float64(90 + i),
			CreatedAt: time.Now(),
		}
		testData = append(testData, data)
	}

	// Save test data
	ctx := context.Background()
	err = service.SavePlayerTickDataBatch(ctx, testData)
	assert.NoError(t, err)

	// Test retrieving data by tick range
	retrievedData, err := service.GetPlayerTickDataByTickRange(ctx, matchID, 1002, 1007)
	assert.NoError(t, err)
	assert.Len(t, retrievedData, 6) // Ticks 1002, 1003, 1004, 1005, 1006, 1007

	// Verify all data is within the range
	for _, data := range retrievedData {
		assert.GreaterOrEqual(t, data.Tick, int64(1002))
		assert.LessOrEqual(t, data.Tick, int64(1007))
	}
}

func TestPlayerTickService_DeletePlayerTickDataByMatch(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Create test data
	matchID := "test-match-123"
	var testData []*types.PlayerTickData
	for i := 0; i < 3; i++ {
		data := &types.PlayerTickData{
			MatchID:   matchID,
			PlayerID:  "test-player-456",
			Tick:      int64(1000 + i),
			Team:      "CT",
			PositionX: float64(100 + i),
			PositionY: float64(200 + i),
			PositionZ: float64(50 + i),
			AimX:      0.0,
			AimY:      float64(90 + i),
			CreatedAt: time.Now(),
		}
		testData = append(testData, data)
	}

	// Save test data
	ctx := context.Background()
	err = service.SavePlayerTickDataBatch(ctx, testData)
	assert.NoError(t, err)

	// Verify data exists
	var count int64
	err = db.Model(&types.PlayerTickData{}).Where("match_id = ?", matchID).Count(&count).Error
	assert.NoError(t, err)
	assert.Equal(t, int64(3), count)

	// Test deleting data by match
	err = service.DeletePlayerTickDataByMatch(ctx, matchID)
	assert.NoError(t, err)

	// Verify data was deleted
	err = db.Model(&types.PlayerTickData{}).Where("match_id = ?", matchID).Count(&count).Error
	assert.NoError(t, err)
	assert.Equal(t, int64(0), count)
}

func TestPlayerTickService_GetPlayerTickDataStats(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Create test data
	matchID := "test-match-123"
	var testData []*types.PlayerTickData
	for i := 0; i < 5; i++ {
		for j := 0; j < 3; j++ {
			data := &types.PlayerTickData{
				MatchID:   matchID,
				PlayerID:  "test-player-" + string(rune(456+j)),
				Tick:      int64(1000 + i),
				Team:      "CT",
				PositionX: float64(100 + i),
				PositionY: float64(200 + i),
				PositionZ: float64(50 + i),
				AimX:      0.0,
				AimY:      float64(90 + i),
				CreatedAt: time.Now(),
			}
			testData = append(testData, data)
		}
	}

	// Save test data
	ctx := context.Background()
	err = service.SavePlayerTickDataBatch(ctx, testData)
	assert.NoError(t, err)

	// Test getting stats
	stats, err := service.GetPlayerTickDataStats(ctx, matchID)
	assert.NoError(t, err)
	assert.NotNil(t, stats)

	// Verify stats
	assert.Equal(t, int64(15), stats["total_records"]) // 5 ticks * 3 players
	assert.Equal(t, int64(5), stats["unique_ticks"])   // 5 unique ticks
	assert.Equal(t, int64(3), stats["unique_players"]) // 3 unique players
	assert.Equal(t, int64(1000), stats["min_tick"])    // Min tick
	assert.Equal(t, int64(1004), stats["max_tick"])    // Max tick
	assert.Contains(t, stats, "generated_at")
}

func TestPlayerTickService_ConcurrentAccess(t *testing.T) {
	// Use SQLite for testing
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	// Auto-migrate the table
	err = db.AutoMigrate(&types.PlayerTickData{})
	assert.NoError(t, err)

	logger := logrus.New()
	service := NewPlayerTickService(db, logger)

	// Test concurrent access
	done := make(chan bool, 10)
	errors := make(chan error, 10)

	ctx := context.Background()
	for i := 0; i < 10; i++ {
		go func(id int) {
			data := &types.PlayerTickData{
				MatchID:   "test-match-123",
				PlayerID:  "test-player-456",
				Tick:      int64(1000 + id),
				Team:      "CT",
				PositionX: float64(100 + id),
				PositionY: float64(200 + id),
				PositionZ: float64(50 + id),
				AimX:      0.0,
				AimY:      float64(90 + id),
				CreatedAt: time.Now(),
			}

			err := service.SavePlayerTickData(ctx, data)
			errors <- err
			done <- true
		}(i)
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}

	// Check for errors
	close(errors)
	for err := range errors {
		assert.NoError(t, err)
	}

	// Verify all data was saved
	var count int64
	err = db.Model(&types.PlayerTickData{}).Where("match_id = ?", "test-match-123").Count(&count).Error
	assert.NoError(t, err)
	assert.Equal(t, int64(10), count)
}
