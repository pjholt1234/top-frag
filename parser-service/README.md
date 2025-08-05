# Parser Service

## Development

The simplest way to develop:

```bash
# Install Go if you haven't already
# brew install go  # on macOS

# Run the service
go run main.go
```

Visit http://localhost:8080


## Testing

Run the test suite:

```bash
# Run all tests
go test

# Run tests with verbose output
go test -v

# Run tests with coverage
go test -cover

# Run tests and generate coverage report
go test -coverprofile=coverage.out
go tool cover -html=coverage.out
```


## Production

For production deployment using Docker:

```bash
docker compose up --build
```