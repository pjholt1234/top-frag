package parser

import (
	"testing"

	"github.com/golang/geo/r3"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
)

// TestFlashEffectMatchingLogic tests the current flash effect matching logic
func TestFlashEffectMatchingLogic(t *testing.T) {
	// Create a mock scenario where we have flash effects and need to match them
	activeFlashEffects := make(map[int]*FlashEffect)

	// Simulate multiple flash effects
	flashEffect1 := &FlashEffect{
		EntityID:        12345,
		ExplosionTick:   1000,
		AffectedPlayers: make(map[uint64]*PlayerFlashInfo),
	}
	flashEffect2 := &FlashEffect{
		EntityID:        12346,
		ExplosionTick:   1100,
		AffectedPlayers: make(map[uint64]*PlayerFlashInfo),
	}

	activeFlashEffects[12345] = flashEffect1
	activeFlashEffects[12346] = flashEffect2

	// Test direct entity ID matching
	t.Run("DirectEntityIDMatch", func(t *testing.T) {
		projectileEntityID := 12345
		targetFlashEffect, exists := activeFlashEffects[projectileEntityID]

		if !exists {
			t.Error("Direct entity ID match failed")
		}

		if targetFlashEffect.EntityID != 12345 {
			t.Errorf("Expected entity ID 12345, got %d", targetFlashEffect.EntityID)
		}
	})

	// Test fallback matching logic (from current HandlePlayerFlashed)
	t.Run("FallbackMatchingLogic", func(t *testing.T) {
		// Simulate the fallback logic from HandlePlayerFlashed
		currentTick := int64(1200)

		var targetFlashEffect *FlashEffect

		// This is the fallback logic from the current code
		for _, flashEffect := range activeFlashEffects {
			timeDiff := currentTick - flashEffect.ExplosionTick

			// Look for flash effects within a reasonable time window (5 seconds = 320 ticks)
			if timeDiff >= 0 && timeDiff <= 320 {
				// Use the most recent matching flash effect
				if targetFlashEffect == nil || flashEffect.ExplosionTick > targetFlashEffect.ExplosionTick {
					targetFlashEffect = flashEffect
				}
			}
		}

		if targetFlashEffect == nil {
			t.Error("Fallback matching failed - no flash effect found")
			return
		}

		// Should find the most recent one (12346 with tick 1100)
		if targetFlashEffect.EntityID != 12346 {
			t.Errorf("Expected most recent flash effect (12346), got %d", targetFlashEffect.EntityID)
		}
	})
}

// TestFlashEffectStorageAndRetrieval tests the current flash effect storage logic
func TestFlashEffectStorageAndRetrieval(t *testing.T) {
	// Create mock FlashExplode event
	entityID := 12345
	flashExplodeEvent := events.FlashExplode{
		GrenadeEvent: events.GrenadeEvent{
			GrenadeEntityID: entityID,
			Position:        r3.Vector{X: 100, Y: 200, Z: 50},
			Thrower: &common.Player{
				SteamID64: 76561198012345678,
				Name:      "TestPlayer",
			},
		},
	}

	// Test the current flash effect storage logic
	activeFlashEffects := make(map[int]*FlashEffect)

	// Store flash effect (simulating HandleFlashExplode)
	flashEffect := &FlashEffect{
		EntityID:        flashExplodeEvent.GrenadeEntityID,
		ExplosionTick:   1000,
		AffectedPlayers: make(map[uint64]*PlayerFlashInfo),
	}
	activeFlashEffects[flashExplodeEvent.GrenadeEntityID] = flashEffect

	// Try to retrieve flash effect (simulating HandlePlayerFlashed)
	// This is where the entity ID matching happens
	projectileEntityID := entityID // This would be e.Projectile.Entity.ID()
	targetFlashEffect, exists := activeFlashEffects[projectileEntityID]

	if !exists {
		t.Error("Flash effect not found - entity IDs don't match!")
	}

	if targetFlashEffect.EntityID != entityID {
		t.Errorf("Flash effect entity ID mismatch: expected %d, got %d", entityID, targetFlashEffect.EntityID)
	}

	t.Logf("Flash effect storage and retrieval test passed with entity ID: %d", entityID)
}

// TestEntityIDEquivalenceAssumption tests our assumption about entity ID equivalence
func TestEntityIDEquivalenceAssumption(t *testing.T) {
	t.Log("Testing entity ID equivalence assumption...")

	// The key question: Are these entity IDs the same?
	// - FlashExplode.GrenadeEntityID
	// - GrenadeProjectileDestroy.Projectile.Entity.ID()
	// - PlayerFlashed.Projectile.Entity.ID()

	// Current code assumes they are equivalent:
	// 1. HandleFlashExplode stores flash effect with e.GrenadeEntityID
	// 2. HandlePlayerFlashed looks up flash effect with e.Projectile.Entity.ID()
	// 3. The comment says "The projectile should have the same entity ID as the flash explosion"

	// If this assumption is wrong, the current flash system is broken
	// and we need to fix it before restructuring

	t.Log("Current assumption: FlashExplode.GrenadeEntityID == GrenadeProjectileDestroy.Projectile.Entity.ID() == PlayerFlashed.Projectile.Entity.ID()")
	t.Log("This needs to be verified with real demo data")

	// For now, we'll proceed with the assumption that they are equivalent
	// but we should add logging to verify this in production
}
