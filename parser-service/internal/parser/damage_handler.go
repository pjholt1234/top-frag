package parser

import (
	"parser-service/internal/types"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
)

// DamageHandler handles all damage-related events
type DamageHandler struct {
	processor *EventProcessor
	logger    *logrus.Logger
}

// NewDamageHandler creates a new damage handler
func NewDamageHandler(processor *EventProcessor, logger *logrus.Logger) *DamageHandler {
	return &DamageHandler{
		processor: processor,
		logger:    logger,
	}
}

// HandlePlayerHurt handles player hurt events
func (dh *DamageHandler) HandlePlayerHurt(e events.PlayerHurt) error {
	if e.Attacker == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "attacker is nil", nil).
			WithContext("event_type", "PlayerHurt").
			WithContext("tick", dh.processor.currentTick)
	}

	if e.Player == nil {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "victim player is nil", nil).
			WithContext("event_type", "PlayerHurt").
			WithContext("tick", dh.processor.currentTick)
	}

	if e.HealthDamage < 0 {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "health damage cannot be negative", nil).
			WithContext("health_damage", e.HealthDamage).
			WithContext("attacker", types.SteamIDToString(e.Attacker.SteamID64)).
			WithContext("victim", types.SteamIDToString(e.Player.SteamID64))
	}

	if e.ArmorDamage < 0 {
		return types.NewParseErrorWithSeverity(types.ErrorTypeValidation, types.ErrorSeverityWarning, "armor damage cannot be negative", nil).
			WithContext("armor_damage", e.ArmorDamage).
			WithContext("attacker", types.SteamIDToString(e.Attacker.SteamID64)).
			WithContext("victim", types.SteamIDToString(e.Player.SteamID64))
	}

	// Ensure players are tracked
	if err := dh.processor.ensurePlayerTracked(e.Attacker); err != nil {
		return err
	}
	if err := dh.processor.ensurePlayerTracked(e.Player); err != nil {
		return err
	}

	roundTime := dh.processor.getCurrentRoundTime()

	// Calculate actual damage inflicted, capped at victim's remaining health
	actualHealthDamage := e.HealthDamage
	if e.Player.Health() < e.HealthDamage {
		actualHealthDamage = e.Player.Health()
	}

	weaponName := e.Weapon.String()
	damageEvent := types.DamageEvent{
		RoundNumber:     dh.processor.matchState.CurrentRound,
		RoundTime:       roundTime,
		TickTimestamp:   dh.processor.currentTick,
		AttackerSteamID: types.SteamIDToString(e.Attacker.SteamID64),
		VictimSteamID:   types.SteamIDToString(e.Player.SteamID64),
		Damage:          actualHealthDamage,
		ArmorDamage:     e.ArmorDamage,
		HealthDamage:    actualHealthDamage,
		Headshot:        false,
		Weapon:          weaponName,
	}

	// Log damage events for grenades to help debug
	if weaponName == "hegrenade" || weaponName == "molotov" || weaponName == "incendiary" {
		dh.logger.WithFields(logrus.Fields{
			"weapon":        weaponName,
			"attacker":      damageEvent.AttackerSteamID,
			"victim":        damageEvent.VictimSteamID,
			"health_damage": damageEvent.HealthDamage,
			"armor_damage":  damageEvent.ArmorDamage,
			"round_time":    damageEvent.RoundTime,
			"tick":          damageEvent.TickTimestamp,
		}).Info("Grenade damage event created")
	}

	dh.processor.matchState.DamageEvents = append(dh.processor.matchState.DamageEvents, damageEvent)

	if attackerState, exists := dh.processor.playerStates[e.Attacker.SteamID64]; exists {
		attackerState.TotalDamage += damageEvent.Damage
	}
	if victimState, exists := dh.processor.playerStates[e.Player.SteamID64]; exists {
		victimState.DamageTaken += damageEvent.Damage
	}

	return nil
}
