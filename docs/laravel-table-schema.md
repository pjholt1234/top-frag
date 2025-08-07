# Laravel Table Schema

## Overview
This document defines the database schema for the Counter-Strike stats platform. All tables use Laravel's migration system and include timestamps (`created_at`, `updated_at`).

**Note:** All enums are stored as strings in the database and cast to Laravel enums in the application layer for easier management.

## Tables

### 1. matches
Stores basic match information and metadata.

```sql
CREATE TABLE matches (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    match_hash VARCHAR(64) UNIQUE NOT NULL, -- SHA256 hash of demo file content for deduplication
    map VARCHAR(50) NOT NULL,
    winning_team_score INT NOT NULL,
    losing_team_score INT NOT NULL,
    match_type VARCHAR(20) NOT NULL, -- 'hltv', 'mm', 'faceit', 'esportal', 'other'
    start_timestamp TIMESTAMP NULL,
    end_timestamp TIMESTAMP NULL,
    total_rounds INT NOT NULL,
    total_fight_events INT NOT NULL DEFAULT 0,
    total_grenade_events INT NOT NULL DEFAULT 0,
    processing_status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
    error_message TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_match_hash (match_hash),
    INDEX idx_map (map),
    INDEX idx_match_type (match_type),
    INDEX idx_processing_status (processing_status)
);
```

**Match Hash Explanation:**
The `match_hash` is a SHA256 hash of the demo file content. This provides:
- **Deduplication**: Same demo uploaded multiple times = same hash
- **Unique identification**: Each unique match has a stable identifier
- **Cross-user sharing**: Multiple users can upload the same demo, only processed once

### 2. players
Stores player information across all matches.

```sql
CREATE TABLE players (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    steam_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_matches INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_steam_id (steam_id),
    INDEX idx_name (name)
);
```

### 3. match_players
Junction table linking players to matches with their team information.

```sql
CREATE TABLE match_players (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    match_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    team VARCHAR(10) NOT NULL, -- 'CT' or 'T'
    side_start VARCHAR(10) NOT NULL, -- Which side they started on ('CT' or 'T')
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY unique_match_player (match_id, player_id),
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    
    INDEX idx_match_id (match_id),
    INDEX idx_player_id (player_id),
    INDEX idx_team (team)
);
```

### 4. gunfight_events
Stores every player interaction/fight event.

**Note:** This table is expected to be very large (1000+ events per match). Consider partitioning by `match_id` for production use:
```sql
-- Example partitioning (add after table creation)
ALTER TABLE gunfight_events PARTITION BY HASH(match_id) PARTITIONS 10;
```

```sql
CREATE TABLE gunfight_events (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    match_id BIGINT UNSIGNED NOT NULL,
    round_number INT NOT NULL,
    round_time INT NOT NULL, -- Seconds into the round
    tick_timestamp BIGINT NOT NULL,
    
    player_1_id BIGINT UNSIGNED NOT NULL,
    player_2_id BIGINT UNSIGNED NOT NULL,
    
    player_1_hp_start INT NOT NULL,
    player_2_hp_start INT NOT NULL,
    player_1_armor INT NOT NULL DEFAULT 0,
    player_2_armor INT NOT NULL DEFAULT 0,
    player_1_flashed BOOLEAN NOT NULL DEFAULT FALSE,
    player_2_flashed BOOLEAN NOT NULL DEFAULT FALSE,
    player_1_weapon VARCHAR(50) NOT NULL,
    player_2_weapon VARCHAR(50) NOT NULL,
    player_1_equipment_value INT NOT NULL DEFAULT 0,
    player_2_equipment_value INT NOT NULL DEFAULT 0,
    
    player_1_x FLOAT NOT NULL,
    player_1_y FLOAT NOT NULL,
    player_1_z FLOAT NOT NULL,
    player_2_x FLOAT NOT NULL,
    player_2_y FLOAT NOT NULL,
    player_2_z FLOAT NOT NULL,
    
    distance FLOAT NOT NULL, -- Distance between players
    headshot BOOLEAN NOT NULL DEFAULT FALSE,
    wallbang BOOLEAN NOT NULL DEFAULT FALSE,
    penetrated_objects INT NOT NULL DEFAULT 0,
    
    victor_id BIGINT UNSIGNED NULL, -- NULL if no clear winner
    damage_dealt INT NOT NULL DEFAULT 0,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_1_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (player_2_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (victor_id) REFERENCES players(id) ON DELETE SET NULL,
    
    INDEX idx_match_round (match_id, round_number),
    INDEX idx_match_tick (match_id, tick_timestamp),
    INDEX idx_players (player_1_id, player_2_id),
    INDEX idx_victor (victor_id),
    INDEX idx_round_time (round_number, round_time)
);
```

### 5. grenade_events
Stores all grenade usage and their effects.

```sql
CREATE TABLE grenade_events (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    match_id BIGINT UNSIGNED NOT NULL,
    round_number INT NOT NULL,
    round_time INT NOT NULL, -- Seconds into the round
    tick_timestamp BIGINT NOT NULL,
    
    player_id BIGINT UNSIGNED NOT NULL,
    grenade_type VARCHAR(20) NOT NULL, -- 'hegrenade', 'flashbang', 'smokegrenade', 'molotov', 'incendiary', 'decoy'
    
    -- Player position when throwing
    player_x FLOAT NOT NULL,
    player_y FLOAT NOT NULL,
    player_z FLOAT NOT NULL,
    
    -- Player aim direction (normalized vector)
    player_aim_x FLOAT NOT NULL,
    player_aim_y FLOAT NOT NULL,
    player_aim_z FLOAT NOT NULL,
    
    -- Grenade final position
    grenade_final_x FLOAT NULL,
    grenade_final_y FLOAT NULL,
    grenade_final_z FLOAT NULL,
    
    -- Effects
    damage_dealt INT NOT NULL DEFAULT 0,
    flash_duration FLOAT NULL, -- Seconds of flash
    affected_players JSON NULL, -- Array of affected player IDs and their effects
    
    throw_type VARCHAR(20) NOT NULL DEFAULT 'utility', -- 'lineup', 'reaction', 'pre_aim', 'utility'
    effectiveness_rating INT NULL, -- 1-10 scale, calculated later
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    
    INDEX idx_match_round (match_id, round_number),
    INDEX idx_match_tick (match_id, tick_timestamp),
    INDEX idx_player (player_id),
    INDEX idx_grenade_type (grenade_type),
    INDEX idx_round_time (round_number, round_time)
);
```

### 6. match_summaries
Pre-computed match-level statistics for fast queries.

```sql
CREATE TABLE match_summaries (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    match_id BIGINT UNSIGNED UNIQUE NOT NULL,
    
    -- Basic stats
    total_kills INT NOT NULL DEFAULT 0,
    total_deaths INT NOT NULL DEFAULT 0,
    total_assists INT NOT NULL DEFAULT 0,
    total_headshots INT NOT NULL DEFAULT 0,
    total_wallbangs INT NOT NULL DEFAULT 0,
    total_damage INT NOT NULL DEFAULT 0,
    
    -- Utility stats
    total_he_damage INT NOT NULL DEFAULT 0,
    total_effective_flashes INT NOT NULL DEFAULT 0,
    total_smokes_used INT NOT NULL DEFAULT 0,
    total_molotovs_used INT NOT NULL DEFAULT 0,
    
    -- Round stats
    total_first_kills INT NOT NULL DEFAULT 0,
    total_first_deaths INT NOT NULL DEFAULT 0,
    total_clutches_1v1_attempted INT NOT NULL DEFAULT 0,
    total_clutches_1v1_successful INT NOT NULL DEFAULT 0,
    total_clutches_1v2_attempted INT NOT NULL DEFAULT 0,
    total_clutches_1v2_successful INT NOT NULL DEFAULT 0,
    total_clutches_1v3_attempted INT NOT NULL DEFAULT 0,
    total_clutches_1v3_successful INT NOT NULL DEFAULT 0,
    total_clutches_1v4_attempted INT NOT NULL DEFAULT 0,
    total_clutches_1v4_successful INT NOT NULL DEFAULT 0,
    total_clutches_1v5_attempted INT NOT NULL DEFAULT 0,
    total_clutches_1v5_successful INT NOT NULL DEFAULT 0,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
);
```

### 7. player_match_summaries
Pre-computed player statistics per match for fast queries.

**Note:** This table is separate from `match_players` to keep the pivot table clean and provide dedicated indexes for summary queries.

```sql
CREATE TABLE player_match_summaries (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    match_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    
    -- Basic stats
    kills INT NOT NULL DEFAULT 0,
    deaths INT NOT NULL DEFAULT 0,
    assists INT NOT NULL DEFAULT 0,
    headshots INT NOT NULL DEFAULT 0,
    wallbangs INT NOT NULL DEFAULT 0,
    first_kills INT NOT NULL DEFAULT 0,
    first_deaths INT NOT NULL DEFAULT 0,
    
    -- Damage stats
    total_damage INT NOT NULL DEFAULT 0,
    average_damage_per_round FLOAT NOT NULL DEFAULT 0,
    damage_taken INT NOT NULL DEFAULT 0,
    
    -- Utility stats
    he_damage INT NOT NULL DEFAULT 0,
    effective_flashes INT NOT NULL DEFAULT 0,
    smokes_used INT NOT NULL DEFAULT 0,
    molotovs_used INT NOT NULL DEFAULT 0,
    flashbangs_used INT NOT NULL DEFAULT 0,
    
    -- Clutch stats
    clutches_1v1_attempted INT NOT NULL DEFAULT 0,
    clutches_1v1_successful INT NOT NULL DEFAULT 0,
    clutches_1v2_attempted INT NOT NULL DEFAULT 0,
    clutches_1v2_successful INT NOT NULL DEFAULT 0,
    clutches_1v3_attempted INT NOT NULL DEFAULT 0,
    clutches_1v3_successful INT NOT NULL DEFAULT 0,
    clutches_1v4_attempted INT NOT NULL DEFAULT 0,
    clutches_1v4_successful INT NOT NULL DEFAULT 0,
    clutches_1v5_attempted INT NOT NULL DEFAULT 0,
    clutches_1v5_successful INT NOT NULL DEFAULT 0,
    
    -- Calculated stats
    kd_ratio FLOAT NOT NULL DEFAULT 0,
    headshot_percentage FLOAT NOT NULL DEFAULT 0,
    clutch_success_rate FLOAT NOT NULL DEFAULT 0,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY unique_player_match (match_id, player_id),
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    
    INDEX idx_match_id (match_id),
    INDEX idx_player_id (player_id)
);
```

## Indexes Summary

### Performance Indexes
- All foreign keys are indexed
- `match_id` + `round_number` for round-based queries
- `match_id` + `tick_timestamp` for chronological queries
- `player_id` for player-specific queries
- `grenade_type` for utility analysis
- `processing_status` for job management

### Composite Indexes
- `(match_id, round_number)` - Most common query pattern
- `(match_id, tick_timestamp)` - Chronological analysis
- `(player_1_id, player_2_id)` - Player interaction analysis

## Partitioning Strategy

### gunfight_events Table
This table will be the largest and should be partitioned for production:

```sql
-- Partition by match_id hash (recommended for this use case)
ALTER TABLE gunfight_events PARTITION BY HASH(match_id) PARTITIONS 10;

-- Alternative: Partition by date if you have many matches per day
-- ALTER TABLE gunfight_events PARTITION BY RANGE (YEAR(created_at)) (
--     PARTITION p2024 VALUES LESS THAN (2025),
--     PARTITION p2025 VALUES LESS THAN (2026)
-- );
```

**Benefits of partitioning:**
- Faster queries when filtering by `match_id`
- Better parallel processing
- Easier maintenance and backup
- Improved cache efficiency

## Notes

1. **Match Hash**: SHA256 hash of demo file content for deduplication and unique identification
2. **String Enums**: All enum-like fields use VARCHAR with Laravel enum casting for easier management
3. **JSON Fields**: Used for complex data like affected players in grenade events
4. **Pre-computed Summaries**: Enable fast queries without complex aggregations
5. **Separate Summary Tables**: Keep pivot tables clean and provide dedicated performance
6. **Cascade Deletes**: When a match is deleted, all related data is removed
7. **Timestamps**: All tables include Laravel's standard timestamps 