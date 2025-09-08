package client

import (
	"fmt"
	"parser-test/internal/types"
)

// getPlayerRoundTests returns all player round event test functions
func (tc *TestClient) getPlayerRoundTests() []TestFunction {
	return []TestFunction{
		tc.TestPlayerRoundEventsExist,
		tc.TestTotalRoundsConsistency,
		tc.TestKillsValidation,
		tc.TestAssistsValidation,
		tc.TestDiedValidation,
		tc.TestDamageValidation,
		tc.TestHeadshotsValidation,
		tc.TestFirstDeathValidation,
		tc.TestFirstKillValidation,
		tc.TestRoundTimeOfDeathValidation,
		tc.TestAWPKillsValidation,
		tc.TestDamageDealtValidation,
		tc.TestFlashStatsValidation,
		tc.TestTradeKillStatsValidation,
		tc.TestTradeDeathStatsValidation,
		tc.TestClutchAttemptsValidation,
		tc.TestTimeToContactValidation,
		tc.TestBuyRoundValidations,
		tc.TestGrenadeValueLostValidation,
	}
}

// Basic example test - check if player round events exist
func (tc *TestClient) TestPlayerRoundEventsExist(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestPlayerRoundEventsExist")
	ctx := types.NewTestContext("TestPlayerRoundEventsExist")

	// Get all player round events
	results := testCase.Data("player-round").Get()
	ctx.AssertExists(results)

	return ctx.GetResult()
}

// TestTotalRoundsConsistency - Total number of rounds should be the same for each unique player_steam_id (should all equal 20)
//
//	func (tc *TestClient) TestTotalRoundsConsistency(client *TestClient) *types.AssertionResult {
//		testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestTotalRoundsConsistency")
//		ctx := types.NewTestContext("TestTotalRoundsConsistency")
func (tc *TestClient) TestTotalRoundsConsistency(client *TestClient) *types.AssertionResult {
	// Get all player round events
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestTotalRoundsConsistency")
	ctx := types.NewTestContext("TestTotalRoundsConsistency")

	results := testCase.Data("player-round").Get()
	ctx.AssertExists(results)

	if len(results) > 0 {
		// Group by player_steam_id and count rounds
		playerRoundCounts := make(map[string]int)
		for _, result := range results {
			playerSteamID := result.GetString("player_steam_id")
			playerRoundCounts[playerSteamID]++
		}

		// Check that all players have exactly 20 rounds
		expectedRounds := 20
		for playerSteamID, count := range playerRoundCounts {
			ctx.AssertValue(count, "=", expectedRounds)
			if count != expectedRounds {
				ctx.AssertValue(fmt.Sprintf("Player %s has %d rounds, expected %d", playerSteamID, count, expectedRounds), "=", "consistency check")
			}
		}
	}

	return ctx.GetResult()
}

// TestKillsValidation - Kills can never exceed 5, and specific player assertions
func (tc *TestClient) TestKillsValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestKillsValidation")
	ctx := types.NewTestContext("TestKillsValidation")

	// Test specific player kills
	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198105596992").
		Where("round_number", "=", 1).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("kills"), "=", 1)
	}

	// Test that kills never exceed 5 across all records
	allResults := testCase.Data("player-round").Get()
	for _, result := range allResults {
		kills := result.GetInt("kills")
		ctx.AssertValue(kills, "<=", 5)
	}

	return ctx.GetResult()
}

// TestAssistsValidation - Test assists for specific player
func (tc *TestClient) TestAssistsValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestAssistsValidation")
	ctx := types.NewTestContext("TestAssistsValidation")

	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561199426243273").
		Where("round_number", "=", 1).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("assists"), "=", 1)
	}

	return ctx.GetResult()
}

// TestDiedValidation - Test died field for specific player
func (tc *TestClient) TestDiedValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDiedValidation")
	ctx := types.NewTestContext("TestDiedValidation")

	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("round_number", "=", 1).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("died"), "=", true)
	}

	return ctx.GetResult()
}

// TestDamageValidation - Test damage for specific player and validate damage never exceeds 500
func (tc *TestClient) TestDamageValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageValidation")
	ctx := types.NewTestContext("TestDamageValidation")

	// Test specific player damage
	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("round_number", "=", 1).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("damage"), "=", 267)
	}

	// Test that damage never exceeds 500 across all records (this will fail as expected)
	allResults := testCase.Data("player-round").Get()
	for _, result := range allResults {
		damage := result.GetInt("damage")
		ctx.AssertValue(damage, "<=", 500)
	}

	return ctx.GetResult()
}

// TestHeadshotsValidation - Test headshots for specific player and validate headshots never exceed kills
func (tc *TestClient) TestHeadshotsValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestHeadshotsValidation")
	ctx := types.NewTestContext("TestHeadshotsValidation")

	// Test specific player headshots
	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("round_number", "=", 2).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("headshots"), "=", 1)
	}

	// Test that headshots never exceed kills across all records
	allResults := testCase.Data("player-round").Get()
	for _, result := range allResults {
		headshots := result.GetInt("headshots")
		kills := result.GetInt("kills")
		ctx.AssertValue(headshots, "<=", kills)
	}

	return ctx.GetResult()
}

// TestFirstDeathValidation - Test first death logic per round
func (tc *TestClient) TestFirstDeathValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestFirstDeathValidation")
	ctx := types.NewTestContext("TestFirstDeathValidation")

	// Test specific player first death
	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 1).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("first_death"), "=", true)
		ctx.AssertValue(result.GetField("round_time_of_death") != nil, "=", true)
	}

	// Test that each round has exactly 1 first death
	allResults := testCase.Data("player-round").Get()
	roundFirstDeaths := make(map[int]int)
	for _, result := range allResults {
		roundNumber := result.GetInt("round_number")
		if result.GetBool("first_death") {
			roundFirstDeaths[roundNumber]++
		}
	}

	for roundNumber, count := range roundFirstDeaths {
		ctx.AssertValue(count, "=", 1)
		if count != 1 {
			ctx.AssertValue(fmt.Sprintf("Round %d has %d first deaths, expected 1", roundNumber, count), "=", "first death check")
		}
	}

	return ctx.GetResult()
}

// TestFirstKillValidation - Test first kill logic per round
func (tc *TestClient) TestFirstKillValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestFirstKillValidation")
	ctx := types.NewTestContext("TestFirstKillValidation")

	// Test that each round has exactly 1 first kill
	allResults := testCase.Data("player-round").Get()
	roundFirstKills := make(map[int]int)
	for _, result := range allResults {
		roundNumber := result.GetInt("round_number")
		if result.GetBool("first_kill") {
			roundFirstKills[roundNumber]++
		}
	}

	for roundNumber, count := range roundFirstKills {
		ctx.AssertValue(count, "=", 1)
		if count != 1 {
			ctx.AssertValue(fmt.Sprintf("Round %d has %d first kills, expected 1", roundNumber, count), "=", "first kill check")
		}
	}

	return ctx.GetResult()
}

// TestRoundTimeOfDeathValidation - Test round time of death for specific player
func (tc *TestClient) TestRoundTimeOfDeathValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestRoundTimeOfDeathValidation")
	ctx := types.NewTestContext("TestRoundTimeOfDeathValidation")

	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 1).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		roundTimeOfDeath := result.GetFloat("round_time_of_death")
		ctx.AssertValue(roundTimeOfDeath, "=", 24.0)
	}

	return ctx.GetResult()
}

// TestAWPKillsValidation - Test AWP kills for specific player and validate AWP kills never exceed total kills
func (tc *TestClient) TestAWPKillsValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestAWPKillsValidation")
	ctx := types.NewTestContext("TestAWPKillsValidation")

	// Test specific player AWP kills
	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198180752157").
		Where("round_number", "=", 6).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("kills_with_awp"), "=", 2)
	}

	// Test that AWP kills never exceed total kills across all records
	allResults := testCase.Data("player-round").Get()
	for _, result := range allResults {
		awpKills := result.GetInt("kills_with_awp")
		totalKills := result.GetInt("kills")
		ctx.AssertValue(awpKills, "<=", totalKills)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestDamageDealtValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageDealtValidation")
	ctx := types.NewTestContext("TestDamageDealtValidation")

	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 9).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("damage_dealt"), "=", 138)
	}

	return ctx.GetResult()
}

// TestFlashStatsValidation - Test detailed flash stats for specific player (this will fail as expected)
func (tc *TestClient) TestFlashStatsValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestFlashStatsValidation")
	ctx := types.NewTestContext("TestFlashStatsValidation")

	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198288628308").
		Where("round_number", "=", 12).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("flashes_thrown"), "!=", 0)
		ctx.AssertValue(result.GetField("friendly_flash_duration"), "=", 3.264)
		ctx.AssertValue(result.GetField("enemy_flash_duration"), "=", 14.122)
		ctx.AssertValue(result.GetField("flashes_leading_to_kill"), "=", 1)
		ctx.AssertValue(result.GetField("enemy_players_affected"), "=", 4)
		ctx.AssertValue(result.GetField("flashes_leading_to_death"), "=", 0)
		ctx.AssertValue(result.GetField("grenade_effectiveness"), "=", 1)
	}

	return ctx.GetResult()
}

// TestTradeKillStatsValidation - Test trade kill stats for specific player
func (tc *TestClient) TestTradeKillStatsValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestTradeKillStatsValidation")
	ctx := types.NewTestContext("TestTradeKillStatsValidation")

	// Test specific player trade kill stats
	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561199084124069").
		Where("round_number", "=", 4).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("successful_trades"), "=", 2)
		ctx.AssertValue(result.GetField("total_possible_trades"), "=", 3)
	}

	// Test that successful trades never exceed total possible trades across all records
	allResults := testCase.Data("player-round").Get()
	for _, result := range allResults {
		successfulTrades := result.GetInt("successful_trades")
		totalPossibleTrades := result.GetInt("total_possible_trades")
		ctx.AssertValue(successfulTrades, "<=", totalPossibleTrades)
	}

	return ctx.GetResult()
}

// TestTradeDeathStatsValidation - Test trade death stats for specific player
func (tc *TestClient) TestTradeDeathStatsValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestTradeDeathStatsValidation")
	ctx := types.NewTestContext("TestTradeDeathStatsValidation")

	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561199442474887").
		Where("round_number", "=", 16).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("successful_traded_deaths"), "=", 1)
		ctx.AssertValue(result.GetField("total_possible_traded_deaths"), "=", 1)
	}

	return ctx.GetResult()
}

// TestClutchAttemptsValidation - Test clutch attempts for specific player and validate clutch wins never exceed attempts
func (tc *TestClient) TestClutchAttemptsValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestClutchAttemptsValidation")
	ctx := types.NewTestContext("TestClutchAttemptsValidation")

	// Test specific player clutch attempts
	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561199426243273").
		Where("round_number", "=", 17).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("clutch_attempts_1v1"), "=", 1)
		ctx.AssertValue(result.GetField("clutch_wins_1v1"), "=", 1)
	}

	// Test that clutch wins never exceed clutch attempts across all records (1v1 to 1v5)
	allResults := testCase.Data("player-round").Get()
	for _, result := range allResults {
		for i := 1; i <= 5; i++ {
			attemptsField := fmt.Sprintf("clutch_attempts_1v%d", i)
			winsField := fmt.Sprintf("clutch_wins_1v%d", i)

			attempts := result.GetInt(attemptsField)
			wins := result.GetInt(winsField)
			ctx.AssertValue(wins, "<=", attempts)
		}
	}

	return ctx.GetResult()
}

// TestTimeToContactValidation - Test time to contact for specific player
func (tc *TestClient) TestTimeToContactValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestTimeToContactValidation")
	ctx := types.NewTestContext("TestTimeToContactValidation")

	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198105596992").
		Where("round_number", "=", 17).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("time_to_contact"), "=", 62.000)
	}

	return ctx.GetResult()
}

// TestBuyRoundValidations - Test buy round logic for rounds 1 and 13, and specific eco/force buy rounds
func (tc *TestClient) TestBuyRoundValidations(client *TestClient) *types.AssertionResult {
	ctx := types.NewTestContext("TestBuyRoundValidations")

	// Test that round 1 is always a full buy
	round1TestCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestBuyRoundValidations")
	round1Results := round1TestCase.Data("player-round").
		Where("round_number", "=", 1).
		Get()

	for _, result := range round1Results {
		ctx.AssertValue(result.GetField("is_full_buy"), "=", true)
		ctx.AssertValue(result.GetField("is_eco"), "=", false)
		ctx.AssertValue(result.GetField("is_force_buy"), "=", false)
	}

	// Test that round 13 is always a full buy
	round13TestCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestBuyRoundValidations")
	round13Results := round13TestCase.Data("player-round").
		Where("round_number", "=", 13).
		Get()

	for _, result := range round13Results {
		ctx.AssertValue(result.GetField("is_full_buy"), "=", true)
		ctx.AssertValue(result.GetField("is_eco"), "=", false)
		ctx.AssertValue(result.GetField("is_force_buy"), "=", false)
	}

	// Test specific eco round
	ecoTestCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestBuyRoundValidations")
	result := ecoTestCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198090208424").
		Where("round_number", "=", 8).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("is_eco"), "=", true)
	}

	// Test specific force buy round
	forceTestCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestBuyRoundValidations")
	result = forceTestCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 8).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("is_force_buy"), "=", true)
	}

	// Test specific full buy round
	fullBuyTestCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestBuyRoundValidations")
	result = fullBuyTestCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561198039653735").
		Where("round_number", "=", 9).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("is_full_buy"), "=", true)
	}

	return ctx.GetResult()
}

// TestGrenadeValueLostValidation - Test grenade value lost on death validation
func (tc *TestClient) TestGrenadeValueLostValidation(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestGrenadeValueLostValidation")
	ctx := types.NewTestContext("TestGrenadeValueLostValidation")

	// Test specific player grenade value lost
	result := testCase.Data("player-round").
		Where("player_steam_id", "=", "steam_76561199426243273").
		Where("round_number", "=", 7).
		First()

	ctx.AssertNotNull(result)
	if result != nil {
		ctx.AssertValue(result.GetField("grenade_value_lost_on_death"), "=", 1000)
	}

	// Test that grenade value lost never exceeds 1300 across all records
	allResults := testCase.Data("player-round").Get()
	for _, result := range allResults {
		grenadeValueLost := result.GetInt("grenade_value_lost_on_death")
		ctx.AssertValue(grenadeValueLost, "<=", 1300)
	}

	return ctx.GetResult()
}
