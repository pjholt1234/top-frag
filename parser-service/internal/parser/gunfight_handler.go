package parser

import (
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

type GunfightHandler struct {
	processor *EventProcessor
	logger    *logrus.Logger
}

func NewGunfightHandler(processor *EventProcessor, logger *logrus.Logger) *GunfightHandler {
	return &GunfightHandler{
		processor: processor,
		logger:    logger,
	}
}

func (gh *GunfightHandler) HandlePlayerKilled(e events.Kill) {
	if e.Killer == nil || e.Victim == nil {
		return
	}

	gh.processor.ensurePlayerTracked(e.Killer)
	gh.processor.ensurePlayerTracked(e.Victim)

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

	killerSide := gh.processor.getPlayerCurrentSide(types.SteamIDToString(e.Killer.SteamID64))
	victimSide := gh.processor.getPlayerCurrentSide(types.SteamIDToString(e.Victim.SteamID64))
	roundScenario := gh.processor.getRoundScenario(killerSide, victimSide)

	gunfightEvent := gh.createGunfightEvent(e, isFirstKill)
	gunfightEvent.RoundScenario = roundScenario

	killerSteamID := types.SteamIDToString(e.Killer.SteamID64)
	victimSteamID := types.SteamIDToString(e.Victim.SteamID64)
	gunfightEvent.FlashAssisterSteamID = gh.processor.grenadeHandler.CheckFlashEffectiveness(killerSteamID, victimSteamID, gh.processor.currentTick)

	gh.processor.matchState.GunfightEvents = append(gh.processor.matchState.GunfightEvents, gunfightEvent)
}

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

	gunfightEvent.DamageAssistSteamID = gh.findDamageAssist(player2SteamID, player1SteamID)

	return gunfightEvent
}

func (gh *GunfightHandler) getPlayerHP(player *common.Player) int {
	if player == nil {
		return 0
	}
	return player.Health()
}

func (gh *GunfightHandler) getPlayerArmor(player *common.Player) int {
	if player == nil {
		return 0
	}
	return player.Armor()
}

func (gh *GunfightHandler) getPlayerFlashed(player *common.Player) bool {
	if player == nil {
		return false
	}

	flashDuration := player.FlashDuration
	return flashDuration > 0
}

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

func (gh *GunfightHandler) getPlayerEquipmentValue(player *common.Player) int {
	if player == nil {
		return 0
	}

	equipmentValue := 0

	for _, weapon := range player.Inventory {
		if weapon != nil {
			weaponType := int(weapon.Type)
			equipmentValue += types.GetEquipmentValue(weaponType)
		}
	}

	if player.Armor() > 0 {
		if player.HasHelmet() {
			equipmentValue += types.GetEquipmentValue(403)
		} else {
			equipmentValue += types.GetEquipmentValue(402)
		}
	}

	return equipmentValue
}

func (gh *GunfightHandler) findDamageAssist(victimSteamID, killerSteamID string) *string {
	damageByPlayer := make(map[string]int)

	for _, damageEvent := range gh.processor.matchState.DamageEvents {
		if damageEvent.RoundNumber == gh.processor.matchState.CurrentRound &&
			damageEvent.VictimSteamID == victimSteamID &&
			damageEvent.AttackerSteamID != killerSteamID {
			damageByPlayer[damageEvent.AttackerSteamID] += damageEvent.HealthDamage
		}
	}

	var assistSteamID string
	maxDamage := 0

	for playerSteamID, totalDamage := range damageByPlayer {
		if totalDamage >= types.DamageAssistThreshold && totalDamage > maxDamage {
			maxDamage = totalDamage
			assistSteamID = playerSteamID
		}
	}

	if assistSteamID != "" {
		return &assistSteamID
	}

	return nil
}
