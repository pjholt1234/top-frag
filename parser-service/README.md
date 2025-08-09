# CS:GO Demo Parser Service

A high-performance Go microservice for parsing CS:GO demo files and extracting detailed game events including gunfights, grenade usage, damage events, and round statistics.

## 🏗️ Architecture Overview

This service is built using Go's concurrency primitives and follows a clean architecture pattern with clear separation of concerns:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   HTTP API      │    │   Demo Parser   │    │  Batch Sender   │
│   (Gin)         │───▶│  (demoinfocs)   │───▶│  (HTTP Client)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Handlers      │    │ Event Processor │    │ External APIs   │
│   (Request/     │    │ (Game Events)   │    │ (Callbacks)     │
│    Response)    │    └─────────────────┘    └─────────────────┘
└─────────────────┘
```

## 🚀 How It Works

### 1. **Request Processing**
- Client uploads demo file via HTTP POST with multipart form data
- Service validates the uploaded file
- Creates a job with unique ID
- Saves file to temporary location
- Processing starts immediately in a background goroutine
- Client receives job ID immediately (non-blocking)

### 2. **Demo Parsing**
- Uses `demoinfocs-golang` library to parse CS:GO demo files
- Processes events in real-time as they occur in the demo
- Temporary files are automatically cleaned up after processing (success, error, or panic)
- Maintains game state throughout the parsing process
- Extracts detailed information about:
  - **Gunfights**: Player engagements with health, armor, weapons, positions
  - **Grenades**: Throws, explosions, damage, affected players
  - **Damage Events**: Individual damage instances with weapon info
  - **Round Events**: Start/end of rounds with winners and duration

### 3. **Data Processing**
- Events are processed and categorized in real-time
- Game state is maintained for accurate event context
- Data is structured into comprehensive JSON format
- Progress updates are sent via HTTP callbacks

### 4. **Batch Sending**
- Parsed data is sent to external services in configurable batches
- Implements retry logic with exponential backoff
- Supports multiple event types with different batch sizes
- Sends completion/error notifications

## 📁 Project Structure

```
parser-service/
├── main.go                 # Application entry point
├── config.yaml            # Configuration file
├── go.mod                 # Go dependencies
├── internal/              # Private application code
│   ├── config/           # Configuration management
│   │   └── config.go     # Config structs and loading
│   ├── types/            # Data structures
│   │   ├── types.go      # Event types and structs
│   │   └── types_test.go # Type validation tests
│   ├── api/              # HTTP API layer
│   │   └── handlers/     # Request handlers
│   │       ├── parse_demo.go  # Demo parsing endpoint
│   │       └── health.go      # Health check endpoint
│   └── parser/           # Core parsing logic
│       ├── demo_parser.go     # Main parser orchestration
│       ├── event_processor.go # Event handling logic
│       └── batch_sender.go    # External API communication
└── Dockerfile            # Container configuration
```

## 🔧 Configuration

The service is configured via `config.yaml` with the following sections:

### Server Configuration
```yaml
server:
  port: "8080"           # HTTP server port
  read_timeout: "30s"    # Request read timeout
  write_timeout: "30s"   # Response write timeout
  idle_timeout: "60s"    # Connection idle timeout
```

### Parser Configuration
```yaml
parser:
  max_concurrent_jobs: 3     # Maximum simultaneous demos
  progress_interval: "5s"    # Progress update frequency
  max_demo_size: 1073741824  # Maximum demo file size (1GB)
  temp_dir: "/tmp/parser-service"  # Temporary file directory
```

### Batch Configuration
```yaml
batch:
  gunfight_events_size: 100  # Events per batch for gunfights
  grenade_events_size: 50    # Events per batch for grenades
  damage_events_size: 200    # Events per batch for damage
  round_events_size: 100     # Events per batch for rounds
  retry_attempts: 3          # HTTP retry attempts
  retry_delay: "1s"          # Delay between retries
  http_timeout: "30s"        # HTTP request timeout
```

## 🌐 API Endpoints

### POST /api/parse-demo
Submit a demo file for parsing via multipart form data.

**Request:**
```
Content-Type: multipart/form-data

demo_file: [binary file]
job_id: "optional-custom-job-id"
progress_callback_url: "http://your-app.com/progress"
completion_callback_url: "http://your-app.com/complete"
```

**Response:**
```json
{
  "success": true,
  "job_id": "abc-123-def",
  "message": "Demo parsing started"
}
```

### GET /health
Health check endpoint.

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

### GET /ready
Readiness check endpoint.

**Response:**
```json
{
  "status": "ready",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

## 📊 Data Structures

### Gunfight Event
```json
{
  "round_number": 1,
  "round_time": 45,
  "tick_timestamp": 1234567890,
  "player_1_steam_id": "76561198012345678",
  "player_2_steam_id": "76561198087654321",
  "player_1_hp_start": 100,
  "player_2_hp_start": 85,
  "player_1_armor": 100,
  "player_2_armor": 0,
  "player_1_flashed": false,
  "player_2_flashed": true,
  "player_1_weapon": "ak47",
  "player_2_weapon": "m4a1",
  "player_1_equipment_value": 4700,
  "player_2_equipment_value": 3100,
  "player_1_position": {"x": 100.5, "y": 200.3, "z": 50.0},
  "player_2_position": {"x": 150.2, "y": 180.7, "z": 50.0},
  "distance": 45.2,
  "headshot": true,
  "wallbang": false,
  "penetrated_objects": 0,
  "victor_steam_id": "76561198012345678",
  "damage_dealt": 100
}
```

### Grenade Event
```json
{
  "round_number": 1,
  "round_time": 30,
  "tick_timestamp": 1234567890,
  "player_steam_id": "76561198012345678",
  "grenade_type": "flashbang",
  "player_position": {"x": 100.5, "y": 200.3, "z": 50.0},
  "player_aim": {"x": 0.5, "y": 0.3, "z": 0.8},
  "grenade_final_position": {"x": 120.0, "y": 180.0, "z": 60.0},
  "damage_dealt": 0,
  "flash_duration": 2.5,
  "affected_players": [
    {
      "steam_id": "76561198087654321",
      "flash_duration": 2.5,
      "damage_taken": null
    }
  ],
  "throw_type": "pop"
}
```

## 🔄 Callback System

The service sends progress updates and completion notifications to external services:

### Progress Callback
```json
{
  "job_id": "abc-123-def",
  "status": "processing",
  "progress": 50,
  "current_step": "Parsing events",
  "error_message": null
}
```

### Completion Callback
```json
{
  "job_id": "abc-123-def",
  "status": "completed",
  "match_data": {
    "match": {
      "map": "de_dust2",
      "winning_team_score": 16,
      "losing_team_score": 14,
      "match_type": "competitive",
      "total_rounds": 30
    },
    "players": [...],
    "gunfight_events": [...],
    "grenade_events": [...],
    "round_events": [...],
    "damage_events": [...]
  }
}
```

## 🚀 Development

### Prerequisites
- Go 1.21 or higher
- Git

### Quick Start
```bash
# Clone the repository
git clone <repository-url>
cd parser-service

# Install dependencies
go mod download

# Run the service
go run main.go
```

The service will start on `http://localhost:8080`

### Running Tests
```bash
# Run all tests
go test

# Run tests with verbose output
go test -v

# Run tests with coverage
go test -cover

# Generate coverage report
go test -coverprofile=coverage.out
go tool cover -html=coverage.out
```

## 🐳 Production Deployment

### Using Docker
```bash
# Build and run with Docker Compose
docker compose up --build

# Or build manually
docker build -t parser-service .
docker run -p 8080:8080 parser-service
```

### Environment Variables
The service supports environment variable overrides with the `PARSER_` prefix:

```bash
export PARSER_SERVER_PORT=9090
export PARSER_LOGGING_LEVEL=debug
export PARSER_PARSER_MAX_CONCURRENT_JOBS=5
```

## 🔍 Monitoring and Logging

### Log Levels
- `debug`: Detailed debugging information
- `info`: General operational messages
- `warn`: Warning messages
- `error`: Error messages

### Log Format
The service supports both JSON and text logging formats:

```yaml
logging:
  level: "info"
  format: "json"  # or "text"
```

### Health Checks
The service provides health check endpoints for monitoring:
- `/health`: Basic health status
- `/ready`: Readiness for traffic

## 🔧 Key Go Concepts Used

### Goroutines and Concurrency
```go
// Start background processing
go h.processDemo(context.Background(), job)
```

### Struct Methods and Receivers
```go
func (h *ParseDemoHandler) HandleParseDemo(c *gin.Context) {
    // Method on ParseDemoHandler struct
}
```

### Error Handling
```go
if err != nil {
    return nil, fmt.Errorf("failed to parse demo: %w", err)
}
```

### Context for Cancellation
```go
func (dp *DemoParser) ParseDemo(ctx context.Context, demoPath string) {
    // ctx allows for timeout and cancellation
}
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## 📝 License

[Add your license information here]