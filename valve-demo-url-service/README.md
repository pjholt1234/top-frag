# Sharecode Decoder Service

A Node.js microservice that decodes CS2 sharecodes and retrieves demo download URLs via Steam Game Coordinator.

## Features

- Decode CS2 sharecodes using Steam Game Coordinator
- Retrieve demo download URLs
- TypeScript implementation with full type safety
- Security features (API key authentication, rate limiting, CORS)
- Health check and metrics endpoints
- Comprehensive error handling and logging

## Security Features

- **API Key Authentication**: Protect endpoints with API keys
- **Rate Limiting**: Prevent abuse with configurable rate limits
- **CORS Configuration**: Control cross-origin requests
- **Input Validation**: Validate sharecode format and sanitize inputs
- **Security Headers**: Helmet.js for security headers

## Prerequisites

- Node.js 18.0.0 or higher
- A Steam account with CS2 access
- Steam bot credentials (username, password, shared secret for 2FA)

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   npm install
   ```

3. Copy the environment file:
   ```bash
   cp env.example .env
   ```

4. Configure your environment variables in `.env`

## Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `STEAM_USERNAME` | Steam bot username | Yes |
| `STEAM_PASSWORD` | Steam bot password | Yes |
| `STEAM_SHARED_SECRET` | Steam shared secret for 2FA | No |
| `API_KEYS` | Comma-separated list of API keys | Yes |
| `RATE_LIMIT_MAX` | Maximum requests per window | No (default: 100) |
| `RATE_LIMIT_WINDOW` | Rate limit window in ms | No (default: 900000) |
| `ALLOWED_ORIGINS` | Comma-separated CORS origins | No |
| `PORT` | Server port | No (default: 3001) |
| `NODE_ENV` | Environment | No (default: development) |
| `LOG_LEVEL` | Logging level | No (default: info) |

## Usage

### Development

```bash
# Start in development mode with hot reload
npm run dev:watch

# Start in development mode
npm run dev

# Run tests
npm test

# Run tests in watch mode
npm run test:watch

# Lint code
npm run lint

# Fix linting issues
npm run lint:fix
```

### Production

```bash
# Build the project
npm run build

# Start the production server
npm start
```

## API Endpoints

### POST /demo

Decode a sharecode and retrieve the demo URL.

**Headers:**
- `X-API-Key` or `Authorization: Bearer <api_key>`

**Request Body:**
```json
{
  "sharecode": "CSGO-2vCuH-4GBwh-GLrjU-nz8Rf-L5WwP"
}
```

**Response:**
```json
{
  "matchId": "CSGO-2vCuH-4GBwh-GLrjU-nz8Rf-L5WwP",
  "demoUrl": "http://replay192.valve.net/730/003775976266281254929_1315865239.dem.bz2",
  "service": "sharecode-decoder",
  "timestamp": "2025-09-21T04:23:35.000Z"
}
```

### GET /health

Check service health and dependencies.

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2025-09-21T04:23:35.000Z",
  "uptime": 123.45,
  "version": "1.0.0",
  "environment": "production",
  "dependencies": {
    "steam": "connected",
    "gameCoordinator": "ready"
  },
  "system": {
    "memory": { ... },
    "platform": "darwin",
    "nodeVersion": "v18.17.0"
  }
}
```

### GET /metrics

Get service metrics (requires API key).

**Headers:**
- `X-API-Key` or `Authorization: Bearer <api_key>`

**Response:**
```json
{
  "requests": 150,
  "errors": 2,
  "demosProcessed": 148,
  "startTime": "2025-09-21T04:00:00.000Z",
  "uptime": 3600,
  "memory": {
    "rss": 45,
    "heapTotal": 20,
    "heapUsed": 15,
    "external": 5
  },
  "timestamp": "2025-09-21T05:00:00.000Z"
}
```

## Error Responses

### 400 Bad Request
```json
{
  "error": "Validation failed",
  "details": [...],
  "timestamp": "2025-09-21T04:23:35.000Z"
}
```

### 401 Unauthorized
```json
{
  "error": "Invalid or missing API key",
  "timestamp": "2025-09-21T04:23:35.000Z"
}
```

### 404 Not Found
```json
{
  "error": "Demo not found or unavailable",
  "sharecode": "CSGO-2vCuH-4GBwh-GLrjU-nz8Rf-L5WwP",
  "timestamp": "2025-09-21T04:23:35.000Z"
}
```

### 429 Too Many Requests
```json
{
  "error": "Too many requests, please try again later.",
  "retryAfter": 900
}
```

### 500 Internal Server Error
```json
{
  "error": "Internal server error",
  "message": "Match info request timeout - match may be too old or unavailable",
  "timestamp": "2025-09-21T04:23:35.000Z"
}
```

## Testing

```bash
# Run all tests
npm test

# Run tests with coverage
npm run test:coverage

# Run tests in watch mode
npm run test:watch
```

## Development

### Project Structure

```
src/
├── server.ts              # Main server file
├── services/
│   ├── steamService.ts    # Steam client and GC integration
│   ├── demoService.ts     # Demo URL extraction logic
│   ├── sharecodeService.ts # Sharecode validation
│   └── __tests__/         # Test files
├── utils/
│   └── logger.ts          # Winston logger configuration
└── types/
    └── steam.d.ts         # TypeScript type definitions
```

### Building

```bash
# Build TypeScript to JavaScript
npm run build

# Type check without building
npm run type-check

# Clean build directory
npm run clean
```

## Troubleshooting

### Common Issues

1. **Steam login fails**: Check your credentials and 2FA setup
2. **GC not ready**: Ensure CS2 is in your Steam library
3. **Demo not found**: Sharecode may be too old or invalid
4. **Rate limit exceeded**: Wait for the rate limit window to reset

### Logs

The service uses Winston for structured logging. Logs include:
- Request/response information
- Steam connection status
- Game Coordinator events
- Error details with stack traces

## License

MIT
