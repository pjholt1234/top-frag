package parser

import (
	"parser-service/internal/types"
	"testing"

	"github.com/sirupsen/logrus"
)

func TestRoundHandler_aggregateGunfightMetrics_ExcludesTeamDamage(t *testing.T) {
	// Create a mock processor with team assignments
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound:   1,
			GunfightEvents: []types.GunfightEvent{},
			DamageEvents: []types.DamageEvent{
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player2", // Enemy
					HealthDamage:    50,
				},
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player3", // Teammate
					HealthDamage:    30,
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
			"player2": "B", // Enemy
			"player3": "A", // Teammate
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGunfightMetrics(event, "player1", 1)

	// Verify that only enemy damage is counted (50, not 80)
	if event.Damage != 50 {
		t.Errorf("Expected damage to be 50 (enemy damage only), got %d", event.Damage)
	}
}

func TestRoundHandler_aggregateGrenadeMetrics_CountsGrenadeTypes(t *testing.T) {
	// Create a mock processor with grenade events
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound: 1,
			GrenadeEvents: []types.GrenadeEvent{
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeFlash,
					DamageDealt:         0,
					EffectivenessRating: 5,
				},
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeHE,
					DamageDealt:         50,
					EffectivenessRating: 8,
				},
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeSmoke,
					DamageDealt:         0,
					EffectivenessRating: 0,
				},
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeMolotov,
					DamageDealt:         25,
					EffectivenessRating: 6,
				},
				{
					RoundNumber:         1,
					PlayerSteamID:       "player1",
					GrenadeType:         types.GrenadeTypeDecoy,
					DamageDealt:         0,
					EffectivenessRating: 0,
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGrenadeMetrics(event, "player1", 1)

	// Verify grenade counts
	if event.FlashesThrown != 1 {
		t.Errorf("Expected flashes thrown to be 1, got %d", event.FlashesThrown)
	}
	if event.HesThrown != 1 {
		t.Errorf("Expected HE grenades thrown to be 1, got %d", event.HesThrown)
	}
	if event.SmokesThrown != 1 {
		t.Errorf("Expected smokes thrown to be 1, got %d", event.SmokesThrown)
	}
	if event.FireGrenadesThrown != 1 {
		t.Errorf("Expected fire grenades thrown to be 1, got %d", event.FireGrenadesThrown)
	}
	if event.DecoysThrown != 1 {
		t.Errorf("Expected decoys thrown to be 1, got %d", event.DecoysThrown)
	}
	if event.DamageDealt != 75 {
		t.Errorf("Expected damage dealt to be 75 (50 HE + 25 Molotov), got %d", event.DamageDealt)
	}
}

func TestRoundHandler_aggregateGunfightMetrics_IncludesEnemyDamage(t *testing.T) {
	// Create a mock processor with team assignments
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound:   1,
			GunfightEvents: []types.GunfightEvent{},
			DamageEvents: []types.DamageEvent{
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player2", // Enemy
					HealthDamage:    75,
				},
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player4", // Another enemy
					HealthDamage:    25,
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
			"player2": "B", // Enemy
			"player4": "B", // Another enemy
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGunfightMetrics(event, "player1", 1)

	// Verify that all enemy damage is counted (75 + 25 = 100)
	if event.Damage != 100 {
		t.Errorf("Expected damage to be 100 (all enemy damage), got %d", event.Damage)
	}
}

func TestRoundHandler_aggregateGunfightMetrics_HandlesMissingTeamAssignment(t *testing.T) {
	// Create a mock processor with incomplete team assignments
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound:   1,
			GunfightEvents: []types.GunfightEvent{},
			DamageEvents: []types.DamageEvent{
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player2", // No team assignment
					HealthDamage:    50,
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
			// player2 not assigned (will default to "A")
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGunfightMetrics(event, "player1", 1)

	// Verify that damage is not counted when teams are the same (both default to "A")
	if event.Damage != 0 {
		t.Errorf("Expected damage to be 0 (same team due to default), got %d", event.Damage)
	}
}

func TestRoundHandler_aggregateGunfightMetrics_NoDoubleCounting(t *testing.T) {
	// Create a mock processor with gunfight and damage events for the same kill
	processor := &EventProcessor{
		matchState: &types.MatchState{
			CurrentRound: 1,
			GunfightEvents: []types.GunfightEvent{
				{
					RoundNumber:    1,
					Player1SteamID: "player1",
					Player2SteamID: "player2",
					VictorSteamID:  &[]string{"player1"}[0],
					DamageDealt:    100, // This should NOT be counted
					Player2HPStart: 0,   // Victim had 0 HP when killed
				},
			},
			DamageEvents: []types.DamageEvent{
				{
					RoundNumber:     1,
					AttackerSteamID: "player1",
					VictimSteamID:   "player2",
					HealthDamage:    75, // This should be counted
				},
			},
		},
		teamAssignments: map[string]string{
			"player1": "A",
			"player2": "B", // Enemy
		},
	}

	// Create round handler
	rh := &RoundHandler{
		processor: processor,
		logger:    logrus.New(),
	}

	// Create player round event
	event := &types.PlayerRoundEvent{
		PlayerSteamID: "player1",
		RoundNumber:   1,
	}

	// Call the method
	rh.aggregateGunfightMetrics(event, "player1", 1)

	// Verify that only the damage event is counted (75), not the gunfight damage (100)
	if event.Damage != 75 {
		t.Errorf("Expected damage to be 75 (damage events only), got %d", event.Damage)
	}

	// Verify that the kill is still counted
	if event.Kills != 1 {
		t.Errorf("Expected kills to be 1, got %d", event.Kills)
	}
}
