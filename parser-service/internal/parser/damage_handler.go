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
func (dh *DamageHandler) HandlePlayerHurt(e events.PlayerHurt) {
	if e.Attacker == nil || e.Player == nil {
		return
	}

	// Ensure players are tracked
	dh.processor.ensurePlayerTracked(e.Attacker)
	dh.processor.ensurePlayerTracked(e.Player)

	roundTime := dh.processor.getCurrentRoundTime()

	// Calculate actual damage inflicted, capped at victim's remaining health
	actualHealthDamage := e.HealthDamage
	if e.Player.Health() < e.HealthDamage {
		actualHealthDamage = e.Player.Health()
	}

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
		Weapon:          e.Weapon.String(),
	}

	dh.processor.matchState.DamageEvents = append(dh.processor.matchState.DamageEvents, damageEvent)

	if attackerState, exists := dh.processor.playerStates[e.Attacker.SteamID64]; exists {
		attackerState.TotalDamage += damageEvent.Damage
	}
	if victimState, exists := dh.processor.playerStates[e.Player.SteamID64]; exists {
		victimState.DamageTaken += damageEvent.Damage
	}
}
