package main

import (
	"context"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/sirupsen/logrus"
	"parser-service/internal/api/handlers"
	"parser-service/internal/api/middleware"
	"parser-service/internal/config"
	"parser-service/internal/parser"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		fmt.Printf("Failed to load configuration: %v\n", err)
		os.Exit(1)
	}

	logger := setupLogger(cfg)
	logger.Info("Starting CS:GO Demo Parser Service")

	demoParser := parser.NewDemoParser(cfg, logger)
	batchSender := parser.NewBatchSender(cfg, logger)

	parseDemoHandler := handlers.NewParseDemoHandler(cfg, logger, demoParser, batchSender)
	healthHandler := handlers.NewHealthHandler(logger)

	router := setupRouter(parseDemoHandler, healthHandler, cfg)

	server := &http.Server{
		Addr:         ":" + cfg.Server.Port,
		Handler:      router,
		ReadTimeout:  cfg.Server.ReadTimeout,
		WriteTimeout: cfg.Server.WriteTimeout,
		IdleTimeout:  cfg.Server.IdleTimeout,
	}

	// Starts the HTTP server listen for incoming request in a goroutine
	// This creates a concurrent operation that doesn't block the main thread
	// The best way to think about this is Jobs + Queues in laravel.

	// The main difference is that Laravel jobs are persistent (stored in database/Redis) 
	// while Go goroutines are ephemeral (in-memory only). Laravel jobs survive server 
	// restarts, while goroutines don't!
	go func() {
		logger.WithField("port", cfg.Server.Port).Info("Starting HTTP server")
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.WithError(err).Fatal("Failed to start server")
		}
	}()
	
	// This is pretty confusing....
	// This creates a channel, allowing the gorountine to listen for OS signals
	// When a signal is received, it's sent to the channel
	// This allows the program to handle OS signals gracefully
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	logger.Info("Shutting down server...")

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := server.Shutdown(ctx); err != nil {
		logger.WithError(err).Fatal("Server forced to shutdown")
	}

	logger.Info("Server exited")
}

func setupLogger(cfg *config.Config) *logrus.Logger {
	logger := logrus.New()

	level, err := logrus.ParseLevel(cfg.Logging.Level)
	if err != nil {
		logger.WithError(err).Warn("Invalid log level, using info")
		level = logrus.InfoLevel
	}
	logger.SetLevel(level)

	if cfg.Logging.Format == "json" {
		logger.SetFormatter(&logrus.JSONFormatter{})
	} else {
		logger.SetFormatter(&logrus.TextFormatter{
			FullTimestamp: true,
		})
	}

	return logger
}

func setupRouter(parseDemoHandler *handlers.ParseDemoHandler, healthHandler *handlers.HealthHandler, cfg *config.Config) *gin.Engine {
	gin.SetMode(gin.ReleaseMode)

	router := gin.New()

	router.Use(gin.Recovery())
	router.Use(loggingMiddleware())

	// Health endpoints (public)
	router.GET("/health", healthHandler.HandleHealth)
	router.GET("/ready", healthHandler.HandleReadiness)

	// API endpoints (require authentication)
	api := router.Group("/api")
	api.Use(middleware.APIKeyAuth(cfg.Server.APIKey))
	{
		api.POST("/parse-demo", parseDemoHandler.HandleParseDemo) // File upload endpoint
		api.GET("/job/:job_id", parseDemoHandler.GetJobStatus)
	}

	return router
}

func loggingMiddleware() gin.HandlerFunc {
	return gin.LoggerWithFormatter(func(param gin.LogFormatterParams) string {
		return fmt.Sprintf("%s - [%s] \"%s %s %s %d %s \"%s\" %s\"\n",
			param.ClientIP,
			param.TimeStamp.Format(time.RFC1123),
			param.Method,
			param.Path,
			param.Request.Proto,
			param.StatusCode,
			param.Latency,
			param.Request.UserAgent(),
			param.ErrorMessage,
		)
	})
}