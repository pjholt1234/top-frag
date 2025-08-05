FROM golang:1.21-alpine

WORKDIR /app

# Copy go mod files first for better caching
COPY go.mod ./
RUN go mod download

# Copy source code
COPY . .

# Build the application
RUN go build -o main .

# Make sure the binary is executable
RUN chmod +x main

EXPOSE 8080

# Default command for production build
CMD ["./main"] 