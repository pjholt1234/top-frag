# Top Frag - CS2 Demo Analysis Platform

> **🎯 A comprehensive platform for analyzing Counter-Strike 2 demo files, extracting detailed game events, and providing insights through a modern web interface.**

## 🎯 Project Overview

### What is Top Frag?
Top Frag is a complete CS2 demo analysis platform that parses demo files to extract detailed game events including gunfights, grenade usage, damage events, and round statistics. The platform consists of a high-performance Go parser service and a Laravel/React web application.

### Key Features
- ✅ **Demo File Parsing**: High-performance parsing of CS2 demo files using Go
- ✅ **Event Extraction**: Detailed extraction of gunfights, grenades, damage, and round events
- ✅ **Web Interface**: Modern React-based dashboard with match analysis and grenade library
- ✅ **User Authentication**: Secure user registration and authentication system
- ✅ **Match History**: Track and analyze your CS2 match history
- ✅ **Grenade Library**: Browse and favorite grenade throws from matches
- ✅ **Real-time Processing**: Asynchronous demo processing with progress callbacks
- ✅ **Steam Game Coordinator Integration**: Automatically pull new matchmaking demos

### Tech Stack
- **Backend Parser**: Go 1.21+ - High-performance demo parsing microservice
- **Web Backend**: Laravel 12 - PHP framework with API endpoints and job processing
- **Frontend**: React 19 + TypeScript - Modern SPA with Tailwind CSS and ChadCN
- **Demo URL Service**: Node.js 18+ - Microservice for Steam Game Coordinator integration
- **Database**: MySQL - Relational database for match and player data

---

## 🏗️ Architecture Overview

### System Architecture
```
                       ┌─────────────────┐    ┌─────────────────┐
                       │ Valve Demo URL  │───▶│ Steam Game      │
                       │   Service       │    │ Coordinator     │
                       │   (Node.js)     │    │                 │
                       └─────────────────┘    └─────────────────┘
                                ▲
                                │ 
                                ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   React SPA     │    │   Laravel API   │    │ Parser Service  │
│   (Frontend)    │◀──▶│   (Backend)     │◀──▶│   (Go)          │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                ▲                       ▲
                                │                       │ 
                                ▼                       ▼    
                        ┌─────────────────┐   ┌─────────────────┐
                        │   Database      │   │   Database      │
                        │   (MySQL)       │   │   (MySQL)       │
                        └─────────────────┘   └─────────────────┘ 
        
```

### Project Structure
```
top-frag/
├── parser-service/          # Go microservice for demo parsing
├── web-app/                 # Laravel/React web application
├── valve-demo-url-service/  # Node.js service for demo URL retrieval
├── docs/                    # Documentation and guides
├── bruno/                   # API testing collections
└── README.md               # This file
```

### Key Components
- **Parser Service**: High-performance Go service that parses CS2 demo files and extracts game events
- **Web Application**: Laravel backend with React frontend for user interface and data management
- **Valve Demo URL Service**: Node.js microservice that decodes CS2 sharecodes and retrieves demo download URLs via Steam Game Coordinator

---

## 🚀 Quick Start

### Prerequisites
- [ ] Go 1.21 or higher
- [ ] PHP 8.3 or higher
- [ ] Node.js 18+ and npm
- [ ] MySQL 8.0+
- [ ] Steam account with CS2 access (for valve-demo-url-service)

### Installation
```bash
# Clone the repository
git clone <repository-url>
cd top-frag

# Set up parser service
cd parser-service
go mod download
cp config.yaml.backup config.yaml

# Set up web application
cd ../web-app
composer install
npm install
cp .env.example .env

# Set up valve demo URL service
cd ../valve-demo-url-service
npm install
cp env.example .env
```

### Verify Installation
```bash
# Test parser service
cd parser-service
go test

# Test web application
cd ../web-app
php artisan test
npm run test

# Test valve demo URL service
cd ../valve-demo-url-service
npm test
```

---

### Available Scripts
```bash
# Parser Service
cd parser-service
go test                    # Run tests
gofmt -w .                # Format code

# Web Application
cd web-app
php artisan test          # Run PHP tests
npm run test             # Run frontend tests
./vendor/bin/pint        # Format PHP code
npm run lint             # Check TypeScript code
npm run format           # Format TypeScript code

# Valve Demo URL Service
cd valve-demo-url-service
npm test                 # Run tests
npm run dev              # Start development server
npm run build            # Build for production
npm run lint             # Check TypeScript code

```

### Code Quality Tools
- **Go**: `golangci-lint` - Comprehensive Go linting
- **PHP**: `Laravel Pint` - PHP code formatting
- **TypeScript**: `ESLint` + `Prettier` - Code quality and formatting
- **Node.js**: `ESLint` + `Jest` - TypeScript linting and testing

---

## 📚 Documentation

### Essential Reading
- **[Task Execution Guide](docs/TASK_EXECUTION_GUIDE.md)** - Development workflow and process
- **[Coding Standards](docs/CODING_STANDARDS.md)** - Code style and conventions
- **[Statistics Guide](docs/STATS_GUIDE.md)** - Comprehensive guide to all statistics and metrics
- **[Line of Sight Detection](docs/LOS_DETECTION.md)** - Technical guide to LOS detection algorithms and implementation

### Additional Resources
- **[Bruno API Collections](bruno/)** - API testing and documentation data structures

---

## 🧪 Testing

### Test Structure
```
tests/
├── parser-service/        # Go unit and integration tests
├── web-app/tests/         # PHP feature and unit tests
├── valve-demo-url-service/ # Node.js unit and integration tests
└── bruno/                 # API integration tests
```

### Running Tests
```bash
# Parser Service Tests
cd parser-service
go test                    # All tests
go test -v                 # Verbose output
go test -cover             # With coverage

# Web Application Tests
cd web-app
php artisan test          # All tests
npm run test             # Frontend tests

# Valve Demo URL Service Tests
cd valve-demo-url-service
npm test                 # All tests
npm run test:coverage    # With coverage

```

---

## 🔧 Configuration

### Environment Variables
| Variable | Description | Default | Required |
|----------|-------------|---------|----------|
| `DB_CONNECTION` | Database connection type | `mysql` | ✅ |
| `DB_HOST` | Database host | `127.0.0.1` | ✅ |
| `DB_DATABASE` | Database name | - | ✅ |
| `PARSER_SERVICE_URL` | Parser service URL | `http://localhost:8080` | ✅ |
| `PARSER_SERVICE_API_KEY` | Parser service API key | - | ✅ |
| `VALVE_DEMO_URL_SERVICE_URL` | Valve demo URL service URL | `http://localhost:3001` | ✅ |
| `VALVE_DEMO_URL_SERVICE_API_KEY` | Valve demo URL service API key | - | ✅ |
| `APP_KEY` | Laravel application key | - | ✅ |

### Configuration Files
- **`.env`** - Web application environment variables
- **`config.yaml`** - Parser service configuration
- **`valve-demo-url-service/.env`** - Valve demo URL service environment variables
- **`package.json`** - Frontend dependencies and scripts
- **`composer.json`** - Backend dependencies and scripts

---

## 🚀 Deployment

### Production Deployment

*Production deployment instructions will be added here.*

---

## 📊 Monitoring & Observability

### Health Checks
- **Parser Service**: `GET /health` and `GET /ready`
- **Web Application**: `GET /up` (Laravel health check)
- **Valve Demo URL Service**: `GET /health` and `GET /metrics`

### Logging
- **Format**: JSON for parser service, Laravel logs for web app, Winston for valve-demo-url-service
- **Levels**: `debug`, `info`, `warn`, `error`
- **Location**: `parser-service/`, `web-app/storage/logs/`, and `valve-demo-url-service/logs/`

---

## 📈 Performance

### Performance Characteristics
- **Demo Parsing**: ~2-5 minutes for typical match demos
- **API Response**: <200ms for most endpoints
- **Concurrent Processing**: Up to 3 demos simultaneously
- **Resource Usage**: ~512MB RAM per parser instance

---

## 📄 License

MIT License - See [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

### Core Dependencies
- [demoinfocs-golang](https://github.com/markus-wa/demoinfocs-golang) - Main Go parser package that the parser service is built ontop
- [node-globaloffensive](https://github.com/DoctorMcKay/node-globaloffensive) - Used for interacting with CS2 Game Coordinator for valve demo fetching
- [node-steam-user](https://github.com/DoctorMcKay/node-steam-user) - Used for interacting with steam client on behave of bot user
- [Laravel](https://laravel.com) - PHP web framework
- [React](https://reactjs.org) - JavaScript UI library
- [Tailwind CSS](https://tailwindcss.com) - CSS framework
- [ChadCN](https://ui.shadcn.com) - Re-usable components built using Radix UI and Tailwind CSS
- [Express](https://expressjs.com) - Node.js web framework for valve-demo-url-service
- [Winston](https://github.com/winstonjs/winston) - Logging library for valve-demo-url-service

### Assets & Resources
- [Counter-Strike Wiki](https://counterstrike.fandom.com/wiki) - Map logs and background images
- [Simple Radar](https://readtldr.gg/simpleradar) - Map radar images for CS2
- [Boltobserv](https://github.com/boltgolt/boltobserv) - Player position to radar conversion configuration
- [cs2-map-parser](https://github.com/AtomicBool/cs2-map-parser) - Used to generate .tri files for line of sight detection
- [csgo-rank-icons](https://github.com/ItzArty/csgo-rank-icons) - CS2 rank icons extracted from game binaries

---

## 🤖 AI Assistant Context

> **This section provides context for AI assistants working on this project**

### Project Context
This is a CS2 demo analysis platform that processes Counter-Strike 2 demo files to extract detailed game events. The codebase follows clean architecture patterns with clear separation between the parser service (Go), web application (Laravel/React), and testing harness (Go CLI).

### Key Concepts
- **Demo Parsing**: Using demoinfocs-golang to extract game events from CS2 demo files
- **Event Processing**: Real-time processing of gunfights, grenades, damage, and round events
- **Microservice Architecture**: Separate parser service communicating with web app via HTTP
- **Modern Web Stack**: Laravel API backend with React SPA frontend

### Development Guidelines for AI
1. **Always read** the [Task Execution Guide](docs/TASK_EXECUTION_GUIDE.md) before making changes
2. **Follow** the [Coding Standards](docs/CODING_STANDARDS.md) exactly
3. **Respect** project boundaries (database, config, dependencies)
4. **Test** all changes thoroughly
5. **Ask** for clarification when requirements are unclear

### Common Patterns
- **Error Handling**: Explicit error handling with proper wrapping and context
- **Data Flow**: HTTP API communication between services with JSON payloads
- **Testing**: Comprehensive test coverage with unit, integration, and CLI tests

### File Organization
- **Parser Service**: Go microservice in `parser-service/` with clean architecture
- **Web Application**: Laravel backend and React frontend in `web-app/`
- **Valve Demo URL Service**: Node.js microservice in `valve-demo-url-service/` with TypeScript
- **Documentation**: Detailed guides in `docs/` folder

### Dependencies
- **Critical**: demoinfocs-golang, Laravel, React, Node.js, MySQL
- **Optional**: Docker for deployment, Bruno for API testing
- **Avoid**: Breaking changes to database schema without explicit permission

---

*Last updated: September 26, 2025*
*Version: 1.0.0*
