#!/bin/bash

# Top Frag Development Startup Script
# This script starts Docker services and the native Go parser service

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check if Docker is running
check_docker() {
    if ! command_exists docker; then
        print_error "Docker is not installed or not in PATH"
        exit 1
    fi

    if ! docker info >/dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker Desktop"
        exit 1
    fi
}

# Function to check if Go is installed
check_go() {
    if ! command_exists go; then
        print_error "Go is not installed or not in PATH"
        print_error "Please install Go from https://golang.org/dl/"
        exit 1
    fi

    print_success "Go is installed: $(go version)"
}

# Function to check if Air is installed
check_air() {
    if ! command_exists air; then
        print_warning "Air is not installed. Installing Air for hot reloading..."
        go install github.com/air-verse/air@latest
        if [ $? -eq 0 ]; then
            print_success "Air installed successfully"
        else
            print_error "Failed to install Air"
            exit 1
        fi
    else
        print_success "Air is installed: $(air -v)"
    fi
}

# Function to start Docker services
start_docker_services() {
    print_status "Starting Docker services..."
    
    # Start Docker services in background
    docker compose up -d
    
    if [ $? -eq 0 ]; then
        print_success "Docker services started successfully"
    else
        print_error "Failed to start Docker services"
        exit 1
    fi
}

# Function to wait for services to be ready
wait_for_services() {
    print_status "Waiting for services to be ready..."
    
    # Wait for web-app to be ready
    print_status "Waiting for web-app to be ready..."
    timeout=60
    while [ $timeout -gt 0 ]; do
        if curl -s http://localhost:8000/api/auth/user >/dev/null 2>&1; then
            print_success "Web-app is ready"
            break
        fi
        sleep 1
        timeout=$((timeout - 1))
    done
    
    if [ $timeout -eq 0 ]; then
        print_warning "Web-app may not be fully ready, but continuing..."
    fi
    
    # Wait for Redis to be ready
    print_status "Waiting for Redis to be ready..."
    timeout=30
    while [ $timeout -gt 0 ]; do
        if docker compose exec redis redis-cli ping >/dev/null 2>&1; then
            print_success "Redis is ready"
            break
        fi
        sleep 1
        timeout=$((timeout - 1))
    done
    
    if [ $timeout -eq 0 ]; then
        print_warning "Redis may not be fully ready, but continuing..."
    fi
}

# Function to start Go parser service
start_go_parser() {
    print_status "Starting Go parser service with Air hot reloading..."
    
    # Change to parser-service directory
    cd parser-service
    
    # Check if config file exists
    if [ ! -f "config.yaml" ]; then
        print_error "config.yaml not found in parser-service directory"
        exit 1
    fi
    
    # Start Air with hot reloading
    print_status "Starting Air hot reloading for Go parser service..."
    air -c .air.toml
}

# Function to cleanup on exit
cleanup() {
    print_status "Shutting down services..."
    
    # Kill Air process if running
    if [ ! -z "$AIR_PID" ]; then
        kill $AIR_PID 2>/dev/null || true
    fi
    
    # Stop Docker services
    docker compose down
    
    print_success "Cleanup completed"
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Main execution
main() {
    print_status "Starting Top Frag Development Environment"
    print_status "========================================"
    
    # Check prerequisites
    check_docker
    check_go
    check_air
    
    # Start Docker services
    start_docker_services
    
    # Wait for services to be ready
    wait_for_services
    
    print_success "All Docker services are running!"
    print_status "Services available at:"
    print_status "  - Web App: http://localhost:8000"
    print_status "  - Vite Dev Server: http://localhost:5173"
    print_status "  - Valve Demo URL Service: http://localhost:3001"
    print_status "  - Redis: localhost:6379"
    print_status ""
    print_status "Starting Go parser service with hot reloading..."
    print_status "Press Ctrl+C to stop all services"
    print_status "========================================"
    
    # Start Go parser service
    start_go_parser
}

# Run main function
main "$@"
