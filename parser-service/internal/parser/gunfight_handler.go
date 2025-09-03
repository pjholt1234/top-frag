package parser

import (
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

// GunfightHandler handles all gunfight-related events
type GunfightHandler struct {
	processor *EventProcessor
	logger    *logrus.Logger
}

// NewGunfightHandler creates a new gunfight handler
func NewGunfightHandler(processor *EventProcessor, logger *logrus.Logger) *GunfightHandler {
	return &GunfightHandler{
		processor: processor,
		logger:    logger,
	}
}

// HandlePlayerKilled handles player kill events
func (gh *GunfightHandler) HandlePlayerKilled(e events.Kill) {
	if e.Killer == nil || e.Victim == nil {
		return
	}

	// Ensure players are tracked
	gh.processor.ensurePlayerTracked(e.Killer)
	gh.processor.ensurePlayerTracked(e.Victim)

	// Check if this is the first kill of the round
	isFirstKill := gh.processor.matchState.FirstKillPlayer == nil

	if gh.processor.matchState.FirstKillPlayer == nil {
		steamID := types.SteamIDToString(e.Killer.SteamID64)
		gh.processor.matchState.FirstKillPlayer = &steamID
	}
	if gh.processor.matchState.FirstDeathPlayer == nil {
		steamID := types.SteamIDToString(e.Victim.SteamID64)
		gh.processor.matchState.FirstDeathPlayer = &steamID
	}

	gh.processor.matchState.CurrentRoundKills++
	gh.processor.matchState.CurrentRoundDeaths++

	if killerState, exists := gh.processor.playerStates[e.Killer.SteamID64]; exists {
		killerState.Kills++
		if e.IsHeadshot {
			killerState.Headshots++
		}
		if e.PenetratedObjects > 0 {
			killerState.Wallbangs++
		}
	}

	if victimState, exists := gh.processor.playerStates[e.Victim.SteamID64]; exists {
		victimState.Deaths++
	}

	gunfightEvent := gh.createGunfightEvent(e, isFirstKill)

	// Check if any active flash effects contributed to this kill
	killerSteamID := types.SteamIDToString(e.Killer.SteamID64)
	victimSteamID := types.SteamIDToString(e.Victim.SteamID64)
	gunfightEvent.FlashAssisterSteamID = gh.processor.grenadeHandler.CheckFlashEffectiveness(killerSteamID, victimSteamID, gh.processor.currentTick)

	gh.processor.matchState.GunfightEvents = append(gh.processor.matchState.GunfightEvents, gunfightEvent)
}

// createGunfightEvent creates a gunfight event from a kill event
func (gh *GunfightHandler) createGunfightEvent(e events.Kill, isFirstKill bool) types.GunfightEvent {
	roundTime := gh.processor.getCurrentRoundTime()

	player1Pos := gh.processor.getPlayerPosition(e.Killer)
	player2Pos := gh.processor.getPlayerPosition(e.Victim)

	distance := types.CalculateDistance(player1Pos, player2Pos)

	player1SteamID := types.SteamIDToString(e.Killer.SteamID64)
	player2SteamID := types.SteamIDToString(e.Victim.SteamID64)

	gunfightEvent := types.GunfightEvent{
		RoundNumber:       gh.processor.matchState.CurrentRound,
		RoundTime:         roundTime,
		TickTimestamp:     gh.processor.currentTick,
		Player1SteamID:    player1SteamID,
		Player2SteamID:    player2SteamID,
		Player1Side:       gh.processor.getPlayerCurrentSide(player1SteamID),
		Player2Side:       gh.processor.getPlayerCurrentSide(player2SteamID),
		Player1HPStart:    gh.getPlayerHP(e.Killer),
		Player2HPStart:    gh.getPlayerHP(e.Victim),
		Player1Armor:      gh.getPlayerArmor(e.Killer),
		Player2Armor:      gh.getPlayerArmor(e.Victim),
		Player1Flashed:    gh.getPlayerFlashed(e.Killer),
		Player2Flashed:    gh.getPlayerFlashed(e.Victim),
		Player1Weapon:     gh.getPlayerWeapon(e.Killer),
		Player2Weapon:     gh.getPlayerWeapon(e.Victim),
		Player1EquipValue: gh.getPlayerEquipmentValue(e.Killer),
		Player2EquipValue: gh.getPlayerEquipmentValue(e.Victim),
		Player1Position:   player1Pos,
		Player2Position:   player2Pos,
		Distance:          distance,
		Headshot:          e.IsHeadshot,
		Wallbang:          e.PenetratedObjects > 0,
		PenetratedObjects: e.PenetratedObjects,
		VictorSteamID:     nil,
		DamageDealt:       0,
		IsFirstKill:       isFirstKill,
	}

	gunfightEvent.VictorSteamID = &player1SteamID
	gunfightEvent.DamageDealt = 100 - gunfightEvent.Player2HPStart

	return gunfightEvent
}

// getPlayerHP gets a player's health points
func (gh *GunfightHandler) getPlayerHP(player *common.Player) int {
	if player == nil {
		return 0
	}
	return player.Health()
}

// getPlayerArmor gets a player's armor value
func (gh *GunfightHandler) getPlayerArmor(player *common.Player) int {
	if player == nil {
		return 0
	}
	return player.Armor()
}

// getPlayerFlashed checks if a player is currently flashed
func (gh *GunfightHandler) getPlayerFlashed(player *common.Player) bool {
	if player == nil {
		return false
	}

	flashDuration := player.FlashDuration
	return flashDuration > 0
}

// getPlayerWeapon gets a player's active weapon
func (gh *GunfightHandler) getPlayerWeapon(player *common.Player) string {
	if player == nil || player.ActiveWeapon() == nil {
		return "Unknown"
	}
	weapon := player.ActiveWeapon().String()
	if weapon == "" {
		return "Unknown"
	}
	return weapon
}

// getPlayerEquipmentValue calculates a player's total equipment value
func (gh *GunfightHandler) getPlayerEquipmentValue(player *common.Player) int {
	if player == nil {
		return 0
	}

	equipmentValue := 0

	// Add value of all weapons in inventory (this includes the active weapon)
	for _, weapon := range player.Inventory {
		if weapon != nil {
			weaponType := int(weapon.Type)
			equipmentValue += types.GetEquipmentValue(weaponType)
		}
	}

	// Add armor value
	if player.Armor() > 0 {
		if player.HasHelmet() {
			equipmentValue += types.GetEquipmentValue(403) // EqHelmet (Kevlar + Helmet)
		} else {
			equipmentValue += types.GetEquipmentValue(402) // EqKevlar
		}
	}

	return equipmentValue
}
