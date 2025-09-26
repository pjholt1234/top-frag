package types

import (
	"fmt"
	"math"
	"mime/multipart"
	"time"
)

// API Types
// These types are the schemas for the API responses and requests
// json tags: Define how structs are serialized to JSON

type Position struct {
	X float64 `json:"x"`
	Y float64 `json:"y"`
	Z float64 `json:"z"`
}

type Vector struct {
	X float64 `json:"x"`
	Y float64 `json:"y"`
	Z float64 `json:"z"`
}

type Player struct {
	SteamID string  `json:"steam_id"`
	Name    string  `json:"name"`
	Team    string  `json:"team"`           // "A" or "B" (arbitrary team assignment)
	Rank    *string `json:"rank,omitempty"` // Player's matchmaking rank (legacy field)
	// New rank fields
	RankString *string `json:"rank_string,omitempty"`
	RankType   *string `json:"rank_type,omitempty"`
	RankValue  *int    `json:"rank_value,omitempty"`
}

type GunfightEvent struct {
	RoundNumber   int   `json:"round_number"`
	RoundTime     int   `json:"round_time"`
	TickTimestamp int64 `json:"tick_timestamp"`

	Player1SteamID string `json:"player_1_steam_id"`
	Player2SteamID string `json:"player_2_steam_id"`

	Player1HPStart      int    `json:"player_1_hp_start"`
	Player2HPStart      int    `json:"player_2_hp_start"`
	Player1Armor        int    `json:"player_1_armor"`
	Player2Armor        int    `json:"player_2_armor"`
	Player1Flashed      bool   `json:"player_1_flashed"`
	Player2Flashed      bool   `json:"player_2_flashed"`
	Player1Weapon       string `json:"player_1_weapon"`
	Player2Weapon       string `json:"player_2_weapon"`
	Player1EquipValue   int    `json:"player_1_equipment_value"`
	Player2EquipValue   int    `json:"player_2_equipment_value"`
	Player1GrenadeValue int    `json:"player_1_grenade_value"`
	Player2GrenadeValue int    `json:"player_2_grenade_value"`

	Player1Position Position `json:"player_1_position"`
	Player2Position Position `json:"player_2_position"`

	Distance          float64 `json:"distance"`
	Headshot          bool    `json:"headshot"`
	Wallbang          bool    `json:"wallbang"`
	PenetratedObjects int     `json:"penetrated_objects"`

	VictorSteamID        *string `json:"victor_steam_id,omitempty"`
	DamageDealt          int     `json:"damage_dealt"`
	IsFirstKill          bool    `json:"is_first_kill"`
	Player1Side          string  `json:"player_1_side"` // "CT" or "T"
	Player2Side          string  `json:"player_2_side"` // "CT" or "T"
	FlashAssisterSteamID *string `json:"flash_assister_steam_id,omitempty"`
	DamageAssistSteamID  *string `json:"damage_assist_steam_id,omitempty"`
	RoundScenario        string  `json:"round_scenario"` // e.g., "5v4" (killer's team vs victim's team)

	// Impact Rating Fields
	Player1TeamStrength float64 `json:"player_1_team_strength"`
	Player2TeamStrength float64 `json:"player_2_team_strength"`
	Player1Impact       float64 `json:"player_1_impact"`
	Player2Impact       float64 `json:"player_2_impact"`
	AssisterImpact      float64 `json:"assister_impact"`
	FlashAssisterImpact float64 `json:"flash_assister_impact"`
}

type AffectedPlayer struct {
	SteamID       string   `json:"steam_id"`
	FlashDuration *float64 `json:"flash_duration,omitempty"`
	DamageTaken   *int     `json:"damage_taken,omitempty"`
}

type GrenadeEvent struct {
	RoundNumber   int   `json:"round_number"`
	RoundTime     int   `json:"round_time"`
	TickTimestamp int64 `json:"tick_timestamp"`
	ExplosionTick int64 `json:"explosion_tick,omitempty"` // For flashbang matching

	PlayerSteamID string `json:"player_steam_id"`
	PlayerSide    string `json:"player_side"` // "CT" or "T"
	GrenadeType   string `json:"grenade_type"`
	EntityID      int    `json:"entity_id,omitempty"` // For flashbang matching

	PlayerPosition Position `json:"player_position"`
	PlayerAim      Vector   `json:"player_aim"`

	GrenadeFinalPosition *Position `json:"grenade_final_position,omitempty"`

	DamageDealt     int              `json:"damage_dealt"`
	TeamDamageDealt int              `json:"team_damage_dealt"`
	FlashDuration   *float64         `json:"flash_duration,omitempty"`
	AffectedPlayers []AffectedPlayer `json:"affected_players,omitempty"`

	// Flash tracking fields
	FriendlyFlashDuration   *float64 `json:"friendly_flash_duration,omitempty"`
	EnemyFlashDuration      *float64 `json:"enemy_flash_duration,omitempty"`
	FriendlyPlayersAffected int      `json:"friendly_players_affected"`
	EnemyPlayersAffected    int      `json:"enemy_players_affected"`

	// Flash effectiveness tracking
	FlashLeadsToKill  bool `json:"flash_leads_to_kill"`  // Whether this flash blinded an enemy who was then killed
	FlashLeadsToDeath bool `json:"flash_leads_to_death"` // Whether this flash blinded a teammate who then died

	// Smoke blocking tracking
	SmokeBlockingDuration int `json:"smoke_blocking_duration"` // Total ticks the smoke blocked enemy LOS

	ThrowType string `json:"throw_type"`

	EffectivenessRating int `json:"effectiveness_rating"`
}

type RoundEvent struct {
	RoundNumber   int     `json:"round_number"`
	TickTimestamp int64   `json:"tick_timestamp"`
	EventType     string  `json:"event_type"`
	Winner        *string `json:"winner,omitempty"`
	Duration      *int    `json:"duration,omitempty"`

	// Impact Rating Fields
	TotalImpact       float64 `json:"total_impact"`
	TotalGunfights    int     `json:"total_gunfights"`
	AverageImpact     float64 `json:"average_impact"`
	RoundSwingPercent float64 `json:"round_swing_percent"`
	ImpactPercentage  float64 `json:"impact_percentage"`
}

type DamageEvent struct {
	RoundNumber   int   `json:"round_number"`
	RoundTime     int   `json:"round_time"`
	TickTimestamp int64 `json:"tick_timestamp"`

	AttackerSteamID string `json:"attacker_steam_id"`
	VictimSteamID   string `json:"victim_steam_id"`

	Damage       int    `json:"damage"`
	ArmorDamage  int    `json:"armor_damage"`
	HealthDamage int    `json:"health_damage"`
	Headshot     bool   `json:"headshot"`
	Weapon       string `json:"weapon"`
}

type PlayerRoundEvent struct {
	// Basic fields
	PlayerSteamID string `json:"player_steam_id"`
	RoundNumber   int    `json:"round_number"`

	// Gun Fights
	Kills            int  `json:"kills"`
	Assists          int  `json:"assists"`
	Died             bool `json:"died"`
	Damage           int  `json:"damage"`
	Headshots        int  `json:"headshots"`
	FirstKill        bool `json:"first_kill"`
	FirstDeath       bool `json:"first_death"`
	RoundTimeOfDeath *int `json:"round_time_of_death,omitempty"`
	KillsWithAWP     int  `json:"kills_with_awp"`

	// Grenades
	DamageDealt             int     `json:"damage_dealt"`
	FlashesThrown           int     `json:"flashes_thrown"`
	FireGrenadesThrown      int     `json:"fire_grenades_thrown"`
	SmokesThrown            int     `json:"smokes_thrown"`
	HesThrown               int     `json:"hes_thrown"`
	DecoysThrown            int     `json:"decoys_thrown"`
	FriendlyFlashDuration   float64 `json:"friendly_flash_duration"`
	EnemyFlashDuration      float64 `json:"enemy_flash_duration"`
	FriendlyPlayersAffected int     `json:"friendly_players_affected"`
	EnemyPlayersAffected    int     `json:"enemy_players_affected"`
	FlashesLeadingToKill    int     `json:"flashes_leading_to_kill"`
	FlashesLeadingToDeath   int     `json:"flashes_leading_to_death"`
	GrenadeEffectiveness    int     `json:"grenade_effectiveness"`

	// Details
	SuccessfulTrades          int `json:"successful_trades"`
	TotalPossibleTrades       int `json:"total_possible_trades"`
	SuccessfulTradedDeaths    int `json:"successful_traded_deaths"`
	TotalPossibleTradedDeaths int `json:"total_possible_traded_deaths"`

	// Clutch attempts and wins (1v1, 1v2, 1v3, 1v4, 1v5)
	ClutchAttempts1v1 int `json:"clutch_attempts_1v1"`
	ClutchAttempts1v2 int `json:"clutch_attempts_1v2"`
	ClutchAttempts1v3 int `json:"clutch_attempts_1v3"`
	ClutchAttempts1v4 int `json:"clutch_attempts_1v4"`
	ClutchAttempts1v5 int `json:"clutch_attempts_1v5"`

	ClutchWins1v1 int `json:"clutch_wins_1v1"`
	ClutchWins1v2 int `json:"clutch_wins_1v2"`
	ClutchWins1v3 int `json:"clutch_wins_1v3"`
	ClutchWins1v4 int `json:"clutch_wins_1v4"`
	ClutchWins1v5 int `json:"clutch_wins_1v5"`

	TimeToContact float64 `json:"time_to_contact"`

	// Economy
	IsEco                   bool `json:"is_eco"`
	IsForceBuy              bool `json:"is_force_buy"`
	IsFullBuy               bool `json:"is_full_buy"`
	KillsVsEco              int  `json:"kills_vs_eco"`
	KillsVsForceBuy         int  `json:"kills_vs_force_buy"`
	KillsVsFullBuy          int  `json:"kills_vs_full_buy"`
	GrenadeValueLostOnDeath int  `json:"grenade_value_lost_on_death"`

	// Impact Rating Fields
	TotalImpact       float64 `json:"total_impact"`
	AverageImpact     float64 `json:"average_impact"`
	RoundSwingPercent float64 `json:"round_swing_percent"`
	ImpactPercentage  float64 `json:"impact_percentage"`
}

// Player Match Event
type PlayerMatchEvent struct {
	// Basic fields
	PlayerSteamID string `json:"player_steam_id"`

	// Gun Fights
	Kills                   int     `json:"kills"`
	Assists                 int     `json:"assists"`
	Deaths                  int     `json:"deaths"`
	Damage                  int     `json:"damage"`
	ADR                     float64 `json:"adr"`
	Headshots               int     `json:"headshots"`
	FirstKills              int     `json:"first_kills"`
	FirstDeaths             int     `json:"first_deaths"`
	AverageRoundTimeOfDeath float64 `json:"average_round_time_of_death"`
	KillsWithAWP            int     `json:"kills_with_awp"`

	// Grenades
	DamageDealt                 int     `json:"damage_dealt"`
	FlashesThrown               int     `json:"flashes_thrown"`
	FireGrenadesThrown          int     `json:"fire_grenades_thrown"`
	SmokesThrown                int     `json:"smokes_thrown"`
	HesThrown                   int     `json:"hes_thrown"`
	DecoysThrown                int     `json:"decoys_thrown"`
	FriendlyFlashDuration       float64 `json:"friendly_flash_duration"`
	EnemyFlashDuration          float64 `json:"enemy_flash_duration"`
	FriendlyPlayersAffected     int     `json:"friendly_players_affected"`
	EnemyPlayersAffected        int     `json:"enemy_players_affected"`
	FlashesLeadingToKills       int     `json:"flashes_leading_to_kills"`
	FlashesLeadingToDeaths      int     `json:"flashes_leading_to_deaths"`
	AverageGrenadeEffectiveness int     `json:"average_grenade_effectiveness"`

	// Details
	TotalSuccessfulTrades     int     `json:"total_successful_trades"`
	TotalPossibleTrades       int     `json:"total_possible_trades"`
	TotalTradedDeaths         int     `json:"total_traded_deaths"`
	TotalPossibleTradedDeaths int     `json:"total_possible_traded_deaths"`
	ClutchWins1v1             int     `json:"clutch_wins_1v1"`
	ClutchWins1v2             int     `json:"clutch_wins_1v2"`
	ClutchWins1v3             int     `json:"clutch_wins_1v3"`
	ClutchWins1v4             int     `json:"clutch_wins_1v4"`
	ClutchWins1v5             int     `json:"clutch_wins_1v5"`
	ClutchAttempts1v1         int     `json:"clutch_attempts_1v1"`
	ClutchAttempts1v2         int     `json:"clutch_attempts_1v2"`
	ClutchAttempts1v3         int     `json:"clutch_attempts_1v3"`
	ClutchAttempts1v4         int     `json:"clutch_attempts_1v4"`
	ClutchAttempts1v5         int     `json:"clutch_attempts_1v5"`
	AverageTimeToContact      float64 `json:"average_time_to_contact"`

	// Economy
	KillsVsEco              int     `json:"kills_vs_eco"`
	KillsVsForceBuy         int     `json:"kills_vs_force_buy"`
	KillsVsFullBuy          int     `json:"kills_vs_full_buy"`
	AverageGrenadeValueLost float64 `json:"average_grenade_value_lost"`

	// Ranking
	MatchmakingRank *string `json:"matchmaking_rank"`
	RankType        *string `json:"rank_type"`
	RankValue       *int    `json:"rank_value"`

	// Impact Rating Fields
	TotalImpact       float64 `json:"total_impact"`
	AverageImpact     float64 `json:"average_impact"`
	MatchSwingPercent float64 `json:"match_swing_percent"`
	ImpactPercentage  float64 `json:"impact_percentage"`
}

// GrenadeThrowInfo stores information about a grenade throw
type GrenadeThrowInfo struct {
	PlayerSteamID  string
	PlayerPosition Position
	PlayerAim      Vector
	ThrowTick      int64
	RoundNumber    int
	RoundTime      int
	GrenadeType    string
}

type Match struct {
	Map              string     `json:"map"`
	WinningTeam      string     `json:"winning_team"` // "A" or "B"
	WinningTeamScore int        `json:"winning_team_score"`
	LosingTeamScore  int        `json:"losing_team_score"`
	MatchType        string     `json:"match_type"`          // Legacy field for backward compatibility
	GameMode         *GameMode  `json:"game_mode,omitempty"` // Detailed game mode information
	StartTimestamp   *time.Time `json:"start_timestamp,omitempty"`
	EndTimestamp     *time.Time `json:"end_timestamp,omitempty"`
	TotalRounds      int        `json:"total_rounds"`
	PlaybackTicks    int        `json:"playback_ticks"` // Match duration in ticks from demo header
}

// GameMode represents the detected game mode
type GameMode struct {
	Mode        string `json:"mode"`         // "premier", "competitive", "wingman", "casual", "unknown"
	DisplayName string `json:"display_name"` // Human-readable name
	MaxRounds   int    `json:"max_rounds"`   // Maximum rounds for this mode
	HasHalftime bool   `json:"has_halftime"` // Whether this mode has halftime
}

type ParsedDemoData struct {
	Match             Match              `json:"match"`
	Players           []Player           `json:"players"`
	GunfightEvents    []GunfightEvent    `json:"gunfight_events"`
	GrenadeEvents     []GrenadeEvent     `json:"grenade_events"`
	RoundEvents       []RoundEvent       `json:"round_events"`
	DamageEvents      []DamageEvent      `json:"damage_events"`
	PlayerRoundEvents []PlayerRoundEvent `json:"player_round_events"`
	PlayerMatchEvents []PlayerMatchEvent `json:"player_match_events"`
}

// ParseDemoRequest represents a request with an uploaded demo file
type ParseDemoRequest struct {
	JobID                 string                `form:"job_id"`
	ProgressCallbackURL   string                `form:"progress_callback_url" binding:"required"`
	CompletionCallbackURL string                `form:"completion_callback_url" binding:"required"`
	DemoFile              *multipart.FileHeader `form:"demo_file" binding:"required"`
}

type ParseDemoResponse struct {
	Success bool   `json:"success"`
	JobID   string `json:"job_id"`
	Message string `json:"message"`
	Error   string `json:"error,omitempty"`
}

type ProgressUpdate struct {
	JobID          string                 `json:"job_id"`
	Status         string                 `json:"status"`
	Progress       int                    `json:"progress"`
	CurrentStep    string                 `json:"current_step"`
	ErrorMessage   *string                `json:"error_message,omitempty"`
	StepProgress   int                    `json:"step_progress"`
	TotalSteps     int                    `json:"total_steps"`
	CurrentStepNum int                    `json:"current_step_num"`
	StartTime      time.Time              `json:"start_time"`
	LastUpdateTime time.Time              `json:"last_update_time"`
	ErrorCode      *string                `json:"error_code,omitempty"`
	Context        map[string]interface{} `json:"context,omitempty"`
	IsFinal        bool                   `json:"is_final"`
}

type CompletionData struct {
	JobID     string         `json:"job_id"`
	Status    string         `json:"status"`
	MatchData ParsedDemoData `json:"match_data"`
	Error     string         `json:"error,omitempty"`
}

// Global Types

type ProcessingJob struct {
	JobID                 string
	TempFilePath          string // Path to temporary uploaded file
	ProgressCallbackURL   string
	CompletionCallbackURL string
	Status                string
	Progress              int
	CurrentStep           string
	ErrorMessage          string
	StartTime             time.Time
	MatchData             *ParsedDemoData
	ErrorCode             string
	LastUpdateTime        time.Time
	StepProgress          int
	TotalSteps            int
	CurrentStepNum        int
	Context               map[string]interface{}
	IsFinal               bool
}

type MatchState struct {
	CurrentRound       int
	TotalRounds        int
	RoundStartTick     int64
	RoundEndTick       int64
	Players            map[string]*Player
	RoundEvents        []RoundEvent
	GunfightEvents     []GunfightEvent
	GrenadeEvents      []GrenadeEvent
	DamageEvents       []DamageEvent
	PlayerRoundEvents  []PlayerRoundEvent
	PlayerMatchEvents  []PlayerMatchEvent
	CurrentRoundKills  int
	CurrentRoundDeaths int
	FirstKillPlayer    *string
	FirstDeathPlayer   *string
}

type PlayerState struct {
	SteamID          string
	Name             string
	Team             string
	CurrentHP        int
	CurrentArmor     int
	IsFlashed        bool
	CurrentWeapon    string
	EquipmentValue   int
	Position         Position
	AimDirection     Vector
	Kills            int
	Deaths           int
	Assists          int
	Headshots        int
	Wallbangs        int
	FirstKills       int
	FirstDeaths      int
	TotalDamage      int
	DamageTaken      int
	EffectiveFlashes int
	SmokesUsed       int
	MolotovsUsed     int
	FlashbangsUsed   int
}

// Constants

const (
	WeaponAK47         = "ak47"
	WeaponM4A1         = "m4a1"
	WeaponM4A4         = "m4a4"
	WeaponAWP          = "awp"
	WeaponDeagle       = "deagle"
	WeaponUSP          = "usp_silencer"
	WeaponGlock        = "glock"
	WeaponP250         = "p250"
	WeaponTec9         = "tec9"
	WeaponFiveSeven    = "fiveseven"
	WeaponCZ75         = "cz75a"
	WeaponScout        = "ssg08"
	WeaponAUG          = "aug"
	WeaponSG556        = "sg556"
	WeaponFamas        = "famas"
	WeaponGalil        = "galilar"
	WeaponMP9          = "mp9"
	WeaponMAC10        = "mac10"
	WeaponUMP45        = "ump45"
	WeaponP90          = "p90"
	WeaponBizon        = "bizon"
	WeaponNova         = "nova"
	WeaponXM1014       = "xm1014"
	WeaponMAG7         = "mag7"
	WeaponSawedOff     = "sawedoff"
	WeaponM249         = "m249"
	WeaponNegev        = "negev"
	WeaponKnife        = "knife"
	WeaponHEGrenade    = "hegrenade"
	WeaponFlashbang    = "flashbang"
	WeaponSmokeGrenade = "smokegrenade"
	WeaponMolotov      = "molotov"
	WeaponIncendiary   = "incendiary"
	WeaponDecoy        = "decoy"
)

const (
	GrenadeTypeHE         = "hegrenade"
	GrenadeTypeFlash      = "flashbang"
	GrenadeTypeSmoke      = "smokegrenade"
	GrenadeTypeMolotov    = "molotov"
	GrenadeTypeIncendiary = "incendiary"
	GrenadeTypeDecoy      = "decoy"
)

const (
	ThrowTypeLineup   = "lineup"
	ThrowTypeReaction = "reaction"
	ThrowTypePreAim   = "pre_aim"
	ThrowTypeUtility  = "utility"
)

const (
	MatchTypeValve    = "valve"
	MatchTypeHLTV     = "hltv"
	MatchTypeFaceit   = "faceit"
	MatchTypeESPortal = "esportal"
	MatchTypeOther    = "other"
)

// Game timing constants
const (
	CS2FreezeTime = 15 // Freeze time duration in seconds for CS2
)

// Damage assist constants
const (
	DamageAssistThreshold = 41
)

const (
	BufferDuration      = 1
	MolotovDuration     = 7 + BufferDuration
	IncendiaryDuration  = 5.5 + BufferDuration
	GrenadeDamageWindow = 1.5 + BufferDuration
)

// Trade constants
const (
	TradeTimeWindowSeconds = 3.0   // 3 seconds window for trades
	TradeDistanceThreshold = 250.0 // 250 in-game units distance for trade eligibility
)

// Economy constants
const (
	EcoThreshold      = 2000 // Below or equal to this value = eco round
	ForceBuyThreshold = 4000 // Between eco and this value = force buy round
	// Above ForceBuyThreshold = full buy round
)

// Impact Rating constants
const (
	// Team strength calculation weights
	ManCountWeight  = 0.6  // Weight for man count in team strength
	EquipmentWeight = 0.4  // Weight for equipment value in team strength
	BasePlayerValue = 2000 // Base value per alive player

	// Strength differential multiplier
	StrengthDiffMultiplier = 0.5 // Multiplier for strength differential impact

	// Context multipliers
	OpeningDuelMultiplier  = 1.5 // First gunfight bonus
	WonClutchMultiplier    = 2.0 // Won clutch bonus
	FailedClutchMultiplier = 0.5 // Failed clutch penalty
	StandardMultiplier     = 1.0 // Standard action

	// Assist impact weights
	AssistWeight      = 0.4 // Assister gets 40% of attacker's impact
	FlashAssistWeight = 0.2 // Flash assister gets 20% of attacker's impact

	// Base action impact values
	BaseKillImpact        = 100.0  // Base impact for a kill
	BaseDeathImpact       = -100.0 // Base impact for a death
	BaseAssistImpact      = 50.0   // Base impact for an assist
	BaseFlashAssistImpact = 25.0   // Base impact for a flash assist

	// Impact percentage calculation
	MaxPracticalImpact        = 100.0  // Practical maximum impact for percentage calculation
	MaxPossibleImpactPerRound = 1000.0 // Maximum possible impact per round for swing percentage

	// Round swing percentage calculation
	TeamMaxImpactPerRound   = 500.0 // Theoretical maximum team impact per round
	RoundWinOutcomeBonus    = 0.3   // Bonus multiplier for round wins
	RoundLossOutcomePenalty = -0.1  // Penalty multiplier for round losses
)

// Equipment value mapping for CS:GO/CS2 weapons using EquipmentType constants
var EquipmentValues = map[int]int{
	// Pistols
	1:  200, // EqP2000
	2:  200, // EqGlock
	3:  300, // EqP250
	4:  700, // EqDeagle
	5:  500, // EqFiveSeven
	6:  300, // EqDualBerettas
	7:  500, // EqTec9
	8:  500, // EqCZ
	9:  200, // EqUSP
	10: 600, // EqRevolver

	// SMGs
	101: 1500, // EqMP7
	102: 1250, // EqMP9
	103: 1400, // EqBizon
	104: 1050, // EqMac10
	105: 1200, // EqUMP
	106: 2350, // EqP90
	107: 1500, // EqMP5

	// Heavy
	201: 1100, // EqSawedOff
	202: 1050, // EqNova
	203: 1300, // EqMag7
	204: 2000, // EqXM1014
	205: 5200, // EqM249
	206: 1700, // EqNegev

	// Rifles
	301: 1800, // EqGalil
	302: 1950, // EqFamas
	303: 2700, // EqAK47
	304: 2900, // EqM4A4
	305: 2900, // EqM4A1
	306: 1700, // EqScout
	307: 3000, // EqSG556
	308: 3300, // EqAUG
	309: 4750, // EqAWP
	310: 5000, // EqScar20
	311: 5000, // EqG3SG1

	// Equipment
	401: 200,  // EqZeus
	402: 650,  // EqKevlar
	403: 1000, // EqHelmet (Kevlar + Helmet)
	404: 0,    // EqBomb
	405: 0,    // EqKnife
	406: 400,  // EqDefuseKit

	// Grenades
	501: 50,  // EqDecoy
	502: 400, // EqMolotov
	503: 500, // EqIncendiary
	504: 200, // EqFlash
	505: 300, // EqSmoke
	506: 300, // EqHE
}

var GrenadesEquipmentIDs = map[int]bool{
	501: true,
	502: true,
	503: true,
	504: true,
	505: true,
	506: true,
}

const (
	// Job Lifecycle Statuses
	StatusQueued           = "Queued"
	StatusValidating       = "Validating"
	StatusUploading        = "Uploading"
	StatusInitializing     = "Initializing"
	StatusParsing          = "Parsing"
	StatusProcessingEvents = "ProcessingEvents"
	StatusSendingMetadata  = "SendingMetadata"
	StatusSendingEvents    = "SendingEvents"
	StatusFinalizing       = "Finalizing"
	StatusCompleted        = "Completed"
	StatusFailed           = "Failed"

	// Error-Specific Statuses
	StatusValidationFailed = "ValidationFailed"
	StatusUploadFailed     = "UploadFailed"
	StatusParseFailed      = "ParseFailed"
	StatusCallbackFailed   = "CallbackFailed"
	StatusTimeout          = "Timeout"
	StatusCancelled        = "Cancelled"
)

// Helper functions

// GetEquipmentValue returns the monetary value of a specific equipment item
func GetEquipmentValue(equipmentType int) int {
	if value, exists := EquipmentValues[equipmentType]; exists {
		return value
	}
	return 0 // Unknown equipment
}

// CalculateTotalEquipmentValue calculates the total value of all equipment in a player's inventory
func CalculateTotalEquipmentValue(inventory []int) int {
	total := 0
	for _, item := range inventory {
		total += GetEquipmentValue(item)
	}
	return total
}

func CalculateDistance(pos1, pos2 Position) float64 {
	dx := pos1.X - pos2.X
	dy := pos1.Y - pos2.Y
	dz := pos1.Z - pos2.Z
	return math.Sqrt(dx*dx + dy*dy + dz*dz)
}

func NormalizeVector(v Vector) Vector {
	length := math.Sqrt(v.X*v.X + v.Y*v.Y + v.Z*v.Z)
	if length == 0 {
		return Vector{X: 0, Y: 0, Z: 0}
	}
	return Vector{
		X: v.X / length,
		Y: v.Y / length,
		Z: v.Z / length,
	}
}

func SteamIDToString(steamID uint64) string {
	return fmt.Sprintf("%d", steamID)
}

func StringToSteamID(steamIDString string) uint64 {
	var steamID uint64
	fmt.Sscanf(steamIDString, "%d", &steamID)
	return steamID
}

// StepManager manages progress tracking with granular steps
type StepManager struct {
	TotalSteps     int
	CurrentStepNum int
	StepProgress   int
	StartTime      time.Time
	LastUpdateTime time.Time
	Context        map[string]interface{}
}

// NewStepManager creates a new step manager with the given total rounds
func NewStepManager(totalRounds int) *StepManager {
	return &StepManager{
		TotalSteps:     18 + totalRounds, // 18 base steps + rounds
		CurrentStepNum: 1,
		StepProgress:   0,
		StartTime:      time.Now(),
		LastUpdateTime: time.Now(),
		Context:        make(map[string]interface{}),
	}
}

// UpdateStep updates the current step and resets step progress
func (sm *StepManager) UpdateStep(stepNum int, stepName string) {
	sm.CurrentStepNum = stepNum
	sm.StepProgress = 0
	sm.LastUpdateTime = time.Now()
	sm.Context["current_step_name"] = stepName
}

// UpdateStepProgress updates the progress within the current step
func (sm *StepManager) UpdateStepProgress(progress int, context map[string]interface{}) {
	sm.StepProgress = progress
	sm.LastUpdateTime = time.Now()

	// Merge context
	for k, v := range context {
		sm.Context[k] = v
	}
}

// GetOverallProgress calculates the overall progress percentage
func (sm *StepManager) GetOverallProgress() int {
	if sm.TotalSteps == 0 {
		return 0
	}

	// Calculate progress: (completed steps * 100 + current step progress) / total steps
	completedSteps := sm.CurrentStepNum - 1
	overallProgress := (completedSteps*100 + sm.StepProgress) / sm.TotalSteps

	if overallProgress > 100 {
		return 100
	}
	return overallProgress
}

// PlayerTickData represents player position and aim data for each tick
type PlayerTickData struct {
	ID        uint64    `gorm:"primaryKey;autoIncrement" json:"id"`
	MatchID   string    `gorm:"type:varchar(36);not null;index:idx_match_tick_player" json:"match_id"`
	Tick      int64     `gorm:"not null;index:idx_match_tick_player" json:"tick"`
	PlayerID  string    `gorm:"type:varchar(20);not null;index:idx_match_tick_player" json:"player_id"`
	Team      string    `gorm:"type:varchar(10);not null" json:"team"`
	PositionX float64   `gorm:"type:double;not null" json:"position_x"`
	PositionY float64   `gorm:"type:double;not null" json:"position_y"`
	PositionZ float64   `gorm:"type:double;not null" json:"position_z"`
	AimX      float64   `gorm:"type:double;not null" json:"aim_x"`
	AimY      float64   `gorm:"type:double;not null" json:"aim_y"`
	CreatedAt time.Time `gorm:"autoCreateTime" json:"created_at"`
	UpdatedAt time.Time `gorm:"autoUpdateTime" json:"updated_at"`
}

// TableName specifies the table name for GORM
func (PlayerTickData) TableName() string {
	return "player_tick_data"
}
