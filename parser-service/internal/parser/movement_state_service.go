package parser

import (
	"math"
	"strings"

	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/sirupsen/logrus"
)

// Player flags constants
const (
	FL_ONGROUND = 1 << 0 // 1 - Player is on ground
)

// Position tracking constants
const (
	MOVEMENT_LOOKFORWARD_TICKS = 16  // Look 16 ticks after throw for movement detection
	MOVEMENT_THRESHOLD         = 1.0 // Minimum movement component to detect directional input
)

// PlayerPositionRecord stores a single position data point with all movement-related data
type PlayerPositionRecord struct {
	SteamID    uint64
	Round      int
	Tick       int64
	Position   types.Position
	ViewAngle  float32 // Yaw angle for directional calculation
	IsDucking  bool
	IsWalking  bool
	IsJumping  bool
	IsOnGround bool
}

// MovementStateService handles movement state detection for players
type MovementStateService struct {
	logger          *logrus.Logger
	positionRecords []PlayerPositionRecord
	currentRound    int
}

// NewMovementStateService creates a new movement state service
func NewMovementStateService(logger *logrus.Logger) *MovementStateService {
	return &MovementStateService{
		logger:          logger,
		positionRecords: make([]PlayerPositionRecord, 0),
		currentRound:    0,
	}
}

// UpdatePlayerPosition records player position for movement analysis
func (mss *MovementStateService) UpdatePlayerPosition(player *common.Player, currentTick int64) {
	if player == nil {
		return
	}

	mss.RecordPlayerPosition(player, currentTick)
}

// RecordPlayerPosition stores a position record for post-processing
func (mss *MovementStateService) RecordPlayerPosition(player *common.Player, currentTick int64) {
	if player == nil {
		return
	}

	position := player.Position()
	pawnEntity := player.PlayerPawnEntity()

	record := PlayerPositionRecord{
		SteamID: player.SteamID64,
		Round:   mss.currentRound,
		Tick:    currentTick,
		Position: types.Position{
			X: position.X,
			Y: position.Y,
			Z: position.Z,
		},
		ViewAngle: player.ViewDirectionX(),
	}

	if pawnEntity != nil {
		if duckingProp, hasDucking := pawnEntity.PropertyValue("m_pMovementServices.m_bDucking"); hasDucking {
			record.IsDucking = duckingProp.BoolVal()
		}

		if walkingProp, hasWalking := pawnEntity.PropertyValue("m_bIsWalking"); hasWalking {
			record.IsWalking = walkingProp.BoolVal()
		}

		if flagsProp, hasFlags := pawnEntity.PropertyValue("m_fFlags"); hasFlags {
			flags := int(flagsProp.UInt64())
			record.IsOnGround = (flags & FL_ONGROUND) != 0
			record.IsJumping = !record.IsOnGround
		}
	}

	mss.positionRecords = append(mss.positionRecords, record)

	// Debug: Log position recording every 5000 ticks for first player to avoid spam
	if currentTick%5000 == 0 && len(mss.positionRecords)%10 == 1 {
		mss.logger.WithFields(logrus.Fields{
			"player":        player.Name,
			"tick":          currentTick,
			"round":         mss.currentRound,
			"total_records": len(mss.positionRecords),
		}).Debug("Recorded player position")
	}
}

// SetCurrentRound updates the current round number
func (mss *MovementStateService) SetCurrentRound(round int) {
	mss.currentRound = round
}

// ClearPositionHistory is kept for compatibility but does nothing
func (mss *MovementStateService) ClearPositionHistory() {
	// Position records are kept across rounds for post-processing
}

// GetPlayerThrowType provides a fallback for real-time tracking (replaced by post-processing)
func (mss *MovementStateService) GetPlayerThrowType(player *common.Player, currentTick int64) string {
	// Fallback - real calculation happens in post-processing
	return "Standing"
}

// findPosition finds a position record for specific steamID, round, and tick
func (mss *MovementStateService) findPosition(steamID uint64, round int, tick int64) *PlayerPositionRecord {
	// Debug: Show sample records for debugging
	sampleCount := 0
	for i := 0; i < len(mss.positionRecords) && sampleCount < 5; i++ {
		record := &mss.positionRecords[i]
		if record.SteamID == steamID && record.Round == round {
			mss.logger.WithFields(logrus.Fields{
				"steam_id":    steamID,
				"round":       round,
				"tick":        record.Tick,
				"target_tick": tick,
			}).Debug("Sample position record")
			sampleCount++
		}
	}

	// Search for exact tick match - but search in chronological order
	for i := 0; i < len(mss.positionRecords); i++ {
		record := &mss.positionRecords[i]
		if record.SteamID == steamID && record.Round == round && record.Tick == tick {
			mss.logger.WithFields(logrus.Fields{
				"steam_id":    steamID,
				"round":       round,
				"tick":        tick,
				"found_exact": true,
			}).Debug("Found exact position match")
			return record
		}
	}

	mss.logger.WithFields(logrus.Fields{
		"steam_id": steamID,
		"round":    round,
		"tick":     tick,
	}).Debug("No exact position match found")
	return nil
}

// findPositionWithFallback finds a position with fallback to earlier ticks
func (mss *MovementStateService) findPositionWithFallback(steamID uint64, round int, preferredTick int64, throwTick int64) *PlayerPositionRecord {

	if pos := mss.findPosition(steamID, round, preferredTick); pos != nil {
		return pos
	}

	// Fallback: try 15, 14, 13... down to throwTick+1
	for tick := preferredTick - 1; tick > throwTick; tick-- {
		if pos := mss.findPosition(steamID, round, tick); pos != nil {
			mss.logger.WithFields(logrus.Fields{
				"steam_id":       steamID,
				"round":          round,
				"preferred_tick": preferredTick,
				"found_tick":     tick,
			}).Debug("Used fallback position for movement calculation")
			return pos
		}
	}

	return nil
}

// CalculateGrenadeMovementSimple calculates movement using stored position records (for post-processing)
func (mss *MovementStateService) CalculateGrenadeMovementSimple(steamID uint64, round int, throwTick int64) string {
	mss.logger.WithFields(logrus.Fields{
		"steam_id":      steamID,
		"round":         round,
		"throw_tick":    throwTick,
		"total_records": len(mss.positionRecords),
	}).Debug("Starting grenade movement calculation")

	throwPos := mss.findPosition(steamID, round, throwTick)
	if throwPos == nil {
		mss.logger.WithFields(logrus.Fields{
			"steam_id":   steamID,
			"round":      round,
			"throw_tick": throwTick,
		}).Debug("Could not find throw position")
		return "Standing"
	}

	futurePos := mss.findPositionWithFallback(steamID, round, throwTick+MOVEMENT_LOOKFORWARD_TICKS, throwTick)
	if futurePos == nil {
		mss.logger.WithFields(logrus.Fields{
			"steam_id":    steamID,
			"round":       round,
			"throw_tick":  throwTick,
			"target_tick": throwTick + MOVEMENT_LOOKFORWARD_TICKS,
		}).Debug("Could not find future position")
		return "Standing"
	}

	deltaX := futurePos.Position.X - throwPos.Position.X
	deltaY := futurePos.Position.Y - throwPos.Position.Y

	viewAngle := float64(throwPos.ViewAngle) * math.Pi / 180.0
	viewX := math.Cos(viewAngle)
	viewY := math.Sin(viewAngle)

	forwardComponent := deltaX*viewX + deltaY*viewY
	rightComponent := deltaX*(-viewY) + deltaY*viewX

	forward := forwardComponent > MOVEMENT_THRESHOLD
	back := forwardComponent < -MOVEMENT_THRESHOLD
	right := rightComponent > MOVEMENT_THRESHOLD
	left := rightComponent < -MOVEMENT_THRESHOLD

	// Debug: Log movement calculation details
	mss.logger.WithFields(logrus.Fields{
		"steam_id":          steamID,
		"round":             round,
		"throw_tick":        throwTick,
		"throw_pos_x":       throwPos.Position.X,
		"throw_pos_y":       throwPos.Position.Y,
		"future_pos_x":      futurePos.Position.X,
		"future_pos_y":      futurePos.Position.Y,
		"deltaX":            deltaX,
		"deltaY":            deltaY,
		"view_angle":        throwPos.ViewAngle,
		"forward_component": forwardComponent,
		"right_component":   rightComponent,
		"forward":           forward,
		"back":              back,
		"left":              left,
		"right":             right,
		"is_jumping":        throwPos.IsJumping,
		"is_ducking":        throwPos.IsDucking,
		"is_walking":        throwPos.IsWalking,
	}).Debug("Movement calculation details")

	var components []string

	if throwPos.IsJumping {
		components = append(components, "Jumping")
	} else if throwPos.IsDucking {
		components = append(components, "Crouched")
	}
	var directions []string
	if forward {
		directions = append(directions, "W")
	}
	if back {
		directions = append(directions, "S")
	}
	if left {
		directions = append(directions, "A")
	}
	if right {
		directions = append(directions, "D")
	}

	if len(directions) > 0 {
		directionStr := strings.Join(directions, " + ")
		if throwPos.IsWalking {
			components = append(components, directionStr+" (Walking)")
		} else {
			components = append(components, directionStr)
		}
	}

	if len(components) == 0 {
		return "Standing"
	}

	return strings.Join(components, " + ")
}
