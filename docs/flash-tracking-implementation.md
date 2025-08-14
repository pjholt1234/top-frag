# Flash Tracking Implementation

## Overview

This document describes the implementation of flash tracking for CS2 demo parsing in the Go parser service. The implementation tracks flashbang effects and provides detailed statistics about friendly and enemy players affected.

## Database Schema

The Laravel backend already has the required database fields in the `grenade_events` table:

- `friendly_flash_duration` (float) - Total flash duration on teammates
- `enemy_flash_duration` (float) - Total flash duration on enemies  
- `friendly_players_affected` (int) - Number of teammates affected
- `enemy_players_affected` (int) - Number of enemies affected

## Implementation Approach

The flash tracking system works by:

1. **Tracking Flash Explosions**: When a `FlashExplode` event occurs, we create a `FlashEffect` tracker
2. **Tracking Affected Players**: When `PlayerFlashed` events occur, we associate them with the most recent flash effect
3. **Fallback Player State Check**: If no `PlayerFlashed` events are triggered, we check all players' flash state when the grenade is destroyed
4. **Matching to Grenade Events**: When a flashbang grenade is destroyed, we attach the flash tracking data to the grenade event

## Key Components

### FlashEffect Struct

```go
type FlashEffect struct {
    EntityID         int
    ThrowerSteamID   string
    ExplosionTick    int64
    AffectedPlayers  map[uint64]*PlayerFlashInfo
    FriendlyDuration float64
    EnemyDuration    float64
    FriendlyCount    int
    EnemyCount       int
}
```

### PlayerFlashInfo Struct

```go
type PlayerFlashInfo struct {
    SteamID       string
    Team          string
    FlashDuration float64
    IsFriendly    bool
}
```

### Updated GrenadeEvent Struct

The `GrenadeEvent` struct now includes flash tracking fields:

```go
type GrenadeEvent struct {
    // ... existing fields ...
    
    // Flash tracking fields
    FriendlyFlashDuration   *float64 `json:"friendly_flash_duration,omitempty"`
    EnemyFlashDuration      *float64 `json:"enemy_flash_duration,omitempty"`
    FriendlyPlayersAffected int      `json:"friendly_players_affected"`
    EnemyPlayersAffected    int      `json:"enemy_players_affected"`
}
```

## Event Handling Strategy

The system uses a dedicated approach for flashbangs to avoid duplicate events:

1. **FlashExplode events** create grenade events immediately
2. **PlayerFlashed events** update those grenade events with flash data
3. **GrenadeProjectileDestroy events** skip flashbangs entirely to prevent duplicates

This ensures that each flashbang explosion results in exactly one grenade event with complete flash tracking data.

## Event Handlers

### HandleFlashExplode

- Creates a new `FlashEffect` tracker when a flashbang explodes
- **Checks for existing flash effects** to prevent duplicates for the same entity ID
- **Creates a grenade event immediately** for the flash explosion
- Stores the entity ID, thrower, and explosion tick
- Logs the flash explosion for debugging

### HandlePlayerFlashed

- Called when a player gets flashed
- Finds the most recent flash effect within a time window (1 second = 64 ticks)
- Determines if the player is friendly or enemy to the thrower
- Updates the flash effect with player information and totals
- **Updates the corresponding grenade event** with flash tracking data

### HandleGrenadeProjectileDestroy (Updated)

- **Skips flashbang grenades entirely** - they are handled in HandleFlashExplode
- For other grenade types, looks for the most recent throw information
- Creates grenade events for non-flashbang grenades only
- No longer handles flash tracking data

### updateGrenadeEventWithFlashData (New)

- Finds the grenade event that corresponds to a flash effect
- Updates the grenade event with flash tracking data
- Matches by thrower Steam ID and timing

### checkAllPlayersForFlashDuration (New)

- Fallback method that checks all tracked players for flash duration
- Used when `PlayerFlashed` events are not triggered or not found
- Uses player state to determine if players are currently flashed
- Calculates friendly/enemy statistics based on team assignments

## Team Assignment Logic

The system determines friendly/enemy relationships by:

1. Getting the assigned team for the thrower
2. Getting the assigned team for the affected player
3. Comparing the teams to determine if they're on the same side

## Time Window Logic

Flash effects are matched using a time window approach:

- **PlayerFlashed events**: Look for flash effects within 1 second (64 ticks) of the current tick
- **GrenadeProjectileDestroy events**: Look for flash effects within 2 seconds (128 ticks) of the current tick

This accounts for the fact that flash effects can occur slightly after the explosion and grenade destruction can happen at different times.

## Data Flow

1. **FlashExplode** → Creates FlashEffect tracker + Creates grenade event
2. **PlayerFlashed** → Updates FlashEffect with player data + Updates grenade event with flash data
3. **GrenadeProjectileDestroy** → Skips flashbangs, handles other grenade types only
4. **Batch Sender** → Sends flash tracking data to Laravel backend

## Event Handling Strategy

The system uses a dedicated approach for flashbangs to avoid duplicate events:

1. **FlashExplode events** create grenade events immediately
2. **PlayerFlashed events** update those grenade events with flash data
3. **GrenadeProjectileDestroy events** skip flashbangs entirely to prevent duplicates

This ensures that each flashbang explosion results in exactly one grenade event with complete flash tracking data.

## Testing

The implementation includes a basic test that verifies:

- Flash explosion events are properly tracked
- Flash effect data is correctly stored
- Entity ID and thrower information is preserved

Note: Full PlayerFlashed event testing requires properly initialized Player objects from the demoinfocs library.

## Usage

The flash tracking data will be automatically included in grenade events when:

1. A flashbang is thrown and explodes
2. Players are affected by the flash (via events or state check)
3. The grenade projectile is destroyed

The data will be sent to the Laravel backend with the following fields:

- `friendly_flash_duration`: Total flash duration on teammates
- `enemy_flash_duration`: Total flash duration on enemies
- `friendly_players_affected`: Number of teammates affected
- `enemy_players_affected`: Number of enemies affected

## Debugging

The implementation includes extensive logging to help debug flash tracking:

- Flash explosion events are logged with entity ID and thrower information
- PlayerFlashed events are logged with player and duration information
- Flash effect matching is logged with detailed timing information
- Fallback player state checks are logged with found players and statistics

## Limitations

1. **Time Window Matching**: The system uses time windows to match flash effects, which may not be 100% accurate in edge cases
2. **Team Assignment**: Relies on the existing team assignment logic which may not be perfect in all scenarios
3. **Player Initialization**: Testing PlayerFlashed events requires fully initialized Player objects from demoinfocs
4. **Fallback Duration**: When using fallback logic, a default flash duration of 2.0 seconds is used

## Future Improvements

1. **Entity ID Matching**: If demoinfocs provides better entity ID consistency, we could use direct entity ID matching instead of time windows
2. **Enhanced Team Logic**: Improve team assignment logic for more accurate friendly/enemy detection
3. **Flash Duration Validation**: Add validation to ensure flash durations are reasonable
4. **Performance Optimization**: Consider optimizing the flash effect lookup for high-frequency events
5. **Real Flash Duration**: Access actual flash duration from player state instead of using default values
