# API Schemas

## Parser Service API

### POST /parse-demo
Initiates demo parsing with progress tracking.

**Request:**
```json
{
  "demo_path": "/path/to/demo.dem",
  "job_id": "uuid-string",
  "progress_callback_url": "https://laravel-app.com/api/progress/{job_id}",
  "completion_callback_url": "https://laravel-app.com/api/demo-complete/{job_id}"
}
```

**Response:**
```json
{
  "success": true,
  "job_id": "uuid-string",
  "message": "Demo parsing started"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Invalid demo file path",
  "job_id": "uuid-string"
}
```

## Laravel API

### POST /api/upload-demo
Upload a demo file for processing.

**Request:**
```
Content-Type: multipart/form-data

demo_file: [binary file]
```

**Response:**
```json
{
  "success": true,
  "match_id": "uuid-string",
  "message": "Demo uploaded successfully. Processing started.",
  "estimated_processing_time": "2-3 minutes"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Invalid file format. Only .dem files are supported."
}
```

### GET /api/progress/{match_id}
Get the current processing status of a demo.

**Response:**
```json
{
  "success": true,
  "match_id": "uuid-string",
  "status": "processing", // pending, processing, processing_data, completed, failed
  "progress": 45, // 0-100
  "current_step": "Processing round 15 of 30",
  "estimated_time_remaining": "1 minute",
  "error_message": null
}
```

### POST /api/progress/{match_id}
Update the processing status (called by parser service).

**Request:**
```json
{
  "status": "processing", // pending, processing, processing_data, completed, failed
  "progress": 45,
  "current_step": "Processing round 15 of 30",
  "error_message": null
}
```

**Response:**
```json
{
  "success": true,
  "message": "Progress updated"
}
```

### POST /api/demo-complete/{match_id}
Receive completed demo data from parser service (streaming approach).

**Phase 1: Match Metadata**
```json
{
  "status": "processing_data",
  "match": {
    "map": "de_dust2",
    "winning_team_score": 16,
    "losing_team_score": 14,
    "match_type": "mm",
    "start_timestamp": "2024-01-15T10:30:00Z",
    "end_timestamp": "2024-01-15T11:15:00Z",
    "total_rounds": 30
  },
  "players": [
    {
      "steam_id": "steam_123456789",
      "name": "Player1",
      "team": "CT"
    }
  ]
}
```

**Phase 2: Final Completion**
```json
{
  "status": "completed"
}
```

**Error Response:**
```json
{
  "status": "failed",
  "error": "Failed to process demo file"
}
```

### POST /api/demo-data/{match_id}/gunfight-events
Stream gunfight events in batches.

**Request:**
```json
{
  "batch_index": 1,
  "is_last": false,
  "total_batches": 15,
  "data": [
    {
      "round_number": 1,
      "round_time": 45,
      "tick_timestamp": 12345,
      "player_1_steam_id": "steam_123456789",
      "player_2_steam_id": "steam_987654321",
      "player_1_hp_start": 100,
      "player_2_hp_start": 85,
      "player_1_armor": 100,
      "player_2_armor": 0,
      "player_1_flashed": false,
      "player_2_flashed": true,
      "player_1_weapon": "ak47",
      "player_2_weapon": "m4a1",
      "player_1_equipment_value": 4000,
      "player_2_equipment_value": 3500,
      "player_1_x": 100.5,
      "player_1_y": 200.3,
      "player_1_z": 50.0,
      "player_2_x": 150.2,
      "player_2_y": 250.1,
      "player_2_z": 50.0,
      "distance": 75.3,
      "headshot": true,
      "wallbang": false,
      "penetrated_objects": 0,
      "victor_steam_id": "steam_123456789",
      "damage_dealt": 100
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "batch_received": 1,
  "total_batches": 15,
  "message": "Batch processed successfully"
}
```

### POST /api/demo-data/{match_id}/grenade-events
Stream grenade events in batches.

**Request:**
```json
{
  "batch_index": 1,
  "is_last": true,
  "total_batches": 3,
  "data": [
    {
      "round_number": 1,
      "round_time": 30,
      "tick_timestamp": 12300,
      "player_steam_id": "steam_123456789",
      "grenade_type": "flashbang",
      "player_x": 100.5,
      "player_y": 200.3,
      "player_z": 50.0,
      "player_aim_x": 0.8,
      "player_aim_y": 0.2,
      "player_aim_z": 0.1,
      "grenade_final_x": 150.0,
      "grenade_final_y": 250.0,
      "grenade_final_z": 50.0,
      "damage_dealt": 0,
      "flash_duration": 2.5,
      "affected_players": [
        {
          "steam_id": "steam_987654321",
          "flash_duration": 2.5
        }
      ],
      "throw_type": "lineup"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "batch_received": 1,
  "total_batches": 3,
  "message": "Batch processed successfully"
}
```

### POST /api/demo-data/{match_id}/round-events
Stream round events.

**Request:**
```json
{
  "data": [
    {
      "round_number": 1,
      "tick_timestamp": 12300,
      "event_type": "start"
    },
    {
      "round_number": 1,
      "tick_timestamp": 13500,
      "event_type": "end",
      "winner": "CT",
      "duration": 120
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Round events processed successfully"
}
```

### POST /api/demo-data/{match_id}/damage-events
Stream damage events in batches.

**Request:**
```json
{
  "batch_index": 1,
  "is_last": false,
  "total_batches": 8,
  "data": [
    {
      "round_number": 1,
      "round_time": 45,
      "tick_timestamp": 12345,
      "attacker_steam_id": "steam_123456789",
      "victim_steam_id": "steam_987654321",
      "damage": 100,
      "armor_damage": 0,
      "health_damage": 100,
      "headshot": true,
      "weapon": "ak47"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "batch_received": 1,
  "total_batches": 8,
  "message": "Batch processed successfully"
}
```

### GET /api/match-data/{match_id}
Get processed match data for display.

**Response:**
```json
{
  "success": true,
  "match": {
    "id": "uuid-string",
    "map": "de_dust2",
    "winning_team_score": 16,
    "losing_team_score": 14,
    "match_type": "mm",
    "start_timestamp": "2024-01-15T10:30:00Z",
    "end_timestamp": "2024-01-15T11:15:00Z",
    "total_rounds": 30,
    "total_fight_events": 150,
    "total_grenade_events": 89,
    "processing_status": "completed"
  },
  "match_summary": {
    "total_kills": 150,
    "total_deaths": 150,
    "total_assists": 45,
    "total_headshots": 67,
    "total_wallbangs": 12,
    "total_damage": 45000,
    "total_he_damage": 1200,
    "total_effective_flashes": 23,
    "total_smokes_used": 45,
    "total_molotovs_used": 12,
    "total_first_kills": 30,
    "total_first_deaths": 30,
    "total_clutches_1v1_attempted": 5,
    "total_clutches_1v1_successful": 3,
    "total_clutches_1v2_attempted": 2,
    "total_clutches_1v2_successful": 1
  },
  "players": [
    {
      "id": 1,
      "steam_id": "steam_123456789",
      "name": "Player1",
      "team": "CT",
      "stats": {
        "kills": 25,
        "deaths": 18,
        "assists": 8,
        "headshots": 12,
        "wallbangs": 3,
        "first_kills": 5,
        "first_deaths": 2,
        "total_damage": 4500,
        "average_damage_per_round": 150,
        "damage_taken": 3800,
        "he_damage": 120,
        "effective_flashes": 8,
        "smokes_used": 12,
        "molotovs_used": 3,
        "flashbangs_used": 15,
        "clutches_1v1_attempted": 1,
        "clutches_1v1_successful": 1,
        "clutches_1v2_attempted": 0,
        "clutches_1v2_successful": 0,
        "kd_ratio": 1.39,
        "headshot_percentage": 48.0,
        "clutch_success_rate": 100.0
      }
    }
  ],
  "rounds": [
    {
      "round_number": 1,
      "winner": "CT",
      "duration": 120,
      "total_kills": 5,
      "total_deaths": 5,
      "first_kill_player": "steam_123456789",
      "first_death_player": "steam_987654321"
    }
  ]
}
```

### GET /api/matches
Get list of all processed matches.

**Query Parameters:**
- `page` (optional): Page number for pagination
- `per_page` (optional): Items per page (default: 20)
- `map` (optional): Filter by map
- `match_type` (optional): Filter by match type
- `status` (optional): Filter by processing status

**Response:**
```json
{
  "success": true,
  "matches": [
    {
      "id": "uuid-string",
      "map": "de_dust2",
      "winning_team_score": 16,
      "losing_team_score": 14,
      "match_type": "mm",
      "start_timestamp": "2024-01-15T10:30:00Z",
      "total_rounds": 30,
      "processing_status": "completed",
      "created_at": "2024-01-15T12:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

## Streaming Data Flow

### Processing Sequence:
1. **Upload demo** → Laravel creates match record
2. **Parse demo** → Go service starts processing
3. **Progress updates** → Go service sends progress
4. **Match metadata** → Go service sends match + players
5. **Event batches** → Go service streams events in chunks
6. **Completion** → Go service signals completion
7. **Database processing** → Laravel processes all batches
8. **Summary calculation** → Laravel calculates statistics

### Batch Sizes:
- **Gunfight events**: 100 events per batch
- **Grenade events**: 50 events per batch  
- **Damage events**: 200 events per batch
- **Round events**: All events in one batch

### Error Handling:
- If a batch fails, Laravel can request retry
- Partial data is stored in temporary storage
- Failed matches can be retried from last successful batch

## Error Responses

All endpoints return consistent error responses:

```json
{
  "success": false,
  "error": "Error message",
  "error_code": "VALIDATION_ERROR", // optional
  "details": { // optional, for validation errors
    "field": ["Error message"]
  }
}
```

## Authentication

All Laravel API endpoints require authentication (except upload and progress endpoints for the parser service).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

## Rate Limiting

- Upload endpoint: 5 requests per minute per user
- Progress endpoint: 60 requests per minute per user
- Data streaming endpoints: 100 requests per minute per user
- Match data endpoint: 100 requests per minute per user 