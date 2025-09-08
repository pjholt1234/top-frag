package client

import (
	"parser-test/internal/types"
)

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
		tc.TestEffectivenessRating,
		tc.TestGrenadeDamage,
		tc.TestMolotovDamage,
	}
}

func (tc *TestClient) TestGrenadeEventsExist(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGrenadeEventsExist")
	ctx := types.NewTestContext("TestGrenadeEventsExist")

	results := testCase.Data("grenade").Get()

	ctx.AssertCount(results, ">", 0)

	return ctx.GetResult()
}

func (tc *TestClient) TestJumpingWThrowType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestJumpingWThrowType")
	ctx := types.NewTestContext("TestJumpingWThrowType")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 101739).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		throwType := results[0].GetField("throw_type")
		ctx.AssertValue(throwType, "=", "Jumping + W")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestStandingThrowType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestStandingThrowType")
	ctx := types.NewTestContext("TestStandingThrowType")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 84105).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		throwType := results[0].GetField("throw_type")
		ctx.AssertValue(throwType, "=", "Standing")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestSideDetection(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestSideDetection")
	ctx := types.NewTestContext("TestSideDetection")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 101739).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		playerSide := results[0].GetField("player_side")
		ctx.AssertValue(playerSide, "=", "T")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestGrenadeRoundTime(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGrenadeRoundTime")
	ctx := types.NewTestContext("TestGrenadeRoundTime")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 84105).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		roundTime := results[0].GetField("round_time")
		ctx.AssertValue(roundTime, "=", 49)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestSmokeGrenadeType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestSmokeGrenadeType")
	ctx := types.NewTestContext("TestSmokeGrenadeType")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 84105).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Smoke Grenade")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestIncendiaryGrenadeType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestIncendiaryGrenadeType")
	ctx := types.NewTestContext("TestIncendiaryGrenadeType")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 49608).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Incendiary Grenade")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestMolotovType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestMolotovType")
	ctx := types.NewTestContext("TestMolotovType")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 32458).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Molotov")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestFlashbangType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestFlashbangType")
	ctx := types.NewTestContext("TestFlashbangType")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198173463029").
		Where("tick_timestamp", "=", 35770).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Flashbang")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestHEGrenadeType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestHEGrenadeType")
	ctx := types.NewTestContext("TestHEGrenadeType")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 32476).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "HE Grenade")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestDecoyGrenadeType(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDecoyGrenadeType")
	ctx := types.NewTestContext("TestDecoyGrenadeType")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 32118).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		grenadeType := results[0].GetField("grenade_type")
		ctx.AssertValue(grenadeType, "=", "Decoy Grenade")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestFlashStats(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestFlashStats")
	ctx := types.NewTestContext("TestFlashStats")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 47458).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		result := results[0]

		friendlyFlashDuration := result.GetField("friendly_flash_duration")
		ctx.AssertValue(friendlyFlashDuration, "=", 2.490239072)

		enemyFlashDuration := result.GetField("enemy_flash_duration")
		ctx.AssertValue(enemyFlashDuration, "=", 9.651062784)

		friendlyPlayersAffected := result.GetField("friendly_players_affected")
		ctx.AssertValue(friendlyPlayersAffected, "=", 2)

		enemyPlayersAffected := result.GetField("enemy_players_affected")
		ctx.AssertValue(enemyPlayersAffected, "=", 3)

		flashLeadsToKill := result.GetField("flash_leads_to_kill")
		ctx.AssertValue(flashLeadsToKill, "=", true)

		flashLeadsToDeath := result.GetField("flash_leads_to_death")
		ctx.AssertValue(flashLeadsToDeath, "=", true)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestPlayerPosition(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestPlayerPosition")
	ctx := types.NewTestContext("TestPlayerPosition")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561199426243273").
		Where("tick_timestamp", "=", 16717).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		result := results[0]

		playerX := result.GetField("player_x")
		ctx.AssertValue(playerX, "=", -328)

		playerY := result.GetField("player_y")
		ctx.AssertValue(playerY, "=", -2288)

		playerZ := result.GetField("player_z")
		ctx.AssertValue(playerZ, "=", -124.34375)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestPlayerAim(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestPlayerAim")
	ctx := types.NewTestContext("TestPlayerAim")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198173463029").
		Where("tick_timestamp", "=", 19358).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		result := results[0]

		playerAimX := result.GetField("player_aim_x")
		ctx.AssertValue(playerAimX, "=", 81.0537109375)

		playerAimY := result.GetField("player_aim_y")
		ctx.AssertValue(playerAimY, ">=", -36.127502441406)
		ctx.AssertValue(playerAimY, "<=", -36.127502441407)

		playerAimZ := result.GetField("player_aim_z")
		ctx.AssertValue(playerAimZ, "=", 0)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestGrenadeFinalPosition(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGrenadeFinalPosition")
	ctx := types.NewTestContext("TestGrenadeFinalPosition")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 101739).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		result := results[0]

		grenadeFinalX := result.GetField("grenade_final_x")
		ctx.AssertValue(grenadeFinalX, "=", -519.625)

		grenadeFinalY := result.GetField("grenade_final_y")
		ctx.AssertValue(grenadeFinalY, "=", 207.9375)

		grenadeFinalZ := result.GetField("grenade_final_z")
		ctx.AssertValue(grenadeFinalZ, "=", 162.15625)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestEffectivenessRating(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestEffectivenessRating")
	ctx := types.NewTestContext("TestEffectivenessRating")

	results := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 3275).
		Get()

	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		effectivenessRating := results[0].GetField("effectiveness_rating")
		ctx.AssertValue(effectivenessRating, "=", 10)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestGrenadeDamage(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGrenadeDamage")
	ctx := types.NewTestContext("TestGrenadeDamage")

	result := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("round_number", "=", 7).
		Where("grenade_type", "=", "HE Grenade").
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("damage_dealt"), ">", 0)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestMolotovDamage(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestMolotovDamage")
	ctx := types.NewTestContext("TestMolotovDamage")

	result := testCase.Data("grenade").
		Where("player_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 2).
		Where("grenade_type", "=", "Incendiary Grenade").
		First()

	ctx.AssertNotNull(result)

	if result != nil {
		ctx.AssertValue(result.GetField("damage_dealt"), ">", 0)
	}

	return ctx.GetResult()
}
