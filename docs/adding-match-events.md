# Adding and Modifying Match Events

This guide explains how to add new event types or modify existing ones in the CS:GO Demo Parser Service.

## 📋 Overview

The parser extracts various types of events from CS:GO demo files:
- **Gunfight Events**: Player engagements and kills
- **Grenade Events**: Grenade throws, explosions, and effects
- **Damage Events**: Individual damage instances
- **Round Events**: Round start/end events

## 🏗️ Architecture

Events flow through the system as follows:

```
Demo File → demoinfocs Parser → Event Handlers → Event Processor → Data Structures → External APIs
```

## 📁 File Locations

Key files for event handling:

```
parser-service/
├── internal/
│   ├── types/
│   │   └── types.go              # Event data structures
│   └── parser/
│       ├── demo_parser.go        # Event registration
│       ├── event_processor.go    # Event handling logic
│       └── batch_sender.go       # Event sending logic
```

## 🔧 Adding a New Event Type

### Step 1: Define the Event Structure

Add your new event type to `internal/types/types.go`:

```go
type BombPlantEvent struct {
    RoundNumber    int       `json:"round_number"`
    RoundTime      int       `json:"round_time"`
    TickTimestamp  int64     `json:"tick_timestamp"`
    
    PlayerSteamID  string    `json:"player_steam_id"`
    Site           string    `json:"site"`           // "A" or "B"
    PlayerPosition Position  `json:"player_position"`
    PlantTime      int       `json:"plant_time"`     // Time to plant in seconds
    Success        bool      `json:"success"`        // Whether plant was successful
}
```

### Step 2: Update Match State

Add your event slice to the `MatchState` struct in `internal/types/types.go`:

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
    BombPlantEvents     []BombPlantEvent  // Add your new event type
    CurrentRoundKills   int
    CurrentRoundDeaths  int
    FirstKillPlayer     *string
    FirstDeathPlayer    *string
}
```

### Step 3: Add Event Handler

Create a handler method in `internal/parser/event_processor.go`:

```go
func (ep *EventProcessor) HandleBombPlanted(e events.BombPlanted) {
    if e.Player == nil {
        return
    }
    
    // Determine bomb site
    var site string
    if e.Site == common.BombsiteA {
        site = "A"
    } else if e.Site == common.BombsiteB {
        site = "B"
    }
    
    // Create bomb plant event
    bombPlantEvent := types.BombPlantEvent{
        RoundNumber:    ep.matchState.CurrentRound,
        RoundTime:      int(ep.matchState.RoundStartTick / 64), // Convert ticks to seconds
        TickTimestamp:  ep.matchState.RoundStartTick,
        PlayerSteamID:  types.SteamIDToString(e.Player.SteamID64),
        Site:           site,
        PlayerPosition: ep.getPlayerPosition(e.Player),
        PlantTime:      3, // Standard plant time
        Success:        true,
    }
    
    // Add to match state
    ep.matchState.BombPlantEvents = append(ep.matchState.BombPlantEvents, bombPlantEvent)
    
    ep.logger.WithFields(logrus.Fields{
        "round":    ep.matchState.CurrentRound,
        "player":   bombPlantEvent.PlayerSteamID,
        "site":     site,
    }).Debug("Bomb planted")
}
```

### Step 4: Register the Event Handler

Add your event handler to the registration in `internal/parser/demo_parser.go`:

```go
func (dp *DemoParser) registerEventHandlers(parser demoinfocs.Parser, eventProcessor *EventProcessor, progressCallback func(types.ProgressUpdate)) {
    // Existing handlers...
    parser.RegisterEventHandler(func(e events.RoundStart) {
        eventProcessor.HandleRoundStart(e)
        // ... progress callback
    })
    
    // Add your new handler
    parser.RegisterEventHandler(func(e events.BombPlanted) {
        eventProcessor.HandleBombPlanted(e)
        progressCallback(types.ProgressUpdate{
            Status:      types.StatusProcessing,
            Progress:    50,
            CurrentStep: "Processing bomb plant events",
        })
    })
}
```

### Step 5: Update Parsed Data Building

Modify the `buildParsedData` method in `internal/parser/demo_parser.go`:

```go
func (dp *DemoParser) buildParsedData(matchState *types.MatchState) *types.ParsedDemoData {
    return &types.ParsedDemoData{
        Match:           dp.buildMatchData(matchState),
        Players:         dp.buildPlayersList(matchState),
        GunfightEvents:  matchState.GunfightEvents,
        GrenadeEvents:   matchState.GrenadeEvents,
        RoundEvents:     matchState.RoundEvents,
        DamageEvents:    matchState.DamageEvents,
        BombPlantEvents: matchState.BombPlantEvents,  // Add your new events
    }
}
```

### Step 6: Add Batch Sending Logic

Create a method in `internal/parser/batch_sender.go`:

```go
func (bs *BatchSender) SendBombPlantEvents(ctx context.Context, jobID string, events []types.BombPlantEvent) error {
    if len(events) == 0 {
        return nil
    }
    
    batchSize := bs.config.Batch.BombPlantEventsSize // Add to config
    totalBatches := (len(events) + batchSize - 1) / batchSize
    
    bs.logger.WithFields(logrus.Fields{
        "job_id":        jobID,
        "total_events":  len(events),
        "batch_size":    batchSize,
        "total_batches": totalBatches,
    }).Info("Sending bomb plant events")
    
    for i := 0; i < totalBatches; i++ {
        start := i * batchSize
        end := start + batchSize
        if end > len(events) {
            end = len(events)
        }
        
        batch := events[start:end]
        
        flatEvents := make([]map[string]interface{}, len(batch))
        for j, event := range batch {
            flatEvents[j] = map[string]interface{}{
                "round_number":    event.RoundNumber,
                "round_time":      event.RoundTime,
                "tick_timestamp":  event.TickTimestamp,
                "player_steam_id": event.PlayerSteamID,
                "site":            event.Site,
                "player_position": event.PlayerPosition,
                "plant_time":      event.PlantTime,
                "success":         event.Success,
            }
        }
        
        payload := map[string]interface{}{
            "job_id": jobID,
            "events": flatEvents,
            "type":   "bomb_plant",
        }
        
        if err := bs.sendRequestWithRetry(ctx, bs.baseURL+"/api/events/bomb-plant", payload); err != nil {
            return fmt.Errorf("failed to send bomb plant events batch %d: %w", i+1, err)
        }
    }
    
    return nil
}
```

### Step 7: Update Configuration

Add batch size configuration to `internal/config/config.go`:

```go
type BatchConfig struct {
    GunfightEventsSize int           `mapstructure:"gunfight_events_size"`
    GrenadeEventsSize  int           `mapstructure:"grenade_events_size"`
    DamageEventsSize   int           `mapstructure:"damage_events_size"`
    RoundEventsSize    int           `mapstructure:"round_events_size"`
    BombPlantEventsSize int          `mapstructure:"bomb_plant_events_size"` // Add this
    RetryAttempts      int           `mapstructure:"retry_attempts"`
    RetryDelay         time.Duration `mapstructure:"retry_delay"`
    HTTPTimeout        time.Duration `mapstructure:"http_timeout"`
}
```

And add the default in `setDefaults()`:

```go
func setDefaults() {
    // Existing defaults...
    viper.SetDefault("batch.bomb_plant_events_size", 50) // Add this
}
```

### Step 8: Update Handler Logic

Modify the `sendAllEvents` method in `internal/api/handlers/parse_demo.go`:

```go
func (h *ParseDemoHandler) sendAllEvents(ctx context.Context, job *types.ProcessingJob, parsedData *types.ParsedDemoData) error {
    // Send match metadata
    if err := h.batchSender.SendMatchMetadata(ctx, job.JobID, job.CompletionCallbackURL, parsedData); err != nil {
        return err
    }
    
    // Send existing event types...
    if err := h.batchSender.SendGunfightEvents(ctx, job.JobID, parsedData.GunfightEvents); err != nil {
        return err
    }
    
    // Add your new event type
    if err := h.batchSender.SendBombPlantEvents(ctx, job.JobID, parsedData.BombPlantEvents); err != nil {
        return err
    }
    
    // Send completion
    return h.batchSender.SendCompletion(ctx, job.JobID, job.CompletionCallbackURL)
}
```

## 🔄 Modifying Existing Events

### Adding New Fields

To add a new field to an existing event:

1. **Update the struct** in `internal/types/types.go`:
```go
type GunfightEvent struct {
    // Existing fields...
    RoundNumber    int       `json:"round_number"`
    // Add your new field
    MapName        string    `json:"map_name,omitempty"`
}
```

2. **Update the handler** in `internal/parser/event_processor.go`:
```go
func (ep *EventProcessor) HandlePlayerKilled(e events.Kill) {
    // Existing logic...
    
    gunfightEvent := ep.createGunfightEvent(e)
    gunfightEvent.MapName = ep.matchState.MapName // Add your new field
    
    ep.matchState.GunfightEvents = append(ep.matchState.GunfightEvents, gunfightEvent)
}
```

3. **Update batch sending** in `internal/parser/batch_sender.go`:
```go
flatEvents[j] = map[string]interface{}{
    // Existing fields...
    "round_number": event.RoundNumber,
    "map_name":     event.MapName, // Add your new field
}
```

### Modifying Event Logic

To change how an event is processed:

1. **Update the handler method** in `internal/parser/event_processor.go`
2. **Test the changes** with existing demo files
3. **Update any dependent logic** that uses the modified event

## 🧪 Testing Your Changes

### Unit Tests

Add tests to `internal/types/types_test.go`:

```go
func TestBombPlantEvent_JSON(t *testing.T) {
    event := BombPlantEvent{
        RoundNumber:   1,
        RoundTime:     45,
        TickTimestamp: 1234567890,
        PlayerSteamID: "76561198012345678",
        Site:          "A",
        PlantTime:     3,
        Success:       true,
    }
    
    data, err := json.Marshal(event)
    if err != nil {
        t.Fatalf("Failed to marshal event: %v", err)
    }
    
    var unmarshaled BombPlantEvent
    if err := json.Unmarshal(data, &unmarshaled); err != nil {
        t.Fatalf("Failed to unmarshal event: %v", err)
    }
    
    if unmarshaled.Site != "A" {
        t.Errorf("Expected site A, got %s", unmarshaled.Site)
    }
}
```

### Integration Tests

Test with a real demo file:

```bash
# Run the service
go run main.go

# Send a test request
curl -X POST http://localhost:8080/api/parse-demo \
  -H "Content-Type: application/json" \
  -d '{
    "demo_path": "/path/to/test.dem",
    "progress_callback_url": "http://localhost:3000/progress",
    "completion_callback_url": "http://localhost:3000/complete"
  }'
```

## 📊 Available Event Types

The `demoinfocs-golang` library provides many event types you can listen to:

### Player Events
- `events.PlayerKilled` - Player death
- `events.PlayerHurt` - Player damage
- `events.PlayerSpawn` - Player spawn
- `events.PlayerDisconnected` - Player disconnect

### Round Events
- `events.RoundStart` - Round begins
- `events.RoundEnd` - Round ends
- `events.RoundEndOfficial` - Official round end

### Weapon Events
- `events.WeaponFire` - Weapon fired
- `events.BulletImpact` - Bullet impact
- `events.ItemPickup` - Item picked up
- `events.ItemDrop` - Item dropped

### Grenade Events
- `events.GrenadeProjectileDestroy` - Grenade explosion
- `events.FlashExplode` - Flashbang explosion
- `events.HeExplode` - HE grenade explosion
- `events.SmokeStart` - Smoke grenade starts
- `events.DecoyStart` - Decoy grenade starts

### Bomb Events
- `events.BombPlanted` - Bomb planted
- `events.BombDefused` - Bomb defused
- `events.BombExplode` - Bomb explodes

### Other Events
- `events.MatchStarted` - Match begins
- `events.MatchEnded` - Match ends
- `events.AnnouncementWinPanelMatch` - Match result

## 🚨 Common Pitfalls

### 1. **Nil Pointer Checks**
Always check for nil pointers in event handlers:
```go
func (ep *EventProcessor) HandlePlayerKilled(e events.Kill) {
    if e.Killer == nil || e.Victim == nil {
        return // Skip if players are nil
    }
    // Process event...
}
```

### 2. **Thread Safety**
The event processor is not thread-safe. Don't access shared state from multiple goroutines.

### 3. **Memory Management**
Large demo files can generate many events. Consider batching or filtering if memory becomes an issue.

### 4. **Error Handling**
Always handle errors gracefully in event handlers:
```go
func (ep *EventProcessor) HandleSomeEvent(e events.SomeEvent) {
    defer func() {
        if r := recover(); r != nil {
            ep.logger.WithError(fmt.Errorf("%v", r)).Error("Panic in event handler")
        }
    }()
    // Event handling logic...
}
```

## 🔍 Debugging Events

### Enable Debug Logging

Set log level to debug in `config.yaml`:
```yaml
logging:
  level: "debug"
  format: "json"
```

### Add Debug Logs

Add debug logging to your event handlers:
```go
func (ep *EventProcessor) HandleBombPlanted(e events.BombPlanted) {
    ep.logger.WithFields(logrus.Fields{
        "round":    ep.matchState.CurrentRound,
        "player":   e.Player.SteamID64,
        "site":     e.Site,
    }).Debug("Bomb planted event received")
    
    // Event processing logic...
}
```

### Monitor Event Flow

Use the progress callback to monitor event processing:
```go
progressCallback(types.ProgressUpdate{
    Status:      types.StatusProcessing,
    Progress:    60,
    CurrentStep: "Processing bomb plant events",
})
```

## 📚 Resources

- [demoinfocs-golang Documentation](https://github.com/markus-wa/demoinfocs-golang)
- [CS:GO Demo File Format](https://developer.valvesoftware.com/wiki/DEM_Format)
- [Go Concurrency Patterns](https://golang.org/doc/effective_go.html#concurrency)

## 🤝 Contributing

When adding new events:

1. **Follow existing patterns** for consistency
2. **Add comprehensive tests** for your changes
3. **Update documentation** to reflect new events
4. **Consider backward compatibility** when modifying existing events
5. **Test with multiple demo files** to ensure reliability 