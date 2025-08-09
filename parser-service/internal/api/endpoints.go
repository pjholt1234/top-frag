package api

// Endpoints defines all API endpoints used in the parser service
const (
	// Health endpoints
	HealthEndpoint     = "/health"
	ReadinessEndpoint  = "/ready"
	
	// API endpoints
	ParseDemoEndpoint  = "parse-demo"
	
	// Event data endpoints - new format
	JobEventEndpoint   = "/api/job/%s/event/%s"
)

// Event types for the new endpoint format
const (
	EventTypeRound    = "round"
	EventTypeGunfight = "gunfight"
	EventTypeGrenade  = "grenade"
	EventTypeDamage   = "damage"
) 