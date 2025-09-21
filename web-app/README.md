# Top Frag Web Application

A modern Laravel/React web application for CS2 demo analysis, providing a comprehensive dashboard for match history, grenade library, and user authentication. Built with Laravel 12 backend and React 19 frontend with TypeScript.

## 🎯 Overview

The Web Application is the user-facing component of the Top Frag platform, providing:
- **User Authentication**: Secure registration and login with Laravel Sanctum
- **Match Management**: Upload, track, and analyze CS2 demo files
- **Grenade Library**: Browse and favorite grenade throws from matches
- **Dashboard**: Comprehensive analytics and match statistics
- **Real-time Updates**: Live progress tracking for demo processing

## 🏗️ Architecture Overview

The application follows a modern full-stack architecture with clear separation between backend and frontend:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   React SPA     │    │  Laravel API    │    │ Parser Service  │
│   (Frontend)    │◀──▶│   (Backend)     │◀──▶│   (Go)          │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Browser       │    │   Database      │    │ Demo Files      │
│   (UI/UX)       │    │   (MySQL)       │    │ (.dem)          │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 📁 Project Structure

```
web-app/
├── app/                    # Laravel application code
│   ├── Console/           # Artisan commands
│   ├── Enums/             # PHP enumerations
│   ├── Exceptions/        # Custom exceptions
│   ├── Http/              # HTTP layer
│   │   ├── Controllers/   # API controllers
│   │   ├── Middleware/    # HTTP middleware
│   │   ├── Requests/      # Form request validation
│   │   └── Resources/     # API resource transformers
│   ├── Jobs/              # Background job processing
│   ├── Models/            # Eloquent models
│   ├── Observers/         # Model observers
│   ├── Providers/         # Service providers
│   └── Services/          # Business logic services
├── resources/             # Frontend resources
│   ├── js/               # React/TypeScript code
│   │   ├── components/   # React components
│   │   ├── hooks/        # Custom React hooks
│   │   ├── lib/          # Utility libraries
│   │   └── pages/        # Page components
│   └── views/            # Blade templates
├── routes/                # Route definitions
├── tests/                 # Test suites
├── composer.json          # PHP dependencies
├── package.json           # Node.js dependencies
└── vite.config.js         # Vite build configuration
```

## 🚀 Features

### User Authentication
- **Registration**: User account creation with email validation
- **Login**: Secure authentication with Laravel Sanctum
- **Protected Routes**: Route protection for authenticated users

### Match Management
- **Demo Upload**: Upload CS2 demo files for processing
- **Match History**: View and browse your match history
- **Match Details**: Detailed match analysis and statistics
- **Sharecode Integration**: Automatic demo URL retrieval via valve-demo-url-service

### Grenade Library
- **Browse Grenades**: Filter and search grenade throws
- **Favorites System**: Save and manage favorite grenades
- **Advanced Filtering**: Filter by map, grenade type, effectiveness

## 🛠️ Development Setup

### Prerequisites
- PHP 8.3 or higher
- Node.js 18+ and npm
- MySQL 8.0+ or PostgreSQL 13+
- Composer for PHP dependencies

### Installation
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file
cp .env.example .env

# Configure environment variables
# Add the following to your .env file:
# VALVE_DEMO_URL_SERVICE_BASE_URL=http://localhost:3001
# VALVE_DEMO_URL_SERVICE_API_KEY=your_api_key

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Build frontend assets
npm run build
```

### Development Workflow
```bash
# Start development servers
composer run dev

# Or start individually:
php artisan serve    # Backend (Laravel)
npm run dev         # Frontend (Vite)
php artisan queue:work  # Queue worker
```

## 🧪 Testing

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Frontend tests
npm run test
```

## 🌐 API Endpoints

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `GET /api/auth/user` - Get current user
- `POST /api/auth/logout` - User logout

### Matches
- `GET /api/matches` - List user matches
- `GET /api/matches/{id}` - Get match details
- `POST /api/user/upload/demo` - Upload demo file

### Grenade Library
- `GET /api/grenade-library` - Browse grenades
- `GET /api/grenade-favourites` - List user favorites
- `POST /api/grenade-favourites` - Add favorite
- `DELETE /api/grenade-favourites/{id}` - Remove favorite

## 🔧 Configuration

### Valve Demo URL Service
The application integrates with the valve-demo-url-service for automatic demo URL retrieval from sharecodes:

```bash
# Environment variables for valve-demo-url-service
VALVE_DEMO_URL_SERVICE_BASE_URL=http://localhost:3001
VALVE_DEMO_URL_SERVICE_API_KEY=your_api_key
```

The service provides:
- **Sharecode Decoding**: Converts CS2 sharecodes to demo URLs
- **Steam Integration**: Connects to Steam Game Coordinator
- **Rate Limiting**: Built-in request throttling
- **API Authentication**: Secure API key-based access

## 🔒 Security

### Authentication & Authorization
- **Laravel Sanctum**: Token-based API authentication
- **Protected Routes**: Middleware protection for authenticated endpoints
- **API Key Authentication**: Secure communication with parser service and valve-demo-url-service

### Data Protection
- **Input Validation**: Form request validation for all inputs
- **SQL Injection Prevention**: Eloquent ORM with parameterized queries
- **XSS Protection**: Blade template escaping and React sanitization

## 🚀 Deployment

### Production Build
```bash
# Install production dependencies
composer install --optimize-autoloader --no-dev

# Build frontend assets
npm run build

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force
```

## 🔍 Troubleshooting

### Common Issues

#### Issue: Frontend Build Fails
**Symptoms**: Vite build errors or TypeScript compilation issues
**Solution**: Check TypeScript configuration and dependencies
```bash
# Clear node modules and reinstall
rm -rf node_modules package-lock.json
npm install
```

#### Issue: API Authentication Fails
**Symptoms**: 401 Unauthorized errors on API requests
**Solution**: Check Sanctum configuration and token handling
```bash
# Clear application cache
php artisan config:clear
php artisan cache:clear
```

## 📄 License

MIT License - See [LICENSE](LICENSE) file for details.

---

*Last updated: September 6, 2025*
*Version: 1.0.0*
