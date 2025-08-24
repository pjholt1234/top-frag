# Grenade Favourites API

This document describes the API endpoints for managing grenade favourites in the application.

## Authentication

All endpoints require authentication using Laravel Sanctum. Include the `Authorization: Bearer {token}` header in your requests.

## Endpoints

### GET /api/grenade-favourites

Retrieve all grenade favourites for the authenticated user.

**Query Parameters:**
- `match_id` (optional): Filter by specific match ID
- `grenade_type` (optional): Filter by grenade type (e.g., 'flashbang', 'smoke', 'hegrenade', 'molotov', 'incendiary')
- `player_side` (optional): Filter by player side ('T' or 'CT')

**Response:**
```json
{
  "favourites": [
    {
      "id": 1,
      "match_id": 123,
      "user_id": 456,
      "round_number": 5,
      "round_time": 120.5,
      "tick_timestamp": 12345,
      "player_steam_id": "STEAM_123456789",
      "player_side": "T",
      "grenade_type": "flashbang",
      "player_x": 100.0,
      "player_y": 200.0,
      "player_z": 50.0,
      "player_aim_x": 101.0,
      "player_aim_y": 201.0,
      "player_aim_z": 51.0,
      "grenade_final_x": 150.0,
      "grenade_final_y": 250.0,
      "grenade_final_z": 75.0,
      "damage_dealt": 0,
      "flash_duration": 2.5,
      "friendly_flash_duration": 0,
      "enemy_flash_duration": 2.5,
      "friendly_players_affected": 0,
      "enemy_players_affected": 2,
      "throw_type": "pop",
      "effectiveness_rating": 8.5,
      "created_at": "2025-08-23T22:05:22.000000Z",
      "updated_at": "2025-08-23T22:05:22.000000Z",
      "match": {
        "id": 123,
        "map": "de_dust2",
        "start_timestamp": "2025-08-23T20:00:00.000000Z",
        "end_timestamp": "2025-08-23T21:30:00.000000Z"
      }
    }
  ]
}
```

### POST /api/grenade-favourites

Create a new grenade favourite.

**Request Body:**
```json
{
  "match_id": 123,
  "round_number": 5,
  "round_time": 120.5,
  "tick_timestamp": 12345,
  "player_steam_id": "STEAM_123456789",
  "player_side": "T",
  "grenade_type": "flashbang",
  "player_x": 100.0,
  "player_y": 200.0,
  "player_z": 50.0,
  "player_aim_x": 101.0,
  "player_aim_y": 201.0,
  "player_aim_z": 51.0,
  "grenade_final_x": 150.0,
  "grenade_final_y": 250.0,
  "grenade_final_z": 75.0,
  "damage_dealt": 0,
  "flash_duration": 2.5,
  "friendly_flash_duration": 0,
  "enemy_flash_duration": 2.5,
  "friendly_players_affected": 0,
  "enemy_players_affected": 2,
  "throw_type": "pop",
  "effectiveness_rating": 8.5
}
```

**Required Fields:**
- `match_id`: Must exist in the matches table
- `round_number`: Integer, minimum 1
- `round_time`: Numeric, minimum 0
- `tick_timestamp`: Integer, minimum 0
- `player_steam_id`: String
- `player_side`: Must be 'T' or 'CT'
- `grenade_type`: String
- `player_x`, `player_y`, `player_z`: Numeric coordinates
- `player_aim_x`, `player_aim_y`, `player_aim_z`: Numeric aim coordinates
- `grenade_final_x`, `grenade_final_y`, `grenade_final_z`: Numeric final grenade coordinates

**Optional Fields:**
- `damage_dealt`: Numeric, minimum 0
- `flash_duration`: Numeric, minimum 0
- `friendly_flash_duration`: Numeric, minimum 0
- `enemy_flash_duration`: Numeric, minimum 0
- `friendly_players_affected`: Integer, minimum 0
- `enemy_players_affected`: Integer, minimum 0
- `throw_type`: String
- `effectiveness_rating`: Numeric, between 0 and 10

**Response (201 Created):**
```json
{
  "message": "Grenade added to favourites successfully",
  "favourite": {
    "id": 1,
    "match_id": 123,
    "user_id": 456,
    "round_number": 5,
    // ... all other fields
    "match": {
      "id": 123,
      "map": "de_dust2",
      "start_timestamp": "2025-08-23T20:00:00.000000Z",
      "end_timestamp": "2025-08-23T21:30:00.000000Z"
    }
  }
}
```

**Error Responses:**
- `422 Unprocessable Entity`: Validation errors with detailed field-specific messages
- `409 Conflict`: Grenade already favourited by this user

### DELETE /api/grenade-favourites/{id}

Delete a grenade favourite by ID.

**Response (200 OK):**
```json
{
  "message": "Favourite removed successfully"
}
```

**Error Responses:**
- `404 Not Found`: Favourite not found or doesn't belong to the authenticated user

## Error Handling

All endpoints return appropriate HTTP status codes and error messages in JSON format:

```json
{
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Validation Error Examples

When validation fails, you'll receive detailed error messages for each field:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "match_id": ["A match ID is required."],
    "player_side": ["Player side must be either T or CT."],
    "round_number": ["Round number must be at least 1."],
    "effectiveness_rating": ["Effectiveness rating cannot exceed 10."]
  }
}
```

## Rate Limiting

These endpoints are subject to the same rate limiting as other authenticated API endpoints.

## Security

- Users can only access their own grenade favourites
- Users cannot delete favourites that don't belong to them
- All input is validated and sanitized
- SQL injection protection is provided by Laravel's query builder
