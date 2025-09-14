package main

import (
	"context"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"parser-service/internal/api"
	"parser-service/internal/api/handlers"
	"parser-service/internal/api/middleware"
	"parser-service/internal/config"
	"parser-service/internal/parser"
	"parser-service/internal/types"

	"github.com/gin-gonic/gin"
	"github.com/sirupsen/logrus"
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

	// Create a no-op progress callback for the ProgressManager
	progressCallback := func(update types.ProgressUpdate) {
		// This will be overridden by the actual progress callback in the demo parser
	}
	progressManager := parser.NewProgressManager(logger, progressCallback, 100*time.Millisecond)

	parseDemoHandler := handlers.NewParseDemoHandler(cfg, logger, demoParser, batchSender, progressManager)
	healthHandler := handlers.NewHealthHandler(logger)

	router := setupRouter(parseDemoHandler, healthHandler, cfg)

	server := &http.Server{
		Addr:         ":" + cfg.Server.Port,
		Handler:      router,
		ReadTimeout:  cfg.Server.ReadTimeout,
		WriteTimeout: cfg.Server.WriteTimeout,
		IdleTimeout:  cfg.Server.IdleTimeout,
	}

	go func() {
		logger.WithField("port", cfg.Server.Port).Info("Starting HTTP server")
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.WithError(err).Fatal("Failed to start server")
		}
	}()

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

	// Setup main log file
	if cfg.Logging.File != "" {
		file, err := os.OpenFile(cfg.Logging.File, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
		if err != nil {
			logger.WithError(err).Warn("Failed to open log file, using stdout only")
		} else {
			logger.SetOutput(file)
		}
	}

	// Setup error log file hook
	if cfg.Logging.ErrorFile != "" {
		errorHook, err := config.NewErrorLogHook(cfg.Logging.ErrorFile)
		if err != nil {
			logger.WithError(err).Warn("Failed to setup error log hook")
		} else {
			logger.AddHook(errorHook)
		}
	}

	return logger
}

func setupRouter(parseDemoHandler *handlers.ParseDemoHandler, healthHandler *handlers.HealthHandler, cfg *config.Config) *gin.Engine {
	gin.SetMode(gin.ReleaseMode)

	router := gin.New()

	router.Use(gin.Recovery())
	router.Use(loggingMiddleware())

	router.GET(api.HealthEndpoint, healthHandler.HandleHealth)
	router.GET(api.ReadinessEndpoint, healthHandler.HandleReadiness)

	apiGroup := router.Group("/api")
	apiGroup.Use(middleware.APIKeyAuth(cfg.Server.APIKey))
	apiGroup.POST(api.ParseDemoEndpoint, parseDemoHandler.HandleParseDemo)

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
