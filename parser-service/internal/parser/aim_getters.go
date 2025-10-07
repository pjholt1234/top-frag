package parser

import (
	"parser-service/internal/types"
)

// GetAimEvents returns the collected aim tracking events
// aggregated at the match level (one entry per player)
func (ep *EventProcessor) GetAimEvents() []types.AimAnalysisResult {
	aggregated := ep.aggregateAimEvents(ep.aimEvents)

	return aggregated
}

// aggregateAimEvents aggregates per-round aim data into match-level data
func (ep *EventProcessor) aggregateAimEvents(roundEvents []types.AimAnalysisResult) []types.AimAnalysisResult {
	// Group by player
	aggregated := make(map[string]*types.AimAnalysisResult)

	for _, event := range roundEvents {
		if existing, exists := aggregated[event.PlayerSteamID]; exists {
			// Aggregate counts
			existing.ShotsFired += event.ShotsFired
			existing.ShotsHit += event.ShotsHit
			existing.SprayingShotsFired += event.SprayingShotsFired
			existing.SprayingShotsHit += event.SprayingShotsHit
			existing.HeadHitsTotal += event.HeadHitsTotal
			existing.UpperChestHitsTotal += event.UpperChestHitsTotal
			existing.ChestHitsTotal += event.ChestHitsTotal
			existing.LegsHitsTotal += event.LegsHitsTotal

			// Recalculate accuracy percentages
			if existing.ShotsFired > 0 {
				existing.AccuracyAllShots = float64(existing.ShotsHit) / float64(existing.ShotsFired) * 100.0
			}
			if existing.SprayingShotsFired > 0 {
				existing.SprayingAccuracy = float64(existing.SprayingShotsHit) / float64(existing.SprayingShotsFired) * 100.0
			}
			if existing.ShotsHit > 0 {
				existing.HeadshotAccuracy = float64(existing.HeadHitsTotal) / float64(existing.ShotsHit) * 100.0
			}

			// Average crosshair placement (weighted by shots fired)
			totalShots := float64(existing.ShotsFired)
			if totalShots > 0 {
				existing.AverageCrosshairPlacementX = (existing.AverageCrosshairPlacementX*(totalShots-float64(event.ShotsFired)) + event.AverageCrosshairPlacementX*float64(event.ShotsFired)) / totalShots
				existing.AverageCrosshairPlacementY = (existing.AverageCrosshairPlacementY*(totalShots-float64(event.ShotsFired)) + event.AverageCrosshairPlacementY*float64(event.ShotsFired)) / totalShots
			}

			// Average time to damage (weighted by shots hit)
			if existing.ShotsHit > 0 {
				existing.AverageTimeToDamage = (existing.AverageTimeToDamage*float64(existing.ShotsHit-event.ShotsHit) + event.AverageTimeToDamage*float64(event.ShotsHit)) / float64(existing.ShotsHit)
			}
		} else {
			// Create new aggregated entry (remove round number as it's match-level)
			aggregated[event.PlayerSteamID] = &types.AimAnalysisResult{
				PlayerSteamID:              event.PlayerSteamID,
				RoundNumber:                0, // Match-level data, no specific round
				ShotsFired:                 event.ShotsFired,
				ShotsHit:                   event.ShotsHit,
				AccuracyAllShots:           event.AccuracyAllShots,
				SprayingShotsFired:         event.SprayingShotsFired,
				SprayingShotsHit:           event.SprayingShotsHit,
				SprayingAccuracy:           event.SprayingAccuracy,
				AverageCrosshairPlacementX: event.AverageCrosshairPlacementX,
				AverageCrosshairPlacementY: event.AverageCrosshairPlacementY,
				HeadshotAccuracy:           event.HeadshotAccuracy,
				AverageTimeToDamage:        event.AverageTimeToDamage,
				HeadHitsTotal:              event.HeadHitsTotal,
				UpperChestHitsTotal:        event.UpperChestHitsTotal,
				ChestHitsTotal:             event.ChestHitsTotal,
				LegsHitsTotal:              event.LegsHitsTotal,
				AimRating:                  event.AimRating,
			}
		}
	}

	// Convert map to slice
	result := make([]types.AimAnalysisResult, 0, len(aggregated))
	for _, event := range aggregated {
		result = append(result, *event)
	}

	return result
}

// GetAimWeaponEvents returns the collected weapon-specific aim tracking events
// aggregated at the match level (one entry per player per weapon)
func (ep *EventProcessor) GetAimWeaponEvents() []types.WeaponAimAnalysisResult {
	aggregated := ep.aggregateWeaponAimEvents(ep.aimWeaponEvents)

	return aggregated
}

// aggregateWeaponAimEvents aggregates per-round weapon aim data into match-level data
func (ep *EventProcessor) aggregateWeaponAimEvents(roundEvents []types.WeaponAimAnalysisResult) []types.WeaponAimAnalysisResult {
	// Debug: Log input
	if len(roundEvents) > 0 {
	}

	// Group by player + weapon
	type key struct {
		playerID   string
		weaponName string
	}

	aggregated := make(map[key]*types.WeaponAimAnalysisResult)

	for _, event := range roundEvents {
		k := key{
			playerID:   event.PlayerSteamID,
			weaponName: event.WeaponName,
		}

		if existing, exists := aggregated[k]; exists {
			// Aggregate counts
			existing.ShotsFired += event.ShotsFired
			existing.ShotsHit += event.ShotsHit
			existing.SprayingShotsFired += event.SprayingShotsFired
			existing.SprayingShotsHit += event.SprayingShotsHit
			existing.HeadHitsTotal += event.HeadHitsTotal
			existing.UpperChestHitsTotal += event.UpperChestHitsTotal
			existing.ChestHitsTotal += event.ChestHitsTotal
			existing.LegsHitsTotal += event.LegsHitsTotal

			// Recalculate accuracy percentages
			if existing.ShotsFired > 0 {
				existing.AccuracyAllShots = float64(existing.ShotsHit) / float64(existing.ShotsFired) * 100.0
			}
			if existing.SprayingShotsFired > 0 {
				existing.SprayingAccuracy = float64(existing.SprayingShotsHit) / float64(existing.SprayingShotsFired) * 100.0
			}
			if existing.ShotsHit > 0 {
				existing.HeadshotAccuracy = float64(existing.HeadHitsTotal) / float64(existing.ShotsHit) * 100.0
			}

			// Average crosshair placement (weighted by shots fired)
			totalShots := float64(existing.ShotsFired)
			if totalShots > 0 {
				existing.AverageCrosshairPlacementX = (existing.AverageCrosshairPlacementX*(totalShots-float64(event.ShotsFired)) + event.AverageCrosshairPlacementX*float64(event.ShotsFired)) / totalShots
				existing.AverageCrosshairPlacementY = (existing.AverageCrosshairPlacementY*(totalShots-float64(event.ShotsFired)) + event.AverageCrosshairPlacementY*float64(event.ShotsFired)) / totalShots
			}
		} else {
			// Create new aggregated entry (remove round number as it's match-level)
			aggregated[k] = &types.WeaponAimAnalysisResult{
				PlayerSteamID:              event.PlayerSteamID,
				RoundNumber:                0, // Match-level data, no specific round
				WeaponName:                 event.WeaponName,
				WeaponDisplayName:          event.WeaponDisplayName,
				ShotsFired:                 event.ShotsFired,
				ShotsHit:                   event.ShotsHit,
				AccuracyAllShots:           event.AccuracyAllShots,
				SprayingShotsFired:         event.SprayingShotsFired,
				SprayingShotsHit:           event.SprayingShotsHit,
				SprayingAccuracy:           event.SprayingAccuracy,
				AverageCrosshairPlacementX: event.AverageCrosshairPlacementX,
				AverageCrosshairPlacementY: event.AverageCrosshairPlacementY,
				HeadshotAccuracy:           event.HeadshotAccuracy,
				HeadHitsTotal:              event.HeadHitsTotal,
				UpperChestHitsTotal:        event.UpperChestHitsTotal,
				ChestHitsTotal:             event.ChestHitsTotal,
				LegsHitsTotal:              event.LegsHitsTotal,
			}
		}
	}

	// Convert map to slice
	result := make([]types.WeaponAimAnalysisResult, 0, len(aggregated))
	for _, event := range aggregated {
		result = append(result, *event)
	}

	// Debug: Log output
	if len(result) > 0 {
	}

	return result
}
