FROM golang:1.25-alpine

# Install air for hot reloading and other dependencies
RUN go install github.com/air-verse/air@latest

# Install system dependencies
RUN apk add --no-cache \
    git \
    make \
    gcc \
    musl-dev

WORKDIR /app

# Copy go mod files first for better caching
COPY go.mod go.sum ./
RUN go mod download

# Copy source code
COPY . .

# Create logs directory
RUN mkdir -p logs

# Create tmp directory for air
RUN mkdir -p tmp

# Set permissions
RUN chmod +x tmp

EXPOSE 8080

# Default command for production build
CMD ["go", "run", "main.go"] 