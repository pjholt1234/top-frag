package client

import (
	"parser-test/internal/types"
)

// getGunfightTests returns all gunfight event test functions
func (tc *TestClient) getGunfightTests() []TestFunction {
	return []TestFunction{
		tc.TestGunfightEventsExist,
		tc.TestGunfightCountForPlayer,
		tc.TestGunfightRoundNumber,
		tc.TestGunfightRoundTime,
		tc.TestGunfightTickTimestamp,
		tc.TestGunfightPlayerSides,
		tc.TestGunfightPlayerSide,
		tc.TestGunfightPlayerHP,
		tc.TestGunfightPlayerArmor,
		tc.TestGunfightPlayersFlashed,
		tc.TestGunfightPlayerWeapons,
		tc.TestGunfightPlayerEquipmentValues,
		tc.TestGunfightPlayer1And2Ids,
		tc.TestGunfightPlayer1And2PositionAndDistance,
		tc.TestGunfightWasHeadshot,
		tc.TestGunfightWasNotHeadshot,
		tc.TestGunfightWasNotWallbang,
		tc.TestGunfightWasWallbang,
		tc.TestGunfightVictor,
		tc.TestGunfightFlashAssisterSteamId,
		tc.TestGunfightHasAssist,
		tc.TestGunfightHasNoAssist,
		tc.TestGunfightRoundScenario,
		tc.TestGunfightRoundScenario2WithFirstKill,
		tc.TestGunfightIsFirstKill,
		tc.TestGunfightIsNotFirstKill,
		tc.TestGunfightDamageDealt,
	}
}

// Basic example test - check if gunfight events exist
func (tc *TestClient) TestGunfightEventsExist(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightEventsExist")
	ctx := types.NewTestContext("TestGunfightEventsExist")
	// Get all gunfight events
	results := testCase.Data("gunfight").Get()

	// Assert that some events exist
	ctx.AssertCount(results, ">", 0)

	return ctx.GetResult()
}

// TestGunfightCountForPlayer - Count for player steam_76561198081165057 should be 20
func (tc *TestClient) TestGunfightCountForPlayer(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightCountForPlayer")
	ctx := types.NewTestContext("TestGunfightCountForPlayer")

	// Count events where player is either player_1 or player_2
	results1 := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Get()

	results2 := testCase.Data("gunfight").
		Where("player_2_steam_id", "=", "steam_76561198081165057").
		Get()

	totalCount := len(results1) + len(results2)
	ctx.AssertValue(totalCount, "=", 20)

	return ctx.GetResult()
}

// TestGunfightRoundNumber - Check round number 2 exists for player
func (tc *TestClient) TestGunfightRoundNumber(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightRoundNumber")
	ctx := types.NewTestContext("TestGunfightRoundNumber")

	results := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 2).
		Get()

	ctx.AssertExists(results)

	return ctx.GetResult()
}

// TestGunfightRoundTime - Check round time 10 exists for player
func (tc *TestClient) TestGunfightRoundTime(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightRoundTime")
	ctx := types.NewTestContext("TestGunfightRoundTime")

	results := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Where("round_time", "=", 54).
		Get()

	ctx.AssertExists(results)

	return ctx.GetResult()
}

// TestGunfightTickTimestamp - Check tick timestamp 7008 exists for player
func (tc *TestClient) TestGunfightTickTimestamp(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightTickTimestamp")
	ctx := types.NewTestContext("TestGunfightTickTimestamp")

	results := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Where("tick_timestamp", "=", 46515).
		Get()

	ctx.AssertExists(results)

	return ctx.GetResult()
}

// TestGunfightPlayerSides - Check that player_1_side is never equal to player_2_side
func (tc *TestClient) TestGunfightPlayerSides(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayerSides")
	ctx := types.NewTestContext("TestGunfightPlayerSides")

	results := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Get()

	// Check that we have results
	ctx.AssertCount(results, ">", 0)

	// Loop over and check that player_1_side is never equal to player_2_side
	for _, result := range results {
		player1Side := result.GetField("player_1_side")
		player2Side := result.GetField("player_2_side")

		if player1Side != nil && player2Side != nil {
			ctx.AssertValue(player1Side, "!=", player2Side)
		}
	}

	return ctx.GetResult()
}

// TestGunfightPlayerSide - Check player_1_side = CT for specific tick
func (tc *TestClient) TestGunfightPlayerSide(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayerSide")
	ctx := types.NewTestContext("TestGunfightPlayerSide")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Where("tick_timestamp", "=", 46515).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("player_1_side"), "=", "CT")
	}

	return ctx.GetResult()
}

// TestGunfightPlayerHP - Check player HP values
func (tc *TestClient) TestGunfightPlayerHP(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayerHP")
	ctx := types.NewTestContext("TestGunfightPlayerHP")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Where("tick_timestamp", "=", 6641).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("player_1_hp_start"), "=", 100)
		ctx.AssertValue(result.GetField("player_2_hp_start"), "=", 74)
	}

	return ctx.GetResult()
}

// TestGunfightPlayerArmor - Check player armor values
func (tc *TestClient) TestGunfightPlayerArmor(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayerArmor")
	ctx := types.NewTestContext("TestGunfightPlayerArmor")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Where("tick_timestamp", "=", 6641).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("player_1_armor"), "=", 100)
		ctx.AssertValue(result.GetField("player_2_armor"), "=", 100)
	}

	return ctx.GetResult()
}

// TestGunfightPlayersFlashed - Check players flashed status
func (tc *TestClient) TestGunfightPlayersFlashed(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayersFlashed")
	ctx := types.NewTestContext("TestGunfightPlayersFlashed")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 40786).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("player_1_flashed"), "=", true)
		ctx.AssertValue(result.GetField("player_2_flashed"), "=", true)
	}

	return ctx.GetResult()
}

// TestGunfightPlayerWeapons - Check player weapons
func (tc *TestClient) TestGunfightPlayerWeapons(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayerWeapons")
	ctx := types.NewTestContext("TestGunfightPlayerWeapons")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198288628308").
		Where("tick_timestamp", "=", 20655).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("player_1_weapon"), "=", "M4A4")
		ctx.AssertValue(result.GetField("player_2_weapon"), "=", "Galil AR")
	}

	return ctx.GetResult()
}

// TestGunfightPlayerEquipmentValues - Check player equipment values
func (tc *TestClient) TestGunfightPlayerEquipmentValues(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayerEquipmentValues")
	ctx := types.NewTestContext("TestGunfightPlayerEquipmentValues")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198081165057").
		Where("tick_timestamp", "=", 6641).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("player_1_equipment_value"), "=", 1800)
		ctx.AssertValue(result.GetField("player_2_equipment_value"), "=", 3350)
	}

	return ctx.GetResult()
}

// TestGunfightPlayer1And2Ids - Check player 1 and 2 steam IDs
func (tc *TestClient) TestGunfightPlayer1And2Ids(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayer1And2Ids")
	ctx := types.NewTestContext("TestGunfightPlayer1And2Ids")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 40786).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("player_2_steam_id"), "=", "steam_76561198105596992")
	}

	return ctx.GetResult()
}

// TestGunfightPlayer1And2PositionAndDistance - Check player positions and distance
func (tc *TestClient) TestGunfightPlayer1And2PositionAndDistance(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightPlayer1And2PositionAndDistance")
	ctx := types.NewTestContext("TestGunfightPlayer1And2PositionAndDistance")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561199084124069").
		Where("tick_timestamp", "=", 61610).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		// Use approximate comparisons for floating point precision
		ctx.AssertValue(result.GetField("player_1_x"), ">=", 1152.0)
		ctx.AssertValue(result.GetField("player_1_x"), "<=", 1153.0)
		ctx.AssertValue(result.GetField("player_1_y"), ">=", -97.0)
		ctx.AssertValue(result.GetField("player_1_y"), "<=", -95.0)
		ctx.AssertValue(result.GetField("player_1_z"), ">=", 131.0)
		ctx.AssertValue(result.GetField("player_1_z"), "<=", 133.0)
		ctx.AssertValue(result.GetField("player_2_x"), ">=", 1188.0)
		ctx.AssertValue(result.GetField("player_2_x"), "<=", 1190.0)
		ctx.AssertValue(result.GetField("player_2_y"), ">=", -195.0)
		ctx.AssertValue(result.GetField("player_2_y"), "<=", -192.0)
		ctx.AssertValue(result.GetField("player_2_z"), ">=", 117.0)
		ctx.AssertValue(result.GetField("player_2_z"), "<=", 119.0)
		ctx.AssertValue(result.GetField("distance"), ">=", 104.0)
		ctx.AssertValue(result.GetField("distance"), "<=", 106.0)
	}

	return ctx.GetResult()
}

// TestGunfightWasHeadshot - Check headshot = true
func (tc *TestClient) TestGunfightWasHeadshot(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightWasHeadshot")
	ctx := types.NewTestContext("TestGunfightWasHeadshot")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561199442474887").
		Where("tick_timestamp", "=", 85263).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("headshot"), "=", true)
	}

	return ctx.GetResult()
}

// TestGunfightWasNotHeadshot - Check headshot = false
func (tc *TestClient) TestGunfightWasNotHeadshot(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightWasNotHeadshot")
	ctx := types.NewTestContext("TestGunfightWasNotHeadshot")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 82591).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("headshot"), "=", false)
	}

	return ctx.GetResult()
}

// TestGunfightWasNotWallbang - Check wallbang = false and penetrated_objects = 0
func (tc *TestClient) TestGunfightWasNotWallbang(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightWasNotWallbang")
	ctx := types.NewTestContext("TestGunfightWasNotWallbang")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198090208424").
		Where("tick_timestamp", "=", 82591).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("wallbang"), "=", false)
		ctx.AssertValue(result.GetField("penetrated_objects"), "=", 0)
	}

	return ctx.GetResult()
}

// TestGunfightWasWallbang - Check wallbang = true and penetrated_objects > 0
func (tc *TestClient) TestGunfightWasWallbang(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightWasWallbang")
	ctx := types.NewTestContext("TestGunfightWasWallbang")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198180752157").
		Where("tick_timestamp", "=", 27348).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("wallbang"), "=", true)
		ctx.AssertValue(result.GetField("penetrated_objects"), ">", 0)
	}

	return ctx.GetResult()
}

// TestGunfightVictor - Check victor steam ID
func (tc *TestClient) TestGunfightVictor(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightVictor")
	ctx := types.NewTestContext("TestGunfightVictor")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198180752157").
		Where("tick_timestamp", "=", 27348).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("victor_steam_id"), "=", "steam_76561198180752157")
	}

	return ctx.GetResult()
}

// TestGunfightFlashAssisterSteamId - Check flash assister steam ID
func (tc *TestClient) TestGunfightFlashAssisterSteamId(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightFlashAssisterSteamId")
	ctx := types.NewTestContext("TestGunfightFlashAssisterSteamId")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561199442474887").
		Where("tick_timestamp", "=", 45275).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("flash_assister_steam_id"), "=", "steam_76561198288628308")
	}

	return ctx.GetResult()
}

// TestGunfightHasAssist - Check damage assist steam ID exists
func (tc *TestClient) TestGunfightHasAssist(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightHasAssist")
	ctx := types.NewTestContext("TestGunfightHasAssist")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198180752157").
		Where("tick_timestamp", "=", 71537).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("damage_assist_steam_id"), "=", "steam_76561198090208424")
	}

	return ctx.GetResult()
}

// TestGunfightHasNoAssist - Check damage assist steam ID is null
func (tc *TestClient) TestGunfightHasNoAssist(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightHasNoAssist")
	ctx := types.NewTestContext("TestGunfightHasNoAssist")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561199426243273").
		Where("tick_timestamp", "=", 87107).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		// Check that damage_assist_steam_id is not null (it has a value)
		ctx.AssertValue(result.GetField("damage_assist_steam_id"), "==", nil)
	}

	return ctx.GetResult()
}

// TestGunfightRoundScenario - Check round scenario
func (tc *TestClient) TestGunfightRoundScenario(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightRoundScenario")
	ctx := types.NewTestContext("TestGunfightRoundScenario")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198180752157").
		Where("tick_timestamp", "=", 27348).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("round_scenario"), "=", "2v1")
	}

	return ctx.GetResult()
}

// TestGunfightRoundScenario2WithFirstKill - Check round scenario and first kill
func (tc *TestClient) TestGunfightRoundScenario2WithFirstKill(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightRoundScenario2WithFirstKill")
	ctx := types.NewTestContext("TestGunfightRoundScenario2WithFirstKill")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198180752157").
		Where("tick_timestamp", "=", 71537).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("round_scenario"), "=", "5v5")
		ctx.AssertValue(result.GetField("is_first_kill"), "=", true)
	}

	return ctx.GetResult()
}

// TestGunfightIsFirstKill - Check is first kill = true
func (tc *TestClient) TestGunfightIsFirstKill(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightIsFirstKill")
	ctx := types.NewTestContext("TestGunfightIsFirstKill")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198180752157").
		Where("tick_timestamp", "=", 71537).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("is_first_kill"), "=", true)
	}

	return ctx.GetResult()
}

// TestGunfightIsNotFirstKill - Check is first kill = false
func (tc *TestClient) TestGunfightIsNotFirstKill(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightIsNotFirstKill")
	ctx := types.NewTestContext("TestGunfightIsNotFirstKill")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198105596992").
		Where("tick_timestamp", "=", 84941).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("is_first_kill"), "=", false)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestGunfightDamageDealt(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGunfightDamageDealt")
	ctx := types.NewTestContext("TestGunfightDamageDealt")

	result := testCase.Data("gunfight").
		Where("player_1_steam_id", "=", "steam_76561198105596992").
		Where("tick_timestamp", "=", 84941).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("damage_dealt"), "=", 51)
	}

	return ctx.GetResult()
}
