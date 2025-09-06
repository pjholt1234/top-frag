# Parser Test Harness Makefile

# Go parameters
GOCMD=go
GOBUILD=$(GOCMD) build
GOCLEAN=$(GOCMD) clean
GOTEST=$(GOCMD) test
GOGET=$(GOCMD) get
GOMOD=$(GOCMD) mod
BINARY_NAME=parser-test
BINARY_UNIX=$(BINARY_NAME)_unix

# Build flags
LDFLAGS=-ldflags "-X main.version=$(VERSION) -X main.buildTime=$(shell date -u '+%Y-%m-%d_%H:%M:%S')"

.PHONY: all build clean test deps help run-example

all: clean deps build

## build: Build the binary
build:
	$(GOBUILD) $(LDFLAGS) -o $(BINARY_NAME) -v .

## build-linux: Build the binary for Linux
build-linux:
	CGO_ENABLED=0 GOOS=linux GOARCH=amd64 $(GOBUILD) $(LDFLAGS) -o $(BINARY_UNIX) -v .

## clean: Clean build artifacts
clean:
	$(GOCLEAN)
	rm -f $(BINARY_NAME)
	rm -f $(BINARY_UNIX)

## test: Run tests
test:
	$(GOTEST) -v ./...

## test-coverage: Run tests with coverage
test-coverage:
	$(GOTEST) -v -coverprofile=coverage.out ./...
	$(GOCMD) tool cover -html=coverage.out

## deps: Download and tidy dependencies
deps:
	$(GOMOD) download
	$(GOMOD) tidy

## fmt: Format code
fmt:
	$(GOCMD) fmt ./...

## vet: Run go vet
vet:
	$(GOCMD) vet ./...

## lint: Run golangci-lint (requires golangci-lint to be installed)
lint:
	golangci-lint run

## install: Install the binary
install: build
	cp $(BINARY_NAME) /usr/local/bin/

## run-example: Run example with sample demo (requires demo file)
run-example:
	@if [ ! -f "example.dem" ]; then \
		echo "Error: example.dem not found. Please provide a demo file."; \
		exit 1; \
	fi
	./$(BINARY_NAME) --demo example.dem --log

## run-damage-test: Run only damage tests
run-damage-test:
	@if [ ! -f "example.dem" ]; then \
		echo "Error: example.dem not found. Please provide a demo file."; \
		exit 1; \
	fi
	./$(BINARY_NAME) --demo example.dem --test damage --log

## run-json: Run with JSON output
run-json:
	@if [ ! -f "example.dem" ]; then \
		echo "Error: example.dem not found. Please provide a demo file."; \
		exit 1; \
	fi
	./$(BINARY_NAME) --demo example.dem --format json

## help: Show this help message
help:
	@echo "Available targets:"
	@sed -n 's/^##//p' $(MAKEFILE_LIST) | column -t -s ':' | sed -e 's/^/ /'

# Default target
.DEFAULT_GOAL := help
