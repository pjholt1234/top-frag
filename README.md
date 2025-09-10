# Top Frag - CS2 Demo Analysis Platform

> **🎯 A comprehensive platform for analyzing Counter-Strike 2 demo files, extracting detailed game events, and providing insights through a modern web interface.**

## 🎯 Project Overview

### What is Top Frag?
Top Frag is a complete CS2 demo analysis platform that parses demo files to extract detailed game events including gunfights, grenade usage, damage events, and round statistics. The platform consists of a high-performance Go parser service, a Laravel/React web application, and a comprehensive testing harness.

### Key Features
- ✅ **Demo File Parsing**: High-performance parsing of CS2 demo files using Go
- ✅ **Event Extraction**: Detailed extraction of gunfights, grenades, damage, and round events
- ✅ **Web Interface**: Modern React-based dashboard with match analysis and grenade library
- ✅ **User Authentication**: Secure user registration and authentication system
- ✅ **Match History**: Track and analyze your CS2 match history
- ✅ **Grenade Library**: Browse and favorite grenade throws from matches
- ✅ **Real-time Processing**: Asynchronous demo processing with progress callbacks
- ✅ **Comprehensive Testing**: Full test harness for parser service validation

### Tech Stack
- **Backend Parser**: Go 1.21+ - High-performance demo parsing microservice
- **Web Backend**: Laravel 12 - PHP framework with API endpoints and job processing
- **Frontend**: React 19 + TypeScript - Modern SPA with Tailwind CSS
- **Database**: MySQL/PostgreSQL - Relational database for match data

---

## 🏗️ Architecture Overview

### System Architecture
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web App       │    │ Parser Service  │    │  Test Harness   │
│   (Laravel/     │◀───│   (Go)          │◀───│   (Go CLI)      │
│    React)       │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Database      │    │ Demo Files      │    │ Test Results    │
│   (MySQL)       │    │ (.dem)          │    │ (JSON/Text)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Project Structure
```
top-frag/
├── parser-service/          # Go microservice for demo parsing
├── web-app/                 # Laravel/React web application
├── parser-test/             # Go CLI testing harness
├── docs/                    # Documentation and guides
├── bruno/                   # API testing collections
└── README.md               # This file
```

### Key Components
- **Parser Service**: High-performance Go service that parses CS2 demo files and extracts game events
- **Web Application**: Laravel backend with React frontend for user interface and data management
- **Test Harness**: Comprehensive CLI tool for testing parser service functionality
- **Documentation**: Detailed guides for development, coding standards, and task execution

---

## 🚀 Quick Start

### Prerequisites
- [ ] Go 1.21 or higher
- [ ] PHP 8.3 or higher
- [ ] Node.js 18+ and npm
- [ ] MySQL 8.0+ or PostgreSQL 13+

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

# Set up test harness
cd ../parser-test
go mod download
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

# Test harness
cd ../parser-test
go test
```

---

## 🛠️ Development Setup

### Environment Configuration
```bash
# Web application environment
cd web-app
cp .env.example .env
# Edit .env with your database and service configurations

# Parser service configuration
cd ../parser-service
cp config.yaml.backup config.yaml
# Edit config.yaml with your service settings
```

### Development Workflow
1. **Code Changes**: Make your changes following the coding standards
2. **Testing**: Run tests locally for each component
3. **Linting**: Check code quality with project linters
4. **Formatting**: Format code with project formatters
5. **Commit**: Use conventional commits

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

# Test Harness
cd parser-test
go test                  # Run tests
go build -o parser-test . # Build CLI tool
```

### Code Quality Tools
- **Go**: `golangci-lint` - Comprehensive Go linting
- **PHP**: `Laravel Pint` - PHP code formatting
- **TypeScript**: `ESLint` + `Prettier` - Code quality and formatting

---

## 📚 Documentation

### Essential Reading
- **[Task Execution Guide](docs/TASK_EXECUTION_GUIDE.md)** - Development workflow and process
- **[Coding Standards](docs/CODING_STANDARDS.md)** - Code style and conventions
- **[Parser Service README](parser-service/README.md)** - Parser service architecture and API
- **[Web App README](web-app/README.md)** - Web application setup and features
- **[Test Harness README](parser-test/README.md)** - Testing framework and usage

### Additional Resources
- **[Bruno API Collections](bruno/)** - API testing and documentation
- **[Example Data](example%20event%20data%20responses/)** - Sample event data structures

---

## 🧪 Testing

### Test Structure
```
tests/
├── parser-service/        # Go unit and integration tests
├── web-app/tests/         # PHP feature and unit tests
├── parser-test/           # CLI testing harness
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

# Integration Tests
cd parser-test
./parser-test --demo path/to/demo.dem
```

### Test Requirements
- [ ] All new code must have corresponding tests
- [ ] Bug fixes must include regression tests
- [ ] API changes must include integration tests
- [ ] Minimum coverage: 80%

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
| `APP_KEY` | Laravel application key | - | ✅ |

### Configuration Files
- **`.env`** - Web application environment variables
- **`config.yaml`** - Parser service configuration
- **`package.json`** - Frontend dependencies and scripts
- **`composer.json`** - Backend dependencies and scripts

---

## 🚀 Deployment

### Production Deployment

*Production deployment instructions will be added here.*

---

---

---

## 📊 Monitoring & Observability

### Health Checks
- **Parser Service**: `GET /health` and `GET /ready`
- **Web Application**: `GET /up` (Laravel health check)
- **Database**: Connection status monitoring

### Logging
- **Format**: JSON for parser service, Laravel logs for web app
- **Levels**: `debug`, `info`, `warn`, `error`
- **Location**: `parser-service/logs/` and `web-app/storage/logs/`

### Metrics
- **Performance**: Demo parsing time, API response times
- **Business**: Matches processed, users registered
- **Infrastructure**: CPU, memory, disk usage

---

---

## 📈 Performance

### Performance Characteristics
- **Demo Parsing**: ~2-5 minutes for typical match demos
- **API Response**: <200ms for most endpoints
- **Concurrent Processing**: Up to 3 demos simultaneously
- **Resource Usage**: ~512MB RAM per parser instance

### Optimization Guidelines
- [ ] Use appropriate batch sizes for event processing
- [ ] Implement caching for frequently accessed data
- [ ] Optimize database queries with proper indexing
- [ ] Monitor performance metrics regularly

---

---

## 📄 License

MIT License - See [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- [demoinfocs-golang](https://github.com/markus-wa/demoinfocs-golang) - CS2 demo parsing library
- [Laravel](https://laravel.com) - PHP web framework
- [React](https://reactjs.org) - JavaScript UI library
- [Tailwind CSS](https://tailwindcss.com) - CSS framework

---

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
- **Test Harness**: CLI testing tool in `parser-test/` with comprehensive assertions
- **Documentation**: Detailed guides in `docs/` folder

### Dependencies
- **Critical**: demoinfocs-golang, Laravel, React, MySQL/PostgreSQL
- **Optional**: Docker for deployment, Bruno for API testing
- **Avoid**: Breaking changes to database schema without explicit permission

---

*Last updated: September 6, 2025*
*Version: 1.0.0*
