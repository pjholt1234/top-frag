package parser

import (
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/sirupsen/logrus"
	"parser-service/internal/types"
)

// Struct for managing match events

type EventProcessor struct {
	matchState *types.MatchState
	logger     *logrus.Logger
	playerStates map[uint64]*types.PlayerState
}

func NewEventProcessor(matchState *types.MatchState, logger *logrus.Logger) *EventProcessor {
	return &EventProcessor{
		matchState:    matchState,
		logger:        logger,
		playerStates:  make(map[uint64]*types.PlayerState),
	}
}

func (ep *EventProcessor) HandleRoundStart(e events.RoundStart) {
	ep.matchState.CurrentRound++
	ep.matchState.RoundStartTick = 0
	ep.matchState.CurrentRoundKills = 0
	ep.matchState.CurrentRoundDeaths = 0
	ep.matchState.FirstKillPlayer = nil
	ep.matchState.FirstDeathPlayer = nil

	for _, playerState := range ep.playerStates {
		playerState.CurrentHP = 100
		playerState.CurrentArmor = 0
		playerState.IsFlashed = false
		playerState.CurrentWeapon = ""
		playerState.EquipmentValue = 0
	}

	roundEvent := types.RoundEvent{
		RoundNumber:   ep.matchState.CurrentRound,
		TickTimestamp: 0,
		EventType:     "start",
	}
	ep.matchState.RoundEvents = append(ep.matchState.RoundEvents, roundEvent)

	ep.logger.WithField("round", ep.matchState.CurrentRound).Debug("Round started")
}

func (ep *EventProcessor) HandleRoundEnd(e events.RoundEnd) {
	ep.matchState.RoundEndTick = 0

	var winner string
	if e.Winner == common.TeamCounterTerrorists {
		winner = "CT"
	} else if e.Winner == common.TeamTerrorists {
		winner = "T"
	}

	duration := 120

	roundEvent := types.RoundEvent{
		RoundNumber:   ep.matchState.CurrentRound,
		TickTimestamp: 0,
		EventType:     "end",
		Winner:        &winner,
		Duration:      &duration,
	}
	ep.matchState.RoundEvents = append(ep.matchState.RoundEvents, roundEvent)

	ep.logger.WithFields(logrus.Fields{
		"round":    ep.matchState.CurrentRound,
		"winner":   winner,
		"duration": duration,
	}).Debug("Round ended")
}

func (ep *EventProcessor) HandlePlayerKilled(e events.Kill) {
	if e.Killer == nil || e.Victim == nil {
		return
	}

	if ep.matchState.FirstKillPlayer == nil {
		steamID := types.SteamIDToString(e.Killer.SteamID64)
		ep.matchState.FirstKillPlayer = &steamID
	}
	if ep.matchState.FirstDeathPlayer == nil {
		steamID := types.SteamIDToString(e.Victim.SteamID64)
		ep.matchState.FirstDeathPlayer = &steamID
	}

	ep.matchState.CurrentRoundKills++
	ep.matchState.CurrentRoundDeaths++

	if killerState, exists := ep.playerStates[e.Killer.SteamID64]; exists {
		killerState.Kills++
		if e.IsHeadshot {
			killerState.Headshots++
		}
		if e.PenetratedObjects > 0 {
			killerState.Wallbangs++
		}
	}

	if victimState, exists := ep.playerStates[e.Victim.SteamID64]; exists {
		victimState.Deaths++
	}

	gunfightEvent := ep.createGunfightEvent(e)
	ep.matchState.GunfightEvents = append(ep.matchState.GunfightEvents, gunfightEvent)

	ep.logger.WithFields(logrus.Fields{
		"killer":   e.Killer.Name,
		"victim":   e.Victim.Name,
		"weapon":   e.Weapon.String(),
		"headshot": e.IsHeadshot,
	}).Debug("Player killed")
}

func (ep *EventProcessor) HandlePlayerHurt(e events.PlayerHurt) {
	if e.Attacker == nil || e.Player == nil {
		return
	}

	roundTime := 0

	damageEvent := types.DamageEvent{
		RoundNumber:     ep.matchState.CurrentRound,
		RoundTime:       roundTime,
		TickTimestamp:   0,
		AttackerSteamID: types.SteamIDToString(e.Attacker.SteamID64),
		VictimSteamID:   types.SteamIDToString(e.Player.SteamID64),
		Damage:          e.HealthDamage + e.ArmorDamage,
		ArmorDamage:     e.ArmorDamage,
		HealthDamage:    e.HealthDamage,
		Headshot:        false,
		Weapon:          e.Weapon.String(),
	}

	ep.matchState.DamageEvents = append(ep.matchState.DamageEvents, damageEvent)

	if attackerState, exists := ep.playerStates[e.Attacker.SteamID64]; exists {
		attackerState.TotalDamage += damageEvent.Damage
	}
	if victimState, exists := ep.playerStates[e.Player.SteamID64]; exists {
		victimState.DamageTaken += damageEvent.Damage
	}
}

func (ep *EventProcessor) HandleGrenadeProjectileDestroy(e events.GrenadeProjectileDestroy) {
	if e.Projectile.Thrower == nil {
		return
	}

	roundTime := 0

	playerPos := ep.getPlayerPosition(e.Projectile.Thrower)
	playerAim := ep.getPlayerAim(e.Projectile.Thrower)

	grenadeEvent := types.GrenadeEvent{
		RoundNumber:    ep.matchState.CurrentRound,
		RoundTime:      roundTime,
		TickTimestamp:  0,
		PlayerSteamID:  types.SteamIDToString(e.Projectile.Thrower.SteamID64),
		GrenadeType:    "hegrenade",
		PlayerPosition: playerPos,
		PlayerAim:      playerAim,
		ThrowType:      ep.determineThrowType(e.Projectile),
	}

	position := e.Projectile.Position()
	grenadeEvent.GrenadeFinalPosition = &types.Position{
		X: position.X,
		Y: position.Y,
		Z: position.Z,
	}

	ep.matchState.GrenadeEvents = append(ep.matchState.GrenadeEvents, grenadeEvent)

	if playerState, exists := ep.playerStates[e.Projectile.Thrower.SteamID64]; exists {
		playerState.HEDamage++
	}
}

func (ep *EventProcessor) HandleFlashExplode(e events.FlashExplode) {
	ep.logger.Debug("Flash grenade exploded")
}

func (ep *EventProcessor) HandleHeExplode(e events.HeExplode) {
	ep.logger.Debug("HE grenade exploded")
}

func (ep *EventProcessor) HandleSmokeStart(e events.SmokeStart) {
	ep.logger.Debug("Smoke grenade started")
}

func (ep *EventProcessor) HandleWeaponFire(e events.WeaponFire) {
	if e.Shooter == nil {
		return
	}

	if playerState, exists := ep.playerStates[e.Shooter.SteamID64]; exists {
		playerState.CurrentWeapon = e.Weapon.String()
	}
}

func (ep *EventProcessor) HandleBombPlanted(e events.BombPlanted) {
	ep.logger.Debug("Bomb planted")
}

func (ep *EventProcessor) HandleBombDefused(e events.BombDefused) {
	ep.logger.Debug("Bomb defused")
}

func (ep *EventProcessor) HandleBombExplode(e events.BombExplode) {
	ep.logger.Debug("Bomb exploded")
}

func (ep *EventProcessor) createGunfightEvent(e events.Kill) types.GunfightEvent {
	roundTime := 0

	player1Pos := ep.getPlayerPosition(e.Killer)
	player2Pos := ep.getPlayerPosition(e.Victim)

	distance := types.CalculateDistance(player1Pos, player2Pos)

	gunfightEvent := types.GunfightEvent{
		RoundNumber:      ep.matchState.CurrentRound,
		RoundTime:        roundTime,
		TickTimestamp:    0,
		Player1SteamID:   types.SteamIDToString(e.Killer.SteamID64),
		Player2SteamID:   types.SteamIDToString(e.Victim.SteamID64),
		Player1HPStart:   ep.getPlayerHP(e.Killer),
		Player2HPStart:   ep.getPlayerHP(e.Victim),
		Player1Armor:     ep.getPlayerArmor(e.Killer),
		Player2Armor:     ep.getPlayerArmor(e.Victim),
		Player1Flashed:   ep.getPlayerFlashed(e.Killer),
		Player2Flashed:   ep.getPlayerFlashed(e.Victim),
		Player1Weapon:    ep.getPlayerWeapon(e.Killer),
		Player2Weapon:    ep.getPlayerWeapon(e.Victim),
		Player1EquipValue: ep.getPlayerEquipmentValue(e.Killer),
		Player2EquipValue: ep.getPlayerEquipmentValue(e.Victim),
		Player1Position:  player1Pos,
		Player2Position:  player2Pos,
		Distance:         distance,
		Headshot:         e.IsHeadshot,
		Wallbang:         e.PenetratedObjects > 0,
		PenetratedObjects: e.PenetratedObjects,
		VictorSteamID:    nil,
		DamageDealt:      0,
	}

	victorSteamID := types.SteamIDToString(e.Killer.SteamID64)
	gunfightEvent.VictorSteamID = &victorSteamID
	gunfightEvent.DamageDealt = 100

	return gunfightEvent
}

func (ep *EventProcessor) getPlayerPosition(player *common.Player) types.Position {
	if player == nil {
		return types.Position{}
	}
	position := player.Position()
	return types.Position{
		X: position.X,
		Y: position.Y,
		Z: position.Z,
	}
}

func (ep *EventProcessor) getPlayerAim(player *common.Player) types.Vector {
	if player == nil {
		return types.Vector{}
	}
	viewX := player.ViewDirectionX()
	viewY := player.ViewDirectionY()
	return types.Vector{
		X: float64(viewX),
		Y: float64(viewY),
		Z: 0,
	}
}

func (ep *EventProcessor) getPlayerHP(player *common.Player) int {
	if player == nil {
		return 0
	}
	return player.Health()
}

func (ep *EventProcessor) getPlayerArmor(player *common.Player) int {
	if player == nil {
		return 0
	}
	return player.Armor()
}

func (ep *EventProcessor) getPlayerFlashed(player *common.Player) bool {
	if player == nil {
		return false
	}
	return false
}

func (ep *EventProcessor) getPlayerWeapon(player *common.Player) string {
	if player == nil || player.ActiveWeapon() == nil {
		return ""
	}
	return player.ActiveWeapon().String()
}

func (ep *EventProcessor) getPlayerEquipmentValue(player *common.Player) int {
	if player == nil {
		return 0
	}
	return 0
}

func (ep *EventProcessor) getTeamString(team common.Team) string {
	switch team {
	case common.TeamCounterTerrorists:
		return "CT"
	case common.TeamTerrorists:
		return "T"
	default:
		return "Unknown"
	}
}

func (ep *EventProcessor) determineThrowType(projectile *common.GrenadeProjectile) string {
	return types.ThrowTypeUtility
} 