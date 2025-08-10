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
	SteamID string `json:"steam_id"`
	Name    string `json:"name"`
	Team    string `json:"team"` // "A" or "B" (arbitrary team assignment)
}

type GunfightEvent struct {
	RoundNumber    int       `json:"round_number"`
	RoundTime      int       `json:"round_time"`
	TickTimestamp  int64     `json:"tick_timestamp"`
	
	Player1SteamID string    `json:"player_1_steam_id"`
	Player2SteamID string    `json:"player_2_steam_id"`
	
	Player1HPStart int       `json:"player_1_hp_start"`
	Player2HPStart int       `json:"player_2_hp_start"`
	Player1Armor   int       `json:"player_1_armor"`
	Player2Armor   int       `json:"player_2_armor"`
	Player1Flashed bool      `json:"player_1_flashed"`
	Player2Flashed bool      `json:"player_2_flashed"`
	Player1Weapon  string    `json:"player_1_weapon"`
	Player2Weapon  string    `json:"player_2_weapon"`
	Player1EquipValue int    `json:"player_1_equipment_value"`
	Player2EquipValue int    `json:"player_2_equipment_value"`
	
	Player1Position Position `json:"player_1_position"`
	Player2Position Position `json:"player_2_position"`
	
	Distance         float64 `json:"distance"`
	Headshot         bool    `json:"headshot"`
	Wallbang         bool    `json:"wallbang"`
	PenetratedObjects int    `json:"penetrated_objects"`
	
	VictorSteamID    *string `json:"victor_steam_id,omitempty"`
	DamageDealt      int     `json:"damage_dealt"`
}

type AffectedPlayer struct {
	SteamID       string   `json:"steam_id"`
	FlashDuration *float64 `json:"flash_duration,omitempty"`
	DamageTaken   *int     `json:"damage_taken,omitempty"`
}

type GrenadeEvent struct {
	RoundNumber    int       `json:"round_number"`
	RoundTime      int       `json:"round_time"`
	TickTimestamp  int64     `json:"tick_timestamp"`
	
	PlayerSteamID   string    `json:"player_steam_id"`
	GrenadeType     string    `json:"grenade_type"`
	
	PlayerPosition  Position  `json:"player_position"`
	PlayerAim       Vector    `json:"player_aim"`
	
	GrenadeFinalPosition *Position `json:"grenade_final_position,omitempty"`
	
	DamageDealt     int       `json:"damage_dealt"`
	FlashDuration   *float64  `json:"flash_duration,omitempty"`
	AffectedPlayers []AffectedPlayer `json:"affected_players,omitempty"`
	
	ThrowType       string    `json:"throw_type"`
}

type RoundEvent struct {
	RoundNumber    int       `json:"round_number"`
	TickTimestamp  int64     `json:"tick_timestamp"`
	EventType      string    `json:"event_type"`
	Winner         *string   `json:"winner,omitempty"`
	Duration       *int      `json:"duration,omitempty"`
}

type DamageEvent struct {
	RoundNumber    int       `json:"round_number"`
	RoundTime      int       `json:"round_time"`
	TickTimestamp  int64     `json:"tick_timestamp"`
	
	AttackerSteamID string   `json:"attacker_steam_id"`
	VictimSteamID   string   `json:"victim_steam_id"`
	
	Damage         int       `json:"damage"`
	ArmorDamage    int       `json:"armor_damage"`
	HealthDamage   int       `json:"health_damage"`
	Headshot       bool      `json:"headshot"`
	Weapon         string    `json:"weapon"`
}

type Match struct {
	Map              string     `json:"map"`
	WinningTeam      string     `json:"winning_team"`      // "A" or "B"
	WinningTeamScore int        `json:"winning_team_score"`
	LosingTeamScore  int        `json:"losing_team_score"`
	MatchType        string     `json:"match_type"`
	StartTimestamp   *time.Time `json:"start_timestamp,omitempty"`
	EndTimestamp     *time.Time `json:"end_timestamp,omitempty"`
	TotalRounds      int        `json:"total_rounds"`
}

type ParsedDemoData struct {
	Match           Match           `json:"match"`
	Players         []Player        `json:"players"`
	GunfightEvents  []GunfightEvent `json:"gunfight_events"`
	GrenadeEvents   []GrenadeEvent  `json:"grenade_events"`
	RoundEvents     []RoundEvent    `json:"round_events"`
	DamageEvents    []DamageEvent   `json:"damage_events"`
}

// ParseDemoRequest represents a request with an uploaded demo file
type ParseDemoRequest struct {
	JobID               string `form:"job_id"`
	ProgressCallbackURL string `form:"progress_callback_url" binding:"required"`
	CompletionCallbackURL string `form:"completion_callback_url" binding:"required"`
	DemoFile            *multipart.FileHeader `form:"demo_file" binding:"required"`
}

type ParseDemoResponse struct {
	Success bool   `json:"success"`
	JobID   string `json:"job_id"`
	Message string `json:"message"`
	Error   string `json:"error,omitempty"`
}

type ProgressUpdate struct {
	JobID       string `json:"job_id"`
	Status      string `json:"status"`
	Progress    int    `json:"progress"`
	CurrentStep string `json:"current_step"`
	ErrorMessage *string `json:"error_message,omitempty"`
}

type CompletionData struct {
	JobID      string         `json:"job_id"`
	Status     string         `json:"status"`
	MatchData  ParsedDemoData `json:"match_data"`
	Error      string         `json:"error,omitempty"`
}

// Global Types

type ProcessingJob struct {
	JobID               string
	TempFilePath        string // Path to temporary uploaded file
	ProgressCallbackURL string
	CompletionCallbackURL string
	Status              string
	Progress            int
	CurrentStep         string
	ErrorMessage        string
	StartTime           time.Time
	MatchData           *ParsedDemoData
}

type MatchState struct {
	CurrentRound        int
	TotalRounds         int
	RoundStartTick      int64
	RoundEndTick        int64
	Players             map[string]*Player
	RoundEvents         []RoundEvent
	GunfightEvents      []GunfightEvent
	GrenadeEvents       []GrenadeEvent
	DamageEvents        []DamageEvent
	CurrentRoundKills   int
	CurrentRoundDeaths  int
	FirstKillPlayer     *string
	FirstDeathPlayer    *string
}

type PlayerState struct {
	SteamID         string
	Name            string
	Team            string
	CurrentHP       int
	CurrentArmor    int
	IsFlashed       bool
	CurrentWeapon   string
	EquipmentValue  int
	Position        Position
	AimDirection    Vector
	Kills           int
	Deaths          int
	Assists         int
	Headshots       int
	Wallbangs       int
	FirstKills      int
	FirstDeaths     int
	TotalDamage     int
	DamageTaken     int
	HEDamage        int
	EffectiveFlashes int
	SmokesUsed      int
	MolotovsUsed    int
	FlashbangsUsed  int
}

// Constants

const (
	WeaponAK47     = "ak47"
	WeaponM4A1     = "m4a1"
	WeaponM4A4     = "m4a4"
	WeaponAWP      = "awp"
	WeaponDeagle   = "deagle"
	WeaponUSP      = "usp_silencer"
	WeaponGlock    = "glock"
	WeaponP250     = "p250"
	WeaponTec9     = "tec9"
	WeaponFiveSeven = "fiveseven"
	WeaponCZ75     = "cz75a"
	WeaponScout    = "ssg08"
	WeaponAUG      = "aug"
	WeaponSG556    = "sg556"
	WeaponFamas    = "famas"
	WeaponGalil    = "galilar"
	WeaponMP9      = "mp9"
	WeaponMAC10    = "mac10"
	WeaponUMP45    = "ump45"
	WeaponP90      = "p90"
	WeaponBizon    = "bizon"
	WeaponNova     = "nova"
	WeaponXM1014   = "xm1014"
	WeaponMAG7     = "mag7"
	WeaponSawedOff = "sawedoff"
	WeaponM249     = "m249"
	WeaponNegev    = "negev"
	WeaponKnife    = "knife"
	WeaponHEGrenade = "hegrenade"
	WeaponFlashbang = "flashbang"
	WeaponSmokeGrenade = "smokegrenade"
	WeaponMolotov  = "molotov"
	WeaponIncendiary = "incendiary"
	WeaponDecoy    = "decoy"
)

const (
	GrenadeTypeHE        = "hegrenade"
	GrenadeTypeFlash     = "flashbang"
	GrenadeTypeSmoke     = "smokegrenade"
	GrenadeTypeMolotov   = "molotov"
	GrenadeTypeIncendiary = "incendiary"
	GrenadeTypeDecoy     = "decoy"
)

const (
	ThrowTypeLineup   = "lineup"
	ThrowTypeReaction = "reaction"
	ThrowTypePreAim   = "pre_aim"
	ThrowTypeUtility  = "utility"
)

const (
	MatchTypeHLTV    = "hltv"
	MatchTypeMM      = "mm"
	MatchTypeFaceit  = "faceit"
	MatchTypeESPortal = "esportal"
	MatchTypeOther   = "other"
)

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
	return fmt.Sprintf("steam_%d", steamID)
} 