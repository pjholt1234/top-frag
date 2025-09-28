package parser

import (
	"testing"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
)

func TestNewMovementStateService(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	assert.NotNil(t, service)
	assert.Equal(t, logger, service.logger)
	assert.NotNil(t, service.positionRecords)
	assert.Empty(t, service.positionRecords)
	assert.Equal(t, 0, service.currentRound)
}

func TestMovementStateService_UpdatePlayerPosition(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	// Test with nil player
	service.UpdatePlayerPosition(nil, 1000)
	assert.Empty(t, service.positionRecords)

	// Test with valid player
	player := &common.Player{
		SteamID64: 76561198000000000,
		Name:      "TestPlayer",
	}

	service.UpdatePlayerPosition(player, 1000)
	assert.Len(t, service.positionRecords, 1)

	record := service.positionRecords[0]
	assert.Equal(t, player.SteamID64, record.SteamID)
	assert.Equal(t, 0, record.Round) // Default round
	assert.Equal(t, int64(1000), record.Tick)
}

func TestMovementStateService_RecordPlayerPosition(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	// Test with nil player
	service.RecordPlayerPosition(nil, 1000)
	assert.Empty(t, service.positionRecords)

	// Test with valid player
	player := &common.Player{
		SteamID64: 76561198000000000,
		Name:      "TestPlayer",
	}

	service.RecordPlayerPosition(player, 1000)

	assert.Len(t, service.positionRecords, 1)
	record := service.positionRecords[0]

	assert.Equal(t, player.SteamID64, record.SteamID)
	assert.Equal(t, 0, record.Round)
	assert.Equal(t, int64(1000), record.Tick)
}

func TestMovementStateService_SetCurrentRound(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	// Test setting round
	service.SetCurrentRound(5)
	assert.Equal(t, 5, service.currentRound)

	// Test setting round to 0
	service.SetCurrentRound(0)
	assert.Equal(t, 0, service.currentRound)

	// Test setting negative round
	service.SetCurrentRound(-1)
	assert.Equal(t, -1, service.currentRound)
}

func TestMovementStateService_GetPlayerThrowType(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	player := &common.Player{
		SteamID64: 76561198000000000,
		Name:      "TestPlayer",
	}

	// Test getting throw type
	throwType := service.GetPlayerThrowType(player, 1000)
	assert.Equal(t, "Standing", throwType)
}

func TestMovementStateService_ClearPositionHistory(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	// Add some records
	player := &common.Player{
		SteamID64: 76561198000000000,
		Name:      "TestPlayer",
	}

	service.RecordPlayerPosition(player, 1000)
	service.RecordPlayerPosition(player, 1001)

	assert.Len(t, service.positionRecords, 2)

	// Clear position history (should not affect records)
	service.ClearPositionHistory()
	assert.Len(t, service.positionRecords, 2) // Records should still be there
}

func TestMovementStateService_ConcurrentAccess(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	// Test concurrent access
	done := make(chan bool, 10)

	for i := 0; i < 10; i++ {
		go func(id int) {
			player := &common.Player{
				SteamID64: 76561198000000000 + uint64(id),
				Name:      "Player" + string(rune(id)),
			}

			service.RecordPlayerPosition(player, int64(1000+id))
			service.SetCurrentRound(id)

			records := service.positionRecords
			assert.GreaterOrEqual(t, len(records), 1)

			done <- true
		}(i)
	}

	// Wait for all goroutines to complete
	for i := 0; i < 10; i++ {
		<-done
	}

	// Verify all records were added
	records := service.positionRecords
	assert.Len(t, records, 10)
}

func TestMovementStateService_EdgeCases(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	// Test with zero tick
	player := &common.Player{
		SteamID64: 76561198000000000,
		Name:      "TestPlayer",
	}

	service.RecordPlayerPosition(player, 0)
	assert.Len(t, service.positionRecords, 1)
	assert.Equal(t, int64(0), service.positionRecords[0].Tick)

	// Test with negative tick
	service.RecordPlayerPosition(player, -1)
	assert.Len(t, service.positionRecords, 2)
	assert.Equal(t, int64(-1), service.positionRecords[1].Tick)

	// Test with very large tick
	service.RecordPlayerPosition(player, 999999999)
	assert.Len(t, service.positionRecords, 3)
	assert.Equal(t, int64(999999999), service.positionRecords[2].Tick)
}

func TestMovementStateService_PositionRecordFields(t *testing.T) {
	logger := logrus.New()
	service := NewMovementStateService(logger)

	player := &common.Player{
		SteamID64: 76561198000000000,
		Name:      "TestPlayer",
	}

	service.SetCurrentRound(5)
	service.RecordPlayerPosition(player, 1000)

	record := service.positionRecords[0]

	// Verify all fields
	assert.Equal(t, uint64(76561198000000000), record.SteamID)
	assert.Equal(t, 5, record.Round)
	assert.Equal(t, int64(1000), record.Tick)
	assert.False(t, record.IsDucking)
	assert.False(t, record.IsWalking)
	assert.False(t, record.IsJumping)
	assert.False(t, record.IsOnGround)
}
