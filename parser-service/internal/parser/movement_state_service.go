package parser

import (
	"strings"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/sirupsen/logrus"
)

// Player flags constants
const (
	FL_ONGROUND = 1 << 0 // 1 - Player is on ground
)

// Movement thresholds (units per second)
const (
	WALKING_SPEED_THRESHOLD = 100.0 // Below this = stationary/slow
	RUNNING_SPEED_THRESHOLD = 200.0 // Above this = running
)

// MovementState represents the movement state of a player at a specific moment
type MovementState struct {
	IsOnGround bool
	IsDucking  bool
	IsWalking  bool // Shift-walking
	Velocity2D float64
	IsMoving   bool
}

// MovementStateService handles movement state detection for players
type MovementStateService struct {
	logger *logrus.Logger
}

// NewMovementStateService creates a new movement state service
func NewMovementStateService(logger *logrus.Logger) *MovementStateService {
	return &MovementStateService{
		logger: logger,
	}
}

// GetPlayerMovementState captures the current movement state of a player
func (mss *MovementStateService) GetPlayerMovementState(player *common.Player) *MovementState {
	if player == nil {
		mss.logger.Debug("GetPlayerMovementState called with nil player")
		return nil
	}

	pawnEntity := player.PlayerPawnEntity()
	if pawnEntity == nil {
		mss.logger.WithField("player", player.Name).Debug("No pawn entity found for player")
		return nil
	}

	state := &MovementState{}

	// Get ground state from flags
	if flagsProp, hasFlags := pawnEntity.PropertyValue("m_fFlags"); hasFlags {
		flags := int(flagsProp.UInt64())
		state.IsOnGround = (flags & FL_ONGROUND) != 0
	}

	// Get ducking state
	if duckingProp, hasDucking := pawnEntity.PropertyValue("m_pMovementServices.m_bDucking"); hasDucking {
		state.IsDucking = duckingProp.BoolVal()
	}

	// Get walking state (shift-walking)
	if walkingProp, hasWalking := pawnEntity.PropertyValue("m_bIsWalking"); hasWalking {
		state.IsWalking = walkingProp.BoolVal()
	}

	// Calculate 2D velocity
	state.Velocity2D = mss.calculatePlayer2DVelocity(player)
	state.IsMoving = state.Velocity2D > WALKING_SPEED_THRESHOLD

	mss.logger.WithFields(logrus.Fields{
		"player":      player.Name,
		"on_ground":   state.IsOnGround,
		"ducking":     state.IsDucking,
		"walking":     state.IsWalking,
		"velocity_2d": state.Velocity2D,
		"is_moving":   state.IsMoving,
	}).Debug("Captured player movement state")

	return state
}

// GenerateThrowTypeString converts movement state to a human-readable throw type string
func (mss *MovementStateService) GenerateThrowTypeString(state *MovementState) string {
	if state == nil {
		return "Unknown"
	}

	var components []string

	// Primary movement state
	if !state.IsOnGround {
		components = append(components, "Jumping")
	} else if state.IsDucking {
		components = append(components, "Crouched")
	}

	// Secondary movement modifiers
	if state.IsMoving {
		if state.IsWalking {
			components = append(components, "Walking")
		} else if state.Velocity2D > RUNNING_SPEED_THRESHOLD {
			components = append(components, "Running")
		} else {
			components = append(components, "Moving")
		}
	}

	// If no movement detected, it's a standing throw
	if len(components) == 0 {
		return "Standing"
	}

	// Join components with " + "
	return strings.Join(components, " + ")
}

// GetPlayerThrowType is a convenience method that combines movement state capture and string generation
func (mss *MovementStateService) GetPlayerThrowType(player *common.Player) string {
	state := mss.GetPlayerMovementState(player)
	return mss.GenerateThrowTypeString(state)
}

// calculatePlayer2DVelocity calculates the 2D movement velocity of a player
// This uses a simple method based on recent position changes
func (mss *MovementStateService) calculatePlayer2DVelocity(player *common.Player) float64 {
	// For now, we'll use the base velocity property if available
	// In a full implementation, you might want to track position changes over time

	pawnEntity := player.PlayerPawnEntity()
	if pawnEntity == nil {
		return 0.0
	}

	// Try to get velocity from entity properties
	if velocityProp, hasVelocity := pawnEntity.PropertyValue("m_vecBaseVelocity"); hasVelocity {
		// velocityProp should be a 3D vector, we need to extract X and Y components
		// For now, we'll use a simple approach
		velocityStr := velocityProp.String()

		// This is a simplified approach - in a real implementation you'd parse the vector properly
		// For demonstration, we'll return a basic calculation
		mss.logger.WithFields(logrus.Fields{
			"player":   player.Name,
			"velocity": velocityStr,
		}).Debug("Retrieved velocity property")
	}

	// Alternative: Use player position tracking over time
	// This would require storing previous positions and calculating distance/time
	// For now, return a placeholder velocity based on some movement indicator

	// If we have movement services data, we can infer movement
	if pawnEntity != nil {
		if buttonProp, hasButtons := pawnEntity.PropertyValue("m_pMovementServices.m_nButtonDownMaskPrev"); hasButtons {
			buttons := buttonProp.UInt64()

			// Movement buttons (from our earlier constants)
			IN_FORWARD := uint64(0x8)
			IN_BACK := uint64(0x10)
			IN_MOVELEFT := uint64(0x200)
			IN_MOVERIGHT := uint64(0x400)

			movementButtons := IN_FORWARD | IN_BACK | IN_MOVELEFT | IN_MOVERIGHT

			if buttons&movementButtons != 0 {
				// Player is pressing movement keys - assume moderate speed
				return 150.0 // Moderate movement speed
			}
		}
	}

	return 0.0 // No movement detected
}

// GetDetailedMovementAnalysis provides a detailed analysis of player movement for debugging
func (mss *MovementStateService) GetDetailedMovementAnalysis(player *common.Player) map[string]interface{} {
	analysis := make(map[string]interface{})

	if player == nil {
		analysis["error"] = "nil player"
		return analysis
	}

	analysis["player_name"] = player.Name
	analysis["steam_id"] = player.SteamID64

	pawnEntity := player.PlayerPawnEntity()
	if pawnEntity == nil {
		analysis["error"] = "no pawn entity"
		return analysis
	}

	// Collect all movement-related properties
	if flagsProp, hasFlags := pawnEntity.PropertyValue("m_fFlags"); hasFlags {
		flags := int(flagsProp.UInt64())
		analysis["flags"] = flags
		analysis["on_ground"] = (flags & FL_ONGROUND) != 0
	}

	if duckingProp, hasDucking := pawnEntity.PropertyValue("m_pMovementServices.m_bDucking"); hasDucking {
		analysis["ducking"] = duckingProp.BoolVal()
	}

	if duckJumpProp, hasDuckJump := pawnEntity.PropertyValue("m_pMovementServices.m_bInDuckJump"); hasDuckJump {
		analysis["duck_jumping"] = duckJumpProp.BoolVal()
	}

	if walkingProp, hasWalking := pawnEntity.PropertyValue("m_bIsWalking"); hasWalking {
		analysis["walking"] = walkingProp.BoolVal()
	}

	if buttonProp, hasButtons := pawnEntity.PropertyValue("m_pMovementServices.m_nButtonDownMaskPrev"); hasButtons {
		analysis["button_mask"] = buttonProp.UInt64()
	}

	if groundProp, hasGround := pawnEntity.PropertyValue("m_hGroundEntity"); hasGround {
		analysis["ground_entity"] = groundProp.Handle()
	}

	// Add position for reference
	pos := player.Position()
	analysis["position"] = map[string]float64{
		"x": pos.X,
		"y": pos.Y,
		"z": pos.Z,
	}

	// Calculate movement state
	state := mss.GetPlayerMovementState(player)
	if state != nil {
		analysis["movement_state"] = map[string]interface{}{
			"is_on_ground": state.IsOnGround,
			"is_ducking":   state.IsDucking,
			"is_walking":   state.IsWalking,
			"velocity_2d":  state.Velocity2D,
			"is_moving":    state.IsMoving,
		}
		analysis["throw_type"] = mss.GenerateThrowTypeString(state)
	}

	return analysis
}
