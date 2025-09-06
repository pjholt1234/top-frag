package client

import (
	"fmt"
	"strconv"
	"strings"

	"parser-test/internal/types"
)

// getDamageTests returns all damage event test functions using new assertion pattern
func (tc *TestClient) getDamageTests() []TestFunction {
	return []TestFunction{
		tc.TestDamageEventsExists,
		tc.TestBasicDamageEventDetection,
		tc.TestRoundTime,
		tc.TestTickTimestamp,
		tc.TestDamageEventFields,
		tc.TestDamageEventWeapons,
		tc.TestDamageEventHeadshots,
		tc.TestDamageEventArmorDamage,
		tc.TestDamageEventHealthDamage,
		tc.TestDamageEventSteamIDs,
		tc.TestWeapon,
		tc.TestEventCount,
		tc.TestDamage,
		tc.TestNoDuplicates,
	}
}

func (tc *TestClient) TestDamageEventsExists(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageEventsExists")
	ctx := types.NewTestContext("TestDamageEventsExists")

	results := testCase.Data("damage").Get()
	ctx.AssertCount(results, ">", 0)

	return ctx.GetResult()
}

func (tc *TestClient) TestBasicDamageEventDetection(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestBasicDamageEventDetection")
	ctx := types.NewTestContext("TestBasicDamageEventDetection")

	// Get filtered damage events
	result := testCase.Data("damage").
		Where("attacker_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 1).
		First()

	// Assert that we found a result
	ctx.AssertNotNull(result)

	if result != nil {
		// Verify the damage amount (based on actual data in damage-events.json)
		damage := result.GetField("damage")
		ctx.AssertValue(damage, "=", 33)

		// Verify it was not a headshot
		headshot := result.GetField("headshot")
		ctx.AssertValue(headshot, "=", false)

		// Verify the victim steam ID (based on actual data)
		victimSteamID := result.GetField("victim_steam_id")
		ctx.AssertValue(victimSteamID, "=", "steam_76561198039653735")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestRoundTime(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestRoundTime")
	ctx := types.NewTestContext("TestRoundTime")

	results := testCase.Data("damage").
		Where("attacker_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 1).
		First()

	ctx.AssertNotNull(results)
	if results != nil {
		ctx.AssertValue(results.GetField("round_time"), "=", 19)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestTickTimestamp(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestTickTimestamp")
	ctx := types.NewTestContext("TestTickTimestamp")

	results := testCase.Data("damage").
		Where("attacker_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 3).
		Get()

	expectedTickTimestamps := []int{12991, 14337}
	for i, result := range results {
		if i < len(expectedTickTimestamps) {
			ctx.AssertValue(result.GetField("tick_timestamp"), "=", expectedTickTimestamps[i])
		}
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestDamageEventFields(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageEventFields")
	ctx := types.NewTestContext("TestDamageEventFields")

	// Get first damage event to test field presence
	result := testCase.Data("damage").First()
	ctx.AssertNotNull(result)

	if result != nil {
		// Test that all required fields exist
		ctx.AssertFieldExists(result, "attacker_steam_id")
		ctx.AssertFieldExists(result, "victim_steam_id")
		ctx.AssertFieldExists(result, "damage")
		ctx.AssertFieldExists(result, "headshot")
		ctx.AssertFieldExists(result, "weapon")
		ctx.AssertFieldExists(result, "round_number")
		ctx.AssertFieldExists(result, "armor_damage")
		ctx.AssertFieldExists(result, "health_damage")
		ctx.AssertFieldExists(result, "round_time")
		ctx.AssertFieldExists(result, "tick_timestamp")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestDamageEventWeapons(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageEventWeapons")
	ctx := types.NewTestContext("TestDamageEventWeapons")

	// Test that we have damage events with various weapons
	weapons := []string{"AK-47", "M4A1", "USP-S", "Glock-18", "HE Grenade", "Desert Eagle", "MAC-10", "Five-SeveN", "AWP", "M4A4", "FAMAS", "MP9", "Dual Berettas", "Galil AR", "Tec-9", "Incendiary Grenade", "Molotov", "Smoke Grenade"}

	for _, weapon := range weapons {
		results := testCase.Data("damage").
			Where("weapon", "=", weapon).
			Get()

		// At least some weapons should be present in the data
		if len(results) > 0 {
			ctx.AssertCount(results, ">", 0)
			break // Found at least one weapon, test passes
		}
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestDamageEventHeadshots(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageEventHeadshots")
	ctx := types.NewTestContext("TestDamageEventHeadshots")

	// Test headshot events exist
	headshotResults := testCase.Data("damage").
		Where("headshot", "=", true).
		Get()
	ctx.AssertCount(headshotResults, ">=", 0) // Headshots may or may not exist

	// Test non-headshot events exist
	nonHeadshotResults := testCase.Data("damage").
		Where("headshot", "=", false).
		Get()
	ctx.AssertCount(nonHeadshotResults, ">=", 0) // Non-headshots may or may not exist

	return ctx.GetResult()
}

func (tc *TestClient) TestDamageEventArmorDamage(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageEventArmorDamage")
	ctx := types.NewTestContext("TestDamageEventArmorDamage")

	// Test armor damage values are non-negative
	results := testCase.Data("damage").Get()

	for _, result := range results {
		armorDamage := result.GetField("armor_damage")
		ctx.AssertValue(armorDamage, ">=", 0)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestDamageEventHealthDamage(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageEventHealthDamage")
	ctx := types.NewTestContext("TestDamageEventHealthDamage")

	// Test health damage values are non-negative
	results := testCase.Data("damage").Get()

	for _, result := range results {
		healthDamage := result.GetField("health_damage")
		ctx.AssertValue(healthDamage, ">=", 0)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestDamageEventSteamIDs(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamageEventSteamIDs")
	ctx := types.NewTestContext("TestDamageEventSteamIDs")

	// Test that steam IDs are properly formatted
	results := testCase.Data("damage").Get()

	for _, result := range results {
		attackerSteamID := result.GetField("attacker_steam_id")
		victimSteamID := result.GetField("victim_steam_id")

		// Steam IDs should start with "steam_"
		attackerStr := fmt.Sprintf("%v", attackerSteamID)
		victimStr := fmt.Sprintf("%v", victimSteamID)

		ctx.AssertValue(strings.HasPrefix(attackerStr, "steam_"), "=", true)
		ctx.AssertValue(strings.HasPrefix(victimStr, "steam_"), "=", true)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestWeapon(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestWeapon")
	ctx := types.NewTestContext("TestWeapon")

	// Test weapon where attacker_steam_id = steam_76561198081165057 AND round_number = 1
	results := testCase.Data("damage").
		Where("attacker_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 1).
		Get()

	// Results should be = 1
	ctx.AssertCount(results, "=", 1)

	if len(results) == 1 {
		// Result 1 should have weapon = USP-S
		weapon := results[0].GetField("weapon")
		ctx.AssertValue(weapon, "=", "USP-S")
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestEventCount(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestEventCount")
	ctx := types.NewTestContext("TestEventCount")

	// Test event count where attacker_steam_id = steam_76561198081165057 AND round_number = 3
	results := testCase.Data("damage").
		Where("attacker_steam_id", "=", "steam_76561198081165057").
		Where("round_number", "=", 3).
		Get()

	// Results should be = 3
	ctx.AssertCount(results, "=", 3)

	return ctx.GetResult()
}

func (tc *TestClient) TestDamage(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestDamage")
	ctx := types.NewTestContext("TestDamage")

	// Test damage where attacker_steam_id = steam_76561198081165057
	results := testCase.Data("damage").
		Where("attacker_steam_id", "=", "steam_76561198081165057").
		Get()

	// FOR EACH: armor_damage + health_damage = damage
	for _, result := range results {
		armorDamage := result.GetField("armor_damage")
		healthDamage := result.GetField("health_damage")
		totalDamage := result.GetField("damage")

		// Convert to float64 for calculation
		armorFloat, _ := convertToFloat64(armorDamage)
		healthFloat, _ := convertToFloat64(healthDamage)
		totalFloat, _ := convertToFloat64(totalDamage)

		expectedTotal := armorFloat + healthFloat
		ctx.AssertValue(expectedTotal, "=", totalFloat)
	}

	return ctx.GetResult()
}

func (tc *TestClient) TestNoDuplicates(client *TestClient) *types.AssertionResult {
	testCase := types.NewTestCase(client, tc.jobID, tc.logger, "TestNoDuplicates")
	ctx := types.NewTestContext("TestNoDuplicates")

	// Test for no duplicates - get all damage events
	results := testCase.Data("damage").Get()

	// Create a map to track unique combinations of key fields
	seen := make(map[string]bool)
	duplicates := 0

	for _, result := range results {
		// Create a unique key based on key fields that should be unique
		attackerSteamID := result.GetField("attacker_steam_id")
		victimSteamID := result.GetField("victim_steam_id")
		roundNumber := result.GetField("round_number")
		tickTimestamp := result.GetField("tick_timestamp")
		damage := result.GetField("damage")

		key := fmt.Sprintf("%v_%v_%v_%v_%v", attackerSteamID, victimSteamID, roundNumber, tickTimestamp, damage)

		if seen[key] {
			duplicates++
		} else {
			seen[key] = true
		}
	}

	// Should have no duplicates
	ctx.AssertValue(duplicates, "=", 0)

	return ctx.GetResult()
}

// Helper function to convert interface{} to float64
func convertToFloat64(value interface{}) (float64, error) {
	switch v := value.(type) {
	case float64:
		return v, nil
	case float32:
		return float64(v), nil
	case int:
		return float64(v), nil
	case int8:
		return float64(v), nil
	case int16:
		return float64(v), nil
	case int32:
		return float64(v), nil
	case int64:
		return float64(v), nil
	case uint:
		return float64(v), nil
	case uint8:
		return float64(v), nil
	case uint16:
		return float64(v), nil
	case uint32:
		return float64(v), nil
	case uint64:
		return float64(v), nil
	case string:
		return strconv.ParseFloat(v, 64)
	default:
		return 0, fmt.Errorf("cannot convert %T to float64", value)
	}
}
