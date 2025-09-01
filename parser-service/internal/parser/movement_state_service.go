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
	POSITION_HISTORY_TICKS     = 64  // Keep 1 second of history (64 ticks)
	MOVEMENT_LOOKFORWARD_TICKS = 16  // Look 16 ticks after throw for movement detection
	MOVEMENT_THRESHOLD         = 1.0 // Minimum movement component to detect directional input
)

// MovementState represents the movement state of a player at a specific moment
type MovementState struct {
	IsDucking bool // From m_pMovementServices.m_bDucking
	IsWalking bool // From m_pMovementServices
	IsJumping bool // From ground state

	// Directional movement based on velocity and view direction
	MovingForward bool // W - Moving in view direction
	MovingBack    bool // S - Moving opposite to view direction
	MovingLeft    bool // A - Moving left relative to view direction
	MovingRight   bool // D - Moving right relative to view direction
}

// PlayerPositionRecord stores a single position data point with all movement-related data
type PlayerPositionRecord struct {
	SteamID    uint64
	Round      int
	Tick       int64
	Position   types.Position
	ViewAngle  float32 // ViewDirectionX() - yaw angle for directional calculation
	IsDucking  bool    // From m_pMovementServices.m_bDucking
	IsWalking  bool    // From m_bIsWalking
	IsJumping  bool    // From ground flags
	IsOnGround bool    // From m_fFlags & FL_ONGROUND
}

// PlayerPositionHistory stores position history for velocity calculation (legacy)
type PlayerPositionHistory struct {
	SteamID   uint64
	Positions []PositionSnapshot
}

// PositionSnapshot stores a player's position at a specific tick (legacy)
type PositionSnapshot struct {
	Tick     int64
	Position types.Position
}

// MovementStateService handles movement state detection for players
type MovementStateService struct {
	logger          *logrus.Logger
	positionHistory map[uint64]*PlayerPositionHistory // Map SteamID to position history (legacy)
	maxHistoryTicks int64                             // How many ticks to keep in history (legacy)
	positionRecords []PlayerPositionRecord            // All position records for post-processing
	currentRound    int                               // Current round number
}

// NewMovementStateService creates a new movement state service
func NewMovementStateService(logger *logrus.Logger) *MovementStateService {
	return &MovementStateService{
		logger:          logger,
		positionHistory: make(map[uint64]*PlayerPositionHistory),
		maxHistoryTicks: POSITION_HISTORY_TICKS,
		positionRecords: make([]PlayerPositionRecord, 0),
		currentRound:    0,
	}
}

// UpdatePlayerPosition updates the position history for a player (legacy)
func (mss *MovementStateService) UpdatePlayerPosition(player *common.Player, currentTick int64) {
	if player == nil {
		return
	}

	// Store in new vector structure
	mss.RecordPlayerPosition(player, currentTick)

	// Legacy code for backward compatibility
	steamID := player.SteamID64
	position := player.Position()

	// Get or create position history for this player
	if mss.positionHistory[steamID] == nil {
		mss.positionHistory[steamID] = &PlayerPositionHistory{
			SteamID:   steamID,
			Positions: make([]PositionSnapshot, 0),
		}
	}

	history := mss.positionHistory[steamID]

	// Add new position snapshot
	snapshot := PositionSnapshot{
		Tick: currentTick,
		Position: types.Position{
			X: position.X,
			Y: position.Y,
			Z: position.Z,
		},
	}

	history.Positions = append(history.Positions, snapshot)

	// Clean up old history (keep only maxHistoryTicks)
	cutoffTick := currentTick - mss.maxHistoryTicks
	for i, pos := range history.Positions {
		if pos.Tick >= cutoffTick {
			history.Positions = history.Positions[i:]
			break
		}
	}

	// If all positions are too old, keep only the last one
	if len(history.Positions) > 0 && history.Positions[0].Tick < cutoffTick {
		lastPos := history.Positions[len(history.Positions)-1]
		history.Positions = []PositionSnapshot{lastPos}
	}
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
		ViewAngle: player.ViewDirectionX(), // Store view angle for directional calculation
	}

	// Get movement states if pawn entity is available
	if pawnEntity != nil {
		// Get ducking state
		if duckingProp, hasDucking := pawnEntity.PropertyValue("m_pMovementServices.m_bDucking"); hasDucking {
			record.IsDucking = duckingProp.BoolVal()
		}

		// Get walking state
		if walkingProp, hasWalking := pawnEntity.PropertyValue("m_bIsWalking"); hasWalking {
			record.IsWalking = walkingProp.BoolVal()
		}

		// Get ground state
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

// ClearPositionHistory clears all position history (called at round start)
func (mss *MovementStateService) ClearPositionHistory() {
	mss.positionHistory = make(map[uint64]*PlayerPositionHistory)
}

// GetPlayerThrowType is a convenience method that combines movement state capture and string generation
func (mss *MovementStateService) GetPlayerThrowType(player *common.Player, currentTick int64) string {
	state := mss.GetPlayerMovementState(player, currentTick)
	return mss.GenerateThrowTypeString(state)
}

// GetPlayerMovementState captures the current movement state of a player
func (mss *MovementStateService) GetPlayerMovementState(player *common.Player, currentTick int64) *MovementState {
	if player == nil {
		return nil
	}

	pawnEntity := player.PlayerPawnEntity()
	if pawnEntity == nil {
		return nil
	}

	state := &MovementState{}

	// Get ducking state from movement services
	if duckingProp, hasDucking := pawnEntity.PropertyValue("m_pMovementServices.m_bDucking"); hasDucking {
		state.IsDucking = duckingProp.BoolVal()
	}

	// Get walking state from movement services
	if walkingProp, hasWalking := pawnEntity.PropertyValue("m_bIsWalking"); hasWalking {
		state.IsWalking = walkingProp.BoolVal()
	}

	// Get jumping state from ground flags
	if flagsProp, hasFlags := pawnEntity.PropertyValue("m_fFlags"); hasFlags {
		flags := int(flagsProp.UInt64())
		state.IsJumping = (flags & FL_ONGROUND) == 0
	}

	// Calculate W/A/S/D based on velocity and view direction
	mss.UpdatePlayerPosition(player, currentTick)
	state.MovingForward, state.MovingBack, state.MovingLeft, state.MovingRight = mss.CalculateDirectionalMovement(player, currentTick)

	return state
}

// CalculateDirectionalMovement determines W/A/S/D movement based on velocity vector and view direction
// Uses player position at throw and 16 ticks after
func (mss *MovementStateService) CalculateDirectionalMovement(player *common.Player, currentTick int64) (bool, bool, bool, bool) {
	if player == nil {
		return false, false, false, false
	}

	steamID := player.SteamID64
	history := mss.positionHistory[steamID]

	if history == nil {
		return false, false, false, false
	}

	// Find position at throw (current tick)
	var throwPosition *PositionSnapshot
	for i := len(history.Positions) - 1; i >= 0; i-- {
		if history.Positions[i].Tick == currentTick {
			throwPosition = &history.Positions[i]
			break
		}
	}

	// Find position 16 ticks AFTER throw (future position)
	targetTick := currentTick + MOVEMENT_LOOKFORWARD_TICKS
	var futurePosition *PositionSnapshot
	for i := len(history.Positions) - 1; i >= 0; i-- {
		if history.Positions[i].Tick == targetTick {
			futurePosition = &history.Positions[i]
			break
		}
	}

	if throwPosition == nil || futurePosition == nil {
		return false, false, false, false
	}

	// Calculate velocity vector from throw to 16 ticks later
	deltaX := futurePosition.Position.X - throwPosition.Position.X
	deltaY := futurePosition.Position.Y - throwPosition.Position.Y

	// Get player view angle (where they're facing)
	viewAngle := float64(player.ViewDirectionX()) * math.Pi / 180.0 // Convert to radians

	// Calculate forward/back and left/right components relative to view direction
	// Project movement vector onto view direction vectors
	viewX := math.Cos(viewAngle)
	viewY := math.Sin(viewAngle)

	// Forward/back: dot product with view direction
	forwardComponent := deltaX*viewX + deltaY*viewY

	// Left/right: dot product with perpendicular to view direction
	rightComponent := deltaX*(-viewY) + deltaY*viewX

	// Apply movement threshold to filter out micro-movements
	forward := forwardComponent > MOVEMENT_THRESHOLD
	back := forwardComponent < -MOVEMENT_THRESHOLD
	right := rightComponent > MOVEMENT_THRESHOLD
	left := rightComponent < -MOVEMENT_THRESHOLD

	return forward, back, left, right
}

// GenerateThrowTypeString converts movement state to a human-readable throw type string
func (mss *MovementStateService) GenerateThrowTypeString(state *MovementState) string {
	if state == nil {
		return "Unknown"
	}

	var components []string

	// Primary movement state
	if state.IsJumping {
		components = append(components, "Jumping")
	} else if state.IsDucking {
		components = append(components, "Crouched")
	}

	// Directional movement components
	var directions []string
	if state.MovingForward {
		directions = append(directions, "W")
	}
	if state.MovingBack {
		directions = append(directions, "S")
	}
	if state.MovingLeft {
		directions = append(directions, "A")
	}
	if state.MovingRight {
		directions = append(directions, "D")
	}

	// Add directions to components
	if len(directions) > 0 {
		directionStr := strings.Join(directions, " + ")
		if state.IsWalking {
			components = append(components, directionStr+" (Walking)")
		} else {
			components = append(components, directionStr)
		}
	}

	// If no movement detected, it's a standing throw
	if len(components) == 0 {
		return "Standing"
	}

	// Join components with " + "
	return strings.Join(components, " + ")
}

// findPosition finds a position record for specific steamID, round, and tick
func (mss *MovementStateService) findPosition(steamID uint64, round int, tick int64) *PlayerPositionRecord {
	// Debug: Show sample records to verify data exists, but search forward in time order
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
	// Try preferred tick first
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

	// Find position at throw
	throwPos := mss.findPosition(steamID, round, throwTick)
	if throwPos == nil {
		mss.logger.WithFields(logrus.Fields{
			"steam_id":   steamID,
			"round":      round,
			"throw_tick": throwTick,
		}).Debug("Could not find throw position")
		return "Standing" // Fallback
	}

	// Try to find position 16 ticks after throw (with fallback)
	futurePos := mss.findPositionWithFallback(steamID, round, throwTick+MOVEMENT_LOOKFORWARD_TICKS, throwTick)
	if futurePos == nil {
		mss.logger.WithFields(logrus.Fields{
			"steam_id":    steamID,
			"round":       round,
			"throw_tick":  throwTick,
			"target_tick": throwTick + MOVEMENT_LOOKFORWARD_TICKS,
		}).Debug("Could not find future position")
		return "Standing" // Fallback
	}

	// Calculate velocity vector from throw to future position
	deltaX := futurePos.Position.X - throwPos.Position.X
	deltaY := futurePos.Position.Y - throwPos.Position.Y

	// Get player view angle from the throw position record
	viewAngle := float64(throwPos.ViewAngle) * math.Pi / 180.0 // Convert to radians

	// Calculate forward/back and left/right components relative to view direction
	viewX := math.Cos(viewAngle)
	viewY := math.Sin(viewAngle)

	// Forward/back: dot product with view direction
	forwardComponent := deltaX*viewX + deltaY*viewY

	// Left/right: dot product with perpendicular to view direction
	rightComponent := deltaX*(-viewY) + deltaY*viewX

	// Apply movement threshold to filter out micro-movements
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

	// Build throw type string from stored movement state and calculated directional movement
	var components []string

	// Primary movement state from throw position
	if throwPos.IsJumping {
		components = append(components, "Jumping")
	} else if throwPos.IsDucking {
		components = append(components, "Crouched")
	}

	// Directional movement components
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

	// Add directions to components
	if len(directions) > 0 {
		directionStr := strings.Join(directions, " + ")
		if throwPos.IsWalking {
			components = append(components, directionStr+" (Walking)")
		} else {
			components = append(components, directionStr)
		}
	}

	// If no movement detected, it's a standing throw
	if len(components) == 0 {
		return "Standing"
	}

	// Join components with " + "
	return strings.Join(components, " + ")
}
