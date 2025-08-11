.PHONY: help test test-verbose test-coverage test-coverage-html test-coverage-func clean build run

# Default target
help:
	@echo "Available commands:"
	@echo "  test              - Run tests"
	@echo "  test-verbose      - Run tests with verbose output"
	@echo "  test-coverage     - Run tests with coverage report"
	@echo "  test-coverage-html- Run tests and generate HTML coverage report"
	@echo "  test-coverage-func- Run tests and show function coverage"
	@echo "  clean             - Clean build artifacts"
	@echo "  build             - Build the application"
	@echo "  run               - Run the application"

# Run tests
test:
	go test ./...

# Run tests with verbose output
test-verbose:
	go test -v ./...

# Run tests with coverage
test-coverage:
	go test -coverprofile=coverage.out ./...
	go tool cover -func=coverage.out

# Run tests and generate HTML coverage report
test-coverage-html: test-coverage
	go tool cover -html=coverage.out -o coverage.html
	@echo "HTML coverage report generated: coverage.html"

# Run tests and show function coverage
test-coverage-func:
	go test -coverprofile=coverage.out ./...
	go tool cover -func=coverage.out

# Check coverage threshold
test-coverage-threshold: test-coverage
	@COVERAGE=$$(go tool cover -func=coverage.out | grep total | awk '{print $$3}' | sed 's/%//'); \
	echo "Coverage: $$COVERAGE%"; \
	if (( $$(echo "$$COVERAGE < 70" | bc -l) )); then \
		echo "Coverage is below 70% threshold"; \
		exit 1; \
	fi; \
	echo "Coverage threshold met: $$COVERAGE%"

# Clean build artifacts
clean:
	rm -f coverage.out coverage.html
	rm -f parser-service
	go clean

# Build the application
build:
	go build -o parser-service .

# Run the application
run:
	go run .

# Install dependencies
deps:
	go mod download
	go mod tidy

# Format code
fmt:
	go fmt ./...

# Run linter
lint:
	golangci-lint run

# Run all checks (format, lint, test)
check: fmt lint test-coverage-threshold 