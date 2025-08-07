# Parser Service Data Types

## Overview
This document defines all the data types and structures used in the Go parser service for processing Counter-Strike demo files.

## Core Types

### Position
Represents 3D coordinates in the game world.

```go
type Position struct {
    X float64 `json:"x"`
    Y float64 `json:"y"`
    Z float64 `json:"z"`
}
```

### Vector
Represents a normalized direction vector (for aim direction).

```go
type Vector struct {
    X float64 `json:"x"`
    Y float64 `json:"y"`
    Z float64 `json:"z"`
}
```

### Player
Represents a player in the match.

```go
type Player struct {
    SteamID string `json:"steam_id"`
    Name    string `json:"name"`
    Team    string `json:"team"` // "CT" or "T"
}
```

## Event Types

### GunfightEvent
Represents a fight/interaction between two players.

```go
type GunfightEvent struct {
    RoundNumber    int       `json:"round_number"`
    RoundTime      int       `json:"round_time"` // Seconds into round
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
```

### GrenadeEvent
Represents a grenade throw and its effects.

```go
type GrenadeEvent struct {
    RoundNumber    int       `json:"round_number"`
    RoundTime      int       `json:"round_time"`
    TickTimestamp  int64     `json:"tick_timestamp"`
    
    PlayerSteamID   string    `json:"player_steam_id"`
    GrenadeType     string    `json:"grenade_type"` // hegrenade, flashbang, smokegrenade, molotov, incendiary, decoy
    
    PlayerPosition  Position  `json:"player_position"`
    PlayerAim       Vector    `json:"player_aim"`
    
    GrenadeFinalPosition *Position `json:"grenade_final_position,omitempty"`
    
    DamageDealt     int       `json:"damage_dealt"`
    FlashDuration   *float64  `json:"flash_duration,omitempty"`
    AffectedPlayers []AffectedPlayer `json:"affected_players,omitempty"`
    
    ThrowType       string    `json:"throw_type"` // lineup, reaction, pre_aim, utility
}

type AffectedPlayer struct {
    SteamID       string   `json:"steam_id"`
    FlashDuration *float64 `json:"flash_duration,omitempty"`
    DamageTaken   *int     `json:"damage_taken,omitempty"`
}
```

### RoundEvent
Represents round start/end events.

```go
type RoundEvent struct {
    RoundNumber    int       `json:"round_number"`
    TickTimestamp  int64     `json:"tick_timestamp"`
    EventType      string    `json:"event_type"` // "start", "end"
    Winner         *string   `json:"winner,omitempty"` // "CT", "T", or null for start
    Duration       *int      `json:"duration,omitempty"` // Round duration in seconds
}
```

### DamageEvent
Represents damage dealt to players.

```go
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
```

## Match Data Types

### Match
Represents the overall match information.

```go
type Match struct {
    Map              string     `json:"map"`
    WinningTeamScore int        `json:"winning_team_score"`
    LosingTeamScore  int        `json:"losing_team_score"`
    MatchType        string     `json:"match_type"` // hltv, mm, faceit, esportal, other
    StartTimestamp   *time.Time `json:"start_timestamp,omitempty"`
    EndTimestamp     *time.Time `json:"end_timestamp,omitempty"`
    TotalRounds      int        `json:"total_rounds"`
}
```

### ParsedDemoData
Complete parsed demo data structure.

```go
type ParsedDemoData struct {
    Match           Match           `json:"match"`
    Players         []Player        `json:"players"`
    GunfightEvents  []GunfightEvent `json:"gunfight_events"`
    GrenadeEvents   []GrenadeEvent  `json:"grenade_events"`
    RoundEvents     []RoundEvent    `json:"round_events"`
    DamageEvents    []DamageEvent   `json:"damage_events"`
}
```

## API Request/Response Types

### ParseDemoRequest
Request to parse a demo file.

```go
type ParseDemoRequest struct {
    DemoPath            string `json:"demo_path"`
    JobID               string `json:"job_id"`
    ProgressCallbackURL string `json:"progress_callback_url"`
    CompletionCallbackURL string `json:"completion_callback_url"`
}
```

### ParseDemoResponse
Response from parse demo request.

```go
type ParseDemoResponse struct {
    Success bool   `json:"success"`
    JobID   string `json:"job_id"`
    Message string `json:"message"`
    Error   string `json:"error,omitempty"`
}
```

### ProgressUpdate
Progress update sent to Laravel.

```go
type ProgressUpdate struct {
    JobID       string `json:"job_id"`
    Status      string `json:"status"` // pending, processing, completed, failed
    Progress    int    `json:"progress"` // 0-100
    CurrentStep string `json:"current_step"`
    ErrorMessage *string `json:"error_message,omitempty"`
}
```

### CompletionData
Final completion data sent to Laravel.

```go
type CompletionData struct {
    JobID      string         `json:"job_id"`
    Status     string         `json:"status"`
    MatchData  ParsedDemoData `json:"match_data"`
    Error      string         `json:"error,omitempty"`
}
```

## Internal Processing Types

### ProcessingJob
Internal job tracking structure.

```go
type ProcessingJob struct {
    JobID               string
    DemoPath            string
    ProgressCallbackURL string
    CompletionCallbackURL string
    Status              string
    Progress            int
    CurrentStep         string
    ErrorMessage        string
    StartTime           time.Time
    MatchData           *ParsedDemoData
}
```

### MatchState
Tracks the current state during parsing.

```go
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
```

### PlayerState
Tracks individual player state during parsing.

```go
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
```

## Constants

### Weapon Types
```go
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
```

### Grenade Types
```go
const (
    GrenadeTypeHE        = "hegrenade"
    GrenadeTypeFlash     = "flashbang"
    GrenadeTypeSmoke     = "smokegrenade"
    GrenadeTypeMolotov   = "molotov"
    GrenadeTypeIncendiary = "incendiary"
    GrenadeTypeDecoy     = "decoy"
)
```

### Throw Types
```go
const (
    ThrowTypeLineup   = "lineup"
    ThrowTypeReaction = "reaction"
    ThrowTypePreAim   = "pre_aim"
    ThrowTypeUtility  = "utility"
)
```

### Match Types
```go
const (
    MatchTypeHLTV    = "hltv"
    MatchTypeMM      = "mm"
    MatchTypeFaceit  = "faceit"
    MatchTypeESPortal = "esportal"
    MatchTypeOther   = "other"
)
```

### Processing Status
```go
const (
    StatusPending    = "pending"
    StatusProcessing = "processing"
    StatusCompleted  = "completed"
    StatusFailed     = "failed"
)
```

## Utility Functions

### Distance Calculation
```go
func CalculateDistance(pos1, pos2 Position) float64 {
    dx := pos1.X - pos2.X
    dy := pos1.Y - pos2.Y
    dz := pos1.Z - pos2.Z
    return math.Sqrt(dx*dx + dy*dy + dz*dz)
}
```

### Vector Normalization
```go
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
```

### Steam ID Conversion
```go
func SteamIDToString(steamID uint64) string {
    return fmt.Sprintf("steam_%d", steamID)
}
``` 