package client

import (
	"parser-test/internal/types"
)

// getGrenadeTests returns all grenade event test functions
func (tc *TestClient) getGrenadeTests() []TestFunction {
	return []TestFunction{
		tc.TestGrenadeEventsExist,
		tc.TestJumpingWThrowType,
		tc.TestStandingThrowType,
		tc.TestSideDetection,
		tc.TestGrenadeRoundTime,
		tc.TestSmokeGrenadeType,
		tc.TestIncendiaryGrenadeType,
		tc.TestMolotovType,
		tc.TestFlashbangType,
		tc.TestHEGrenadeType,
		tc.TestDecoyGrenadeType,
		tc.TestFlashStats,
		tc.TestPlayerPosition,
		tc.TestPlayerAim,
		tc.TestGrenadeFinalPosition,
		tc.TestDamageDealt,
		tc.TestEffectivenessRating,
	}
}

// Basic example test - check if grenade events exist
func (tc *TestClient) TestGrenadeEventsExist(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGrenadeEventsExist")
	ctx := types.NewTestContext("TestGrenadeEventsExist")

	// Get all grenade events
	results := testCase.Data("grenade").Get()

	// Assert that some events exist
	ctx.AssertCount(results, ">", 0)

	return ctx.GetResult()
}

// Test jumping + W throw type detection
func (tc *TestClient) TestJumpingWThrowType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestJumpingWThrowType")
	ctx := types.NewTestContext("TestJumpingWThrowType")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 107008).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert throw_type = "Jumping + W"
		throwType := results[0].GetField("throw_type")
		ctx.AssertValue(throwType, "=", "Jumping + W")
	}

	return ctx.GetResult()
}

// Test standing throw type detection
func (tc *TestClient) TestStandingThrowType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestStandingThrowType")
	ctx := types.NewTestContext("TestStandingThrowType")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 88450).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert throw_type = "Standing"
		throwType := results[0].GetField("throw_type")
		ctx.AssertValue(throwType, "=", "Standing")
	}

	return ctx.GetResult()
}

// Test side detection (T side)
func (tc *TestClient) TestSideDetection(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestSideDetection")
	ctx := types.NewTestContext("TestSideDetection")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 107008).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert player_side = "T"
		playerSide := results[0].GetField("player_side")
		ctx.AssertValue(playerSide, "=", "T")
	}

	return ctx.GetResult()
}

// Test round time for mid round smoke
func (tc *TestClient) TestGrenadeRoundTime(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGrenadeRoundTime")
	ctx := types.NewTestContext("TestGrenadeRoundTime")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 88450).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert round_time = 47
		roundTime := results[0].GetField("round_time")
		ctx.AssertValue(roundTime, "=", 47)
	}

	return ctx.GetResult()
}

// Test smoke grenade type detection
func (tc *TestClient) TestSmokeGrenadeType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestSmokeGrenadeType")
	ctx := types.NewTestContext("TestSmokeGrenadeType")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 88450).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert grenade_type = "Smoke Grenade"
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Smoke Grenade")
	}

	return ctx.GetResult()
}

// Test incendiary grenade type detection
func (tc *TestClient) TestIncendiaryGrenadeType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestIncendiaryGrenadeType")
	ctx := types.NewTestContext("TestIncendiaryGrenadeType")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 34051).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert grenade_type = "Incendiary Grenade"
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Incendiary Grenade")
	}

	return ctx.GetResult()
}

// Test molotov type detection
func (tc *TestClient) TestMolotovType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestMolotovType")
	ctx := types.NewTestContext("TestMolotovType")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 34123).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert grenade_type = "Molotov"
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Molotov")
	}

	return ctx.GetResult()
}

// Test flashbang type detection
func (tc *TestClient) TestFlashbangType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestFlashbangType")
	ctx := types.NewTestContext("TestFlashbangType")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198173463029").
		Where("tick_timestamp", "=", 37704).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert grenade_type = "Flashbang"
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Flashbang")
	}

	return ctx.GetResult()
}

// Test HE grenade type detection
func (tc *TestClient) TestHEGrenadeType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestHEGrenadeType")
	ctx := types.NewTestContext("TestHEGrenadeType")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 34143).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert grenade_type = "HE Grenade"
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "HE Grenade")
	}

	return ctx.GetResult()
}

// Test decoy grenade type detection
func (tc *TestClient) TestDecoyGrenadeType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDecoyGrenadeType")
	ctx := types.NewTestContext("TestDecoyGrenadeType")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 33767).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert grenade_type = "Decoy Grenade"
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Decoy Grenade")
	}

	return ctx.GetResult()
}

// Test flash stats (duration, affected players, leads to kill/death)
func (tc *TestClient) TestFlashStats(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestFlashStats")
	ctx := types.NewTestContext("TestFlashStats")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 47458).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		result := results[0]

		// Assert friendly_flash_duration = 2.490239072
		friendlyFlashDuration := result.GetField("friendly_flash_duration")
		ctx.AssertValue(friendlyFlashDuration, "=", 2.490239072)

		// Assert enemy_flash_duration = 9.651062784
		enemyFlashDuration := result.GetField("enemy_flash_duration")
		ctx.AssertValue(enemyFlashDuration, "=", 9.651062784)

		// Assert friendly_players_affected = 2
		friendlyPlayersAffected := result.GetField("friendly_players_affected")
		ctx.AssertValue(friendlyPlayersAffected, "=", 2)

		// Assert enemy_players_affected = 3
		enemyPlayersAffected := result.GetField("enemy_players_affected")
		ctx.AssertValue(enemyPlayersAffected, "=", 3)

		// Assert flash_leads_to_kill = true
		flashLeadsToKill := result.GetField("flash_leads_to_kill")
		ctx.AssertValue(flashLeadsToKill, "=", true)

		// Assert flash_leads_to_death = true
		flashLeadsToDeath := result.GetField("flash_leads_to_death")
		ctx.AssertValue(flashLeadsToDeath, "=", true)
	}

	return ctx.GetResult()
}

// Test player position (x, y, z coordinates)
func (tc *TestClient) TestPlayerPosition(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestPlayerPosition")
	ctx := types.NewTestContext("TestPlayerPosition")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 107008).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		result := results[0]

		// Assert player_x is approximately -328.35876464844 (within 0.001)
		playerX := result.GetField("player_x")
		ctx.AssertValue(playerX, ">=", -328.36)
		ctx.AssertValue(playerX, "<=", -328.35)

		// Assert player_y is approximately -2282.908203125 (within 0.001)
		playerY := result.GetField("player_y")
		ctx.AssertValue(playerY, ">=", -2282.91)
		ctx.AssertValue(playerY, "<=", -2282.90)

		// Assert player_z is approximately -123.984375 (within 0.001)
		playerZ := result.GetField("player_z")
		ctx.AssertValue(playerZ, ">=", -123.99)
		ctx.AssertValue(playerZ, "<=", -123.98)
	}

	return ctx.GetResult()
}

// Test player aim (aim_x, aim_y, aim_z)
func (tc *TestClient) TestPlayerAim(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestPlayerAim")
	ctx := types.NewTestContext("TestPlayerAim")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 107008).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		result := results[0]

		// Assert player_aim_x is approximately 94.009674072266 (within 0.001)
		playerAimX := result.GetField("player_aim_x")
		ctx.AssertValue(playerAimX, ">=", 94.008)
		ctx.AssertValue(playerAimX, "<=", 94.011)

		// Assert player_aim_y is approximately -31.746032714844 (within 0.001)
		playerAimY := result.GetField("player_aim_y")
		ctx.AssertValue(playerAimY, ">=", -31.747)
		ctx.AssertValue(playerAimY, "<=", -31.745)

		// Assert player_aim_z = 0
		playerAimZ := result.GetField("player_aim_z")
		ctx.AssertValue(playerAimZ, "=", 0)
	}

	return ctx.GetResult()
}

// Test grenade final position
func (tc *TestClient) TestGrenadeFinalPosition(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGrenadeFinalPosition")
	ctx := types.NewTestContext("TestGrenadeFinalPosition")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 107008).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		result := results[0]

		// Assert grenade_final_x = -519.625
		grenadeFinalX := result.GetField("grenade_final_x")
		ctx.AssertValue(grenadeFinalX, "=", -519.625)

		// Assert grenade_final_y = 207.9375
		grenadeFinalY := result.GetField("grenade_final_y")
		ctx.AssertValue(grenadeFinalY, "=", 207.9375)

		// Assert grenade_final_z = 162.15625
		grenadeFinalZ := result.GetField("grenade_final_z")
		ctx.AssertValue(grenadeFinalZ, "=", 162.15625)
	}

	return ctx.GetResult()
}

// Test damage dealt (HE or Molotov damage)
// NOTE: This test is expected to fail as damage_dealt field may not be implemented yet
func (tc *TestClient) TestDamageDealt(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageDealt")
	ctx := types.NewTestContext("TestDamageDealt")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 3275).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert damage_dealt = 10 (EXPECTED TO FAIL - field not implemented)
		damageDealt := results[0].GetField("damage_dealt")
		ctx.AssertValue(damageDealt, "=", 10)
	}

	return ctx.GetResult()
}

// Test effectiveness rating
// NOTE: This test is expected to fail as effectiveness_rating field may not be implemented yet
func (tc *TestClient) TestEffectivenessRating(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestEffectivenessRating")
	ctx := types.NewTestContext("TestEffectivenessRating")

	// Get grenade event with specific steam ID and tick timestamp
	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 3275).
		Get()

	// Assert results = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Assert effectiveness_rating = 10 (EXPECTED TO FAIL - field not implemented)
		effectivenessRating := results[0].GetField("effectiveness_rating")
		ctx.AssertValue(effectivenessRating, "=", 10)
	}

	return ctx.GetResult()
}
