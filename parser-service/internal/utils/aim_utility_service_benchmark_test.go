package utils

import (
	"math/rand"
	"parser-service/internal/types"
	"sort"
	"testing"
	"time"
)

// BenchmarkAimUtilityService tests the performance improvements
func BenchmarkAimUtilityService(b *testing.B) {
	// Create test data
	shootingData := generateTestShootingData(1000)
	damageEvents := generateTestDamageEvents(1000)
	playerTickData := generateTestPlayerTickData(1000)

	// Create service
	service, err := NewAimUtilityService("de_dust2", nil)
	if err != nil {
		b.Fatalf("Failed to create service: %v", err)
	}

	b.ResetTimer()

	for i := 0; i < b.N; i++ {
		_, _, err := service.ProcessAimTrackingForRound(
			shootingData,
			damageEvents,
			playerTickData,
			1,
		)
		if err != nil {
			b.Fatalf("ProcessAimTrackingForRound failed: %v", err)
		}
	}
}

// BenchmarkSortingOptimization tests the bubble sort vs sort.Slice performance
func BenchmarkSortingOptimization(b *testing.B) {
	damageEvents := generateTestDamageEvents(1000)

	b.Run("OptimizedSort", func(b *testing.B) {
		for i := 0; i < b.N; i++ {
			sortedEvents := make([]types.DamageEvent, len(damageEvents))
			copy(sortedEvents, damageEvents)

			// Use optimized sort
			sort.Slice(sortedEvents, func(i, j int) bool {
				return sortedEvents[i].TickTimestamp < sortedEvents[j].TickTimestamp
			})
		}
	})

	b.Run("BubbleSort", func(b *testing.B) {
		for i := 0; i < b.N; i++ {
			sortedEvents := make([]types.DamageEvent, len(damageEvents))
			copy(sortedEvents, damageEvents)

			// Use bubble sort (old implementation)
			for i := 0; i < len(sortedEvents)-1; i++ {
				for j := 0; j < len(sortedEvents)-i-1; j++ {
					if sortedEvents[j].TickTimestamp > sortedEvents[j+1].TickTimestamp {
						sortedEvents[j], sortedEvents[j+1] = sortedEvents[j+1], sortedEvents[j]
					}
				}
			}
		}
	})
}

// BenchmarkHashLookups tests the performance of hash map lookups vs linear searches
func BenchmarkHashLookups(b *testing.B) {
	shots := generateTestShootingData(100)
	damages := generateTestDamageEvents(1000)

	b.Run("HashMapLookup", func(b *testing.B) {
		for i := 0; i < b.N; i++ {
			// Create damage lookup map
			damageByTick := make(map[int64][]types.DamageEvent)
			for _, damage := range damages {
				damageByTick[damage.TickTimestamp] = append(damageByTick[damage.TickTimestamp], damage)
			}

			// Simulate shot-damage matching with hash map
			for _, shot := range shots {
				for tickOffset := int64(-10); tickOffset <= 10; tickOffset++ {
					if damages, exists := damageByTick[shot.Tick+tickOffset]; exists {
						_ = damages // Use the result
						break
					}
				}
			}
		}
	})

	b.Run("LinearSearch", func(b *testing.B) {
		for i := 0; i < b.N; i++ {
			// Simulate old linear search approach
			for _, shot := range shots {
				for _, damage := range damages {
					if damage.AttackerSteamID == shot.PlayerID &&
						absInt64(damage.TickTimestamp-shot.Tick) <= 10 {
						break
					}
				}
			}
		}
	})
}

// BenchmarkMemoryAllocations tests memory allocation patterns
func BenchmarkMemoryAllocations(b *testing.B) {
	shots := generateTestShootingData(100)

	b.Run("PreAllocatedSlices", func(b *testing.B) {
		for i := 0; i < b.N; i++ {
			// Pre-allocate slices with known capacity
			crosshairX := make([]float64, 0, len(shots))
			crosshairY := make([]float64, 0, len(shots))
			reactionTimes := make([]float64, 0, len(shots))

			for _, shot := range shots {
				crosshairX = append(crosshairX, float64(shot.Tick))
				crosshairY = append(crosshairY, float64(shot.Tick))
				reactionTimes = append(reactionTimes, float64(shot.Tick))
			}
		}
	})

	b.Run("DynamicSlices", func(b *testing.B) {
		for i := 0; i < b.N; i++ {
			// Dynamic slice allocation (old approach)
			var crosshairX []float64
			var crosshairY []float64
			var reactionTimes []float64

			for _, shot := range shots {
				crosshairX = append(crosshairX, float64(shot.Tick))
				crosshairY = append(crosshairY, float64(shot.Tick))
				reactionTimes = append(reactionTimes, float64(shot.Tick))
			}
		}
	})
}

// Helper functions to generate test data
func generateTestShootingData(count int) []types.PlayerShootingData {
	rand.Seed(time.Now().UnixNano())
	shots := make([]types.PlayerShootingData, count)

	for i := 0; i < count; i++ {
		shots[i] = types.PlayerShootingData{
			PlayerID:    "test_player",
			WeaponName:  "ak47",
			Tick:        int64(rand.Intn(10000)),
			IsSpraying:  rand.Float32() < 0.3,
			RoundNumber: 1,
		}
	}

	return shots
}

func generateTestDamageEvents(count int) []types.DamageEvent {
	rand.Seed(time.Now().UnixNano())
	damages := make([]types.DamageEvent, count)

	for i := 0; i < count; i++ {
		damages[i] = types.DamageEvent{
			AttackerSteamID: "test_player",
			VictimSteamID:   "test_victim",
			TickTimestamp:   int64(rand.Intn(10000)),
			HitGroup:        rand.Intn(7),
			Weapon:          "ak47",
			RoundNumber:     1,
		}
	}

	return damages
}

func generateTestPlayerTickData(count int) []types.PlayerTickData {
	rand.Seed(time.Now().UnixNano())
	ticks := make([]types.PlayerTickData, count)

	for i := 0; i < count; i++ {
		ticks[i] = types.PlayerTickData{
			PlayerID:  "test_player",
			Tick:      int64(rand.Intn(10000)),
			PositionX: float64(rand.Float32() * 1000),
			PositionY: float64(rand.Float32() * 1000),
			PositionZ: float64(rand.Float32() * 100),
			AimX:      float64(rand.Float32() * 360),
			AimY:      float64(rand.Float32() * 360),
		}
	}

	return ticks
}

func absInt64(x int64) int64 {
	if x < 0 {
		return -x
	}
	return x
}
