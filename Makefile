# Top Frag Development Makefile

.PHONY: help start stop restart logs status cleanup install-deps dev

# Default target
help: ## Show this help message
	@echo "Top Frag Development Commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Quick Start:"
	@echo "  make dev    # Start development environment"
	@echo "  make logs  # View logs"
	@echo "  make stop  # Stop services"

dev: ## Start development environment
	@echo "🚀 Starting Top Frag development environment..."
	@./docker-dev.sh start

start: dev ## Alias for dev

stop: ## Stop all services
	@echo "🛑 Stopping all services..."
	@./docker-dev.sh stop

restart: ## Restart all services
	@echo "🔄 Restarting all services..."
	@./docker-dev.sh restart

logs: ## View logs for all services
	@./docker-dev.sh logs

logs-web: ## View logs for web application
	@./docker-dev.sh logs web-app

logs-parser: ## View logs for parser service
	@./docker-dev.sh logs parser-service

logs-valve: ## View logs for valve demo URL service
	@./docker-dev.sh logs valve-demo-url-service

status: ## Show service status
	@./docker-dev.sh status

cleanup: ## Clean up Docker resources
	@echo "🧹 Cleaning up Docker resources..."
	@./docker-dev.sh cleanup

install-deps: ## Install development dependencies
	@echo "📦 Installing development dependencies..."
	@./docker-dev.sh install-deps

# Service-specific commands
web-shell: ## Open shell in web application container
	@docker compose exec web-app bash

parser-shell: ## Open shell in parser service container
	@docker compose exec parser-service sh

valve-shell: ## Open shell in valve demo URL service container
	@docker compose exec valve-demo-url-service sh

mysql-shell: ## Open MySQL shell
	@docker compose exec mysql mysql -u top_frag -p top_frag

# Development utilities
test: ## Run tests for all services
	@echo "🧪 Running tests..."
	@docker compose exec web-app php artisan test
	@docker compose exec parser-service go test ./...
	@docker compose exec valve-demo-url-service npm test

build: ## Build all services
	@echo "🔨 Building all services..."
	@docker compose build

pull: ## Pull latest images
	@echo "📥 Pulling latest images..."
	@docker compose pull

# Database commands
migrate: ## Run Laravel migrations
	@docker compose exec web-app php artisan migrate

migrate-fresh: ## Fresh migrate with seeding
	@docker compose exec web-app php artisan migrate:fresh --seed

seed: ## Run database seeders
	@docker compose exec web-app php artisan db:seed

# Cache commands
cache-clear: ## Clear all caches
	@docker compose exec web-app php artisan cache:clear
	@docker compose exec web-app php artisan config:clear
	@docker compose exec web-app php artisan route:clear
	@docker compose exec web-app php artisan view:clear

# Monitoring
monitor: ## Monitor all services
	@echo "📊 Monitoring services (Ctrl+C to stop)..."
	@watch -n 2 'docker compose ps'

# Quick development workflow
quick-start: ## Quick start with environment setup
	@echo "⚡ Quick start setup..."
	@cp docker.env.example .env 2>/dev/null || echo "⚠️  .env file already exists"
	@make dev
