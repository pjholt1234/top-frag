package utils

import (
	"fmt"
	"math"
	"parser-service/internal/config"
	"parser-service/internal/types"
	"runtime"
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/sirupsen/logrus"
)

type CalculationCache struct {
	distanceCache map[string]float64
	vectorCache   map[string]Vector3
	fovCache      map[string]bool
	mutex         sync.RWMutex
}

type ObjectPool struct {
	vectorPool   sync.Pool
	trianglePool sync.Pool
	resultPool   sync.Pool
}

type AimUtilityService struct {
	losDetector *LOSDetector
	cache       *CalculationCache
	pool        *ObjectPool
	logger      *logrus.Logger
	config      *config.Config
}

const (
	EngagementGapSeconds     = 5
	ReactionTimeSearchWindow = 128
	MaxParallelLOSWorkers    = 9
	LOSCheckInterval         = 2
	MovementThreshold        = 5.0
)

// Aim Rating Constants
const (
	// Accuracy bounds (percentage)
	AccuracyMaxScore = 25.0
	AccuracyMinScore = 0.0
	AccuracyMaxValue = 90.0 // >= 90% gets max score
	AccuracyMinValue = 10.0 // <= 10% gets min score

	// Crosshair placement bounds (degrees)
	CrosshairMaxScore = 25.0
	CrosshairMinScore = 0.0
	CrosshairMaxValue = 5.0  // <= 5 degrees gets max score
	CrosshairMinValue = 30.0 // >= 30 degrees gets min score

	// Time to damage bounds (milliseconds)
	TimeToDamageMaxScore = 20.0
	TimeToDamageMinScore = 0.0
	TimeToDamageMaxValue = 250.0 // <= 250ms gets max score
	TimeToDamageMinValue = 750.0 // >= 750ms gets min score

	// Headshot accuracy bounds (percentage)
	HeadshotMaxScore = 15.0
	HeadshotMinScore = 0.0
	HeadshotMaxValue = 90.0 // >= 90% gets max score
	HeadshotMinValue = 10.0 // <= 10% gets min score

	// Spray accuracy bounds (percentage)
	SprayMaxScore = 15.0
	SprayMinScore = 0.0
	SprayMaxValue = 90.0 // >= 90% gets max score
	SprayMinValue = 10.0 // <= 10% gets min score
)

func NewAimUtilityService(mapName string, cfg *config.Config) (*AimUtilityService, error) {
	losDetector, err := NewLOSDetector(mapName)
	if err != nil {
		return nil, err
	}

	return &AimUtilityService{
		losDetector: losDetector,
		cache: &CalculationCache{
			distanceCache: make(map[string]float64),
			vectorCache:   make(map[string]Vector3),
			fovCache:      make(map[string]bool),
		},
		pool: &ObjectPool{
			vectorPool: sync.Pool{
				New: func() interface{} { return &Vector3{} },
			},
			trianglePool: sync.Pool{
				New: func() interface{} { return &Triangle{} },
			},
			resultPool: sync.Pool{
				New: func() interface{} { return &LOSResult{} },
			},
		},
		logger: logrus.StandardLogger(),
		config: cfg,
	}, nil
}

// shouldProcessPlayer checks if we should process this player based on environment and Steam ID
func (aus *AimUtilityService) shouldProcessPlayer(playerID string) bool {
	// In development environment, process all players
	if aus.config != nil && aus.config.Environment == "development" {
		return true
	}

	// In other environments, only process specific Steam ID
	return playerID == "76561198081165057"
}

func (aus *AimUtilityService) ProcessAimTrackingForRound(
	shootingData []types.PlayerShootingData,
	damageEvents []types.DamageEvent,
	playerTickData []types.PlayerTickData,
	roundNumber int,
) ([]types.AimAnalysisResult, []types.WeaponAimAnalysisResult, error) {
	playerShootingData := aus.groupShootingDataByPlayer(shootingData, roundNumber)
	playerDamageEvents := aus.groupDamageEventsByPlayer(damageEvents, roundNumber)

	var aimResults []types.AimAnalysisResult
	var weaponResults []types.WeaponAimAnalysisResult

	reactionTimes := aus.calculateReactionTimesFromDamageEvents(damageEvents, playerTickData, roundNumber)

	for playerID, shots := range playerShootingData {
		if !aus.shouldProcessPlayer(playerID) {
			continue
		}

		damages := playerDamageEvents[playerID]

		shotAnalyses := aus.analyzeShots(shots, damages, playerTickData, playerID, roundNumber)

		aimResult := aus.calculatePlayerAimStatsFromAnalysis(playerID, roundNumber, shotAnalyses)

		if reactionTime, exists := reactionTimes[playerID]; exists {
			if reactionTime >= 50.0 {
				if aimResult.AverageTimeToDamage > 0 {
					aimResult.AverageTimeToDamage = (aimResult.AverageTimeToDamage + reactionTime) / 2.0
				} else {
					aimResult.AverageTimeToDamage = reactionTime
				}
			}
		}

		aimResults = append(aimResults, aimResult)

		weaponStats := aus.calculateWeaponAimStatsFromAnalysis(playerID, roundNumber, shotAnalyses)
		weaponResults = append(weaponResults, weaponStats...)
	}

	return aimResults, weaponResults, nil
}

// calculateReactionTimesFromDamageEvents calculates reaction times using time-gap approach
func (aus *AimUtilityService) calculateReactionTimesFromDamageEvents(
	damageEvents []types.DamageEvent,
	playerTickData []types.PlayerTickData,
	roundNumber int,
) map[string]float64 {
	reactionTimes := make(map[string]float64)

	// Sort damage events by timestamp using optimized sort
	sortedDamageEvents := make([]types.DamageEvent, len(damageEvents))
	copy(sortedDamageEvents, damageEvents)

	sort.Slice(sortedDamageEvents, func(i, j int) bool {
		return sortedDamageEvents[i].TickTimestamp < sortedDamageEvents[j].TickTimestamp
	})

	// Find first shots using time gap approach
	firstShots := aus.identifyFirstShots(sortedDamageEvents)

	// Pre-index player tick data by PlayerID for O(1) lookups
	indexStart := time.Now()
	tickDataByPlayer := make(map[string][]types.PlayerTickData)
	for i := range playerTickData {
		tick := &playerTickData[i]
		tickDataByPlayer[tick.PlayerID] = append(tickDataByPlayer[tick.PlayerID], *tick)
	}

	// Ensure each player's ticks are sorted by tick
	for playerID := range tickDataByPlayer {
		sort.Slice(tickDataByPlayer[playerID], func(i, j int) bool {
			return tickDataByPlayer[playerID][i].Tick < tickDataByPlayer[playerID][j].Tick
		})
	}

	if aus.logger != nil {
		elapsed := time.Since(indexStart)
		aus.logger.WithFields(logrus.Fields{
			"label":       "player_tick_indexing",
			"duration_ms": elapsed.Milliseconds(),
			"tick_count":  len(playerTickData),
			"players":     len(tickDataByPlayer),
		}).Info("performance")
	}

	// Calculate reaction times for first shots using indexed data
	for _, damage := range firstShots {
		if !aus.shouldProcessPlayer(damage.AttackerSteamID) {
			continue
		}

		reactionTime := aus.calculateReactionTimeForFirstShotOptimized(damage, tickDataByPlayer)
		if reactionTime >= 50.0 {
			reactionTimes[damage.AttackerSteamID] = reactionTime
		}
	}

	return reactionTimes
}

func (aus *AimUtilityService) identifyFirstShots(damageEvents []types.DamageEvent) []types.DamageEvent {
	var firstShots []types.DamageEvent
	gapTicks := int64(EngagementGapSeconds * 64)

	for i, damage := range damageEvents {
		if !aus.isGunDamage(damage) {
			continue
		}

		isFirstShot := true

		for j := i - 1; j >= 0; j-- {
			timeDiff := damage.TickTimestamp - damageEvents[j].TickTimestamp
			if timeDiff > gapTicks {
				break
			}

			if damageEvents[j].AttackerSteamID == damage.AttackerSteamID &&
				damageEvents[j].VictimSteamID == damage.VictimSteamID &&
				damageEvents[j].Weapon == damage.Weapon {
				isFirstShot = false
				break
			}
		}

		if isFirstShot {
			firstShots = append(firstShots, damage)
		}
	}

	return firstShots
}

func (aus *AimUtilityService) isGunDamage(damage types.DamageEvent) bool {
	nonGunWeapons := []string{
		"hegrenade", "he grenade", "flashbang", "flash", "smokegrenade", "smoke",
		"incgrenade", "incendiary", "molotov", "decoy",
		"knife", "knife_t", "knife_ct", "bayonet", "karambit", "m9_bayonet",
		"flip", "gut", "huntsman", "falchion", "bowie", "butterfly", "shadow_daggers",
		"zeus", "taser",
	}

	weapon := strings.ToLower(damage.Weapon)

	for _, nonGun := range nonGunWeapons {
		if strings.Contains(weapon, nonGun) {
			return false
		}
	}

	return true
}

// Deprecated: Use calculateReactionTimeForFirstShotOptimized with pre-indexed data instead
// This function is kept for backwards compatibility but performs poorly with large datasets
func (aus *AimUtilityService) calculateReactionTimeForFirstShot(
	damage types.DamageEvent,
	playerTickData []types.PlayerTickData,
) float64 {
	searchStartTick := damage.TickTimestamp - ReactionTimeSearchWindow
	if searchStartTick < 0 {
		searchStartTick = 0
	}

	var attackerTicks []types.PlayerTickData
	for _, tick := range playerTickData {
		if tick.PlayerID == damage.AttackerSteamID &&
			tick.Tick >= searchStartTick &&
			tick.Tick <= damage.TickTimestamp {
			attackerTicks = append(attackerTicks, tick)
		}
	}

	if len(attackerTicks) == 0 {
		return 0.0
	}

	sort.Slice(attackerTicks, func(i, j int) bool {
		return attackerTicks[i].Tick < attackerTicks[j].Tick
	})

	var victimTicks []types.PlayerTickData
	for _, tick := range playerTickData {
		if tick.PlayerID == damage.VictimSteamID &&
			tick.Tick >= searchStartTick &&
			tick.Tick <= damage.TickTimestamp {
			victimTicks = append(victimTicks, tick)
		}
	}

	sort.Slice(victimTicks, func(i, j int) bool {
		return victimTicks[i].Tick < victimTicks[j].Tick
	})

	visibilityTick, foundVisibility := aus.findFirstAttackerLOSParallel(attackerTicks, victimTicks, damage)

	if !foundVisibility {
		return 0.0
	}

	tickDiff := float64(damage.TickTimestamp - visibilityTick)
	reactionTimeMs := (tickDiff / 64.0) * 1000.0

	return reactionTimeMs
}

func (aus *AimUtilityService) calculateReactionTimeForFirstShotOptimized(
	damage types.DamageEvent,
	tickDataByPlayer map[string][]types.PlayerTickData,
) float64 {
	searchStartTick := damage.TickTimestamp - ReactionTimeSearchWindow
	if searchStartTick < 0 {
		searchStartTick = 0
	}

	// O(1) lookups for attacker and victim tick slices
	allAttackerTicks, hasAttacker := tickDataByPlayer[damage.AttackerSteamID]
	if !hasAttacker || len(allAttackerTicks) == 0 {
		return 0.0
	}

	allVictimTicks, hasVictim := tickDataByPlayer[damage.VictimSteamID]
	if !hasVictim || len(allVictimTicks) == 0 {
		return 0.0
	}

	attackerTicks := aus.filterTicksByRange(allAttackerTicks, searchStartTick, damage.TickTimestamp)
	if len(attackerTicks) == 0 {
		return 0.0
	}

	victimTicks := aus.filterTicksByRange(allVictimTicks, searchStartTick, damage.TickTimestamp)

	visibilityTick, foundVisibility := aus.findFirstAttackerLOSParallel(attackerTicks, victimTicks, damage)
	if !foundVisibility {
		return 0.0
	}

	tickDiff := float64(damage.TickTimestamp - visibilityTick)
	reactionTimeMs := (tickDiff / 64.0) * 1000.0
	return reactionTimeMs
}

// filterTicksByRange uses binary search to efficiently filter ticks within a range
func (aus *AimUtilityService) filterTicksByRange(
	sortedTicks []types.PlayerTickData,
	startTick, endTick int64,
) []types.PlayerTickData {
	if len(sortedTicks) == 0 {
		return nil
	}

	// Binary search for start index
	startIdx := sort.Search(len(sortedTicks), func(i int) bool {
		return sortedTicks[i].Tick >= startTick
	})
	if startIdx >= len(sortedTicks) {
		return nil
	}

	// Binary search for end index (first index with tick > endTick)
	endIdx := sort.Search(len(sortedTicks), func(i int) bool {
		return sortedTicks[i].Tick > endTick
	})

	return sortedTicks[startIdx:endIdx]
}

func (aus *AimUtilityService) checkLineOfSight(attacker, victim *types.PlayerTickData) bool {
	// Create cache key for this LOS check
	cacheKey := fmt.Sprintf("%s_%d_%s_%d", attacker.PlayerID, attacker.Tick, victim.PlayerID, victim.Tick)

	// Check cache first
	aus.cache.mutex.RLock()
	if hasLOS, exists := aus.cache.fovCache[cacheKey]; exists {
		aus.cache.mutex.RUnlock()
		return hasLOS
	}
	aus.cache.mutex.RUnlock()

	attackerPos := Vector3{
		X: float32(attacker.PositionX),
		Y: float32(attacker.PositionY),
		Z: float32(attacker.PositionZ),
	}

	victimPos := Vector3{
		X: float32(victim.PositionX),
		Y: float32(victim.PositionY),
		Z: float32(victim.PositionZ),
	}

	playerBox := Box{
		Width:  32,
		Height: 72,
		Depth:  32,
	}

	attackerCanSee, _, _ := aus.losDetector.CheckLineOfSightWithFOVAndBox(
		attackerPos, victimPos, playerBox, playerBox,
		attacker.AimX, attacker.AimY,
		victim.AimX, victim.AimY,
	)

	// Cache the result
	aus.cache.mutex.Lock()
	aus.cache.fovCache[cacheKey] = attackerCanSee
	aus.cache.mutex.Unlock()

	return attackerCanSee
}

func (aus *AimUtilityService) groupShootingDataByPlayer(
	shootingData []types.PlayerShootingData,
	roundNumber int,
) map[string][]types.PlayerShootingData {
	playerData := make(map[string][]types.PlayerShootingData)

	for _, shot := range shootingData {
		if shot.RoundNumber == roundNumber {
			playerData[shot.PlayerID] = append(playerData[shot.PlayerID], shot)
		}
	}

	return playerData
}

func (aus *AimUtilityService) groupDamageEventsByPlayer(
	damageEvents []types.DamageEvent,
	roundNumber int,
) map[string][]types.DamageEvent {
	playerDamages := make(map[string][]types.DamageEvent)

	for _, damage := range damageEvents {
		if damage.RoundNumber == roundNumber {
			playerDamages[damage.AttackerSteamID] = append(playerDamages[damage.AttackerSteamID], damage)
		}
	}

	return playerDamages
}

func (aus *AimUtilityService) calculateAverage(values []float64) float64 {
	if len(values) == 0 {
		return 0.0
	}

	sum := 0.0
	for _, value := range values {
		sum += value
	}

	return sum / float64(len(values))
}

func abs(x int64) int64 {
	if x < 0 {
		return -x
	}
	return x
}

func (aus *AimUtilityService) calculateCrosshairPlacement(
	shot types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
) (float64, float64) {

	// Create player tick lookup map for O(1) access
	playerTickMap := make(map[string]map[int64]types.PlayerTickData)
	for _, tick := range playerTickData {
		if playerTickMap[tick.PlayerID] == nil {
			playerTickMap[tick.PlayerID] = make(map[int64]types.PlayerTickData)
		}
		playerTickMap[tick.PlayerID][tick.Tick] = tick
	}

	playerTick, exists := playerTickMap[shot.PlayerID][shot.Tick]
	if !exists {
		return 0.0, 0.0
	}

	// Create damage lookup map for O(1) access
	damageByTick := make(map[int64][]types.DamageEvent)
	for _, damage := range damages {
		damageByTick[damage.TickTimestamp] = append(damageByTick[damage.TickTimestamp], damage)
	}

	var closestDamage *types.DamageEvent
	minTimeDiff := int64(10)

	// Check for damage within 10 tick window using hash map lookup
	for tickOffset := int64(-10); tickOffset <= 10; tickOffset++ {
		if damages, exists := damageByTick[shot.Tick+tickOffset]; exists {
			for i, damage := range damages {
				timeDiff := abs(shot.Tick - damage.TickTimestamp)
				if timeDiff <= minTimeDiff {
					minTimeDiff = timeDiff
					closestDamage = &damages[i]
				}
			}
		}
	}

	if closestDamage == nil {
		return 0.0, 0.0
	}

	// Find victim tick using hash map lookup
	victimTickMap := playerTickMap[closestDamage.VictimSteamID]
	if victimTickMap == nil {
		return 0.0, 0.0
	}

	var victimTick *types.PlayerTickData
	for tickOffset := int64(-5); tickOffset <= 5; tickOffset++ {
		if tick, exists := victimTickMap[closestDamage.TickTimestamp+tickOffset]; exists {
			victimTick = &tick
			break
		}
	}

	if victimTick == nil {
		return 0.0, 0.0
	}

	crosshairX, crosshairY := aus.calculateCrosshairPlacementWithLOS(&playerTick, victimTick)

	return crosshairX, crosshairY
}

func (aus *AimUtilityService) calculateCrosshairPlacementWithLOS(
	shooterTick, victimTick *types.PlayerTickData,
) (float64, float64) {

	shooterPos := Vector3{
		X: float32(shooterTick.PositionX),
		Y: float32(shooterTick.PositionY),
		Z: float32(shooterTick.PositionZ),
	}

	victimPos := Vector3{
		X: float32(victimTick.PositionX),
		Y: float32(victimTick.PositionY),
		Z: float32(victimTick.PositionZ),
	}

	idealDirection := Vector3{
		X: victimPos.X - shooterPos.X,
		Y: victimPos.Y - shooterPos.Y,
		Z: victimPos.Z - shooterPos.Z,
	}
	idealDirection = normalizeVector(idealDirection)

	actualAimDirection := Vector3{
		X: float32(shooterTick.AimX),
		Y: float32(shooterTick.AimY),
		Z: 0.0,
	}
	actualAimDirection = normalizeVector(actualAimDirection)

	angleDiff := calculateAngleBetweenVectors(idealDirection, actualAimDirection)

	angleDegrees := float64(angleDiff) * 180.0 / math.Pi

	crosshairX := angleDegrees * math.Cos(float64(shooterTick.AimX)*math.Pi/180.0)
	crosshairY := angleDegrees * math.Sin(float64(shooterTick.AimY)*math.Pi/180.0)

	crosshairX = math.Abs(crosshairX)
	crosshairY = math.Abs(crosshairY)

	return crosshairX, crosshairY
}

func (aus *AimUtilityService) calculateReactionTime(
	shot types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
) float64 {

	// Create damage lookup map for O(1) access
	damageByTick := make(map[int64][]types.DamageEvent)
	for _, damage := range damages {
		damageByTick[damage.TickTimestamp] = append(damageByTick[damage.TickTimestamp], damage)
	}

	var closestDamage *types.DamageEvent
	minTimeDiff := int64(10)

	// Check for damage within 10 tick window using hash map lookup
	for tickOffset := int64(-10); tickOffset <= 10; tickOffset++ {
		if damages, exists := damageByTick[shot.Tick+tickOffset]; exists {
			for i, damage := range damages {
				timeDiff := abs(shot.Tick - damage.TickTimestamp)
				if timeDiff <= minTimeDiff {
					minTimeDiff = timeDiff
					closestDamage = &damages[i]
				}
			}
		}
	}

	if closestDamage == nil {
		return 0.0
	}

	tickDiff := float64(abs(shot.Tick - closestDamage.TickTimestamp))
	reactionTimeMs := (tickDiff / 64.0) * 1000.0

	return reactionTimeMs
}

func normalizeVector(v Vector3) Vector3 {
	length := float32(math.Sqrt(float64(v.X*v.X + v.Y*v.Y + v.Z*v.Z)))
	if length == 0 {
		return v
	}
	return Vector3{
		X: v.X / length,
		Y: v.Y / length,
		Z: v.Z / length,
	}
}

func calculateAngleBetweenVectors(v1, v2 Vector3) float32 {
	dot := v1.X*v2.X + v1.Y*v2.Y + v1.Z*v2.Z
	if dot > 1.0 {
		dot = 1.0
	} else if dot < -1.0 {
		dot = -1.0
	}
	return float32(math.Acos(float64(dot)))
}

func (aus *AimUtilityService) findFirstAttackerLOSParallel(
	attackerTicks []types.PlayerTickData,
	victimTicks []types.PlayerTickData,
	damage types.DamageEvent,
) (int64, bool) {
	if len(attackerTicks) == 0 || len(victimTicks) == 0 {
		return 0, false
	}

	// For small datasets, use sequential processing
	if len(attackerTicks) < 50 {
		return aus.findFirstAttackerLOS(attackerTicks, victimTicks, damage)
	}

	victimTickMap := make(map[int64]types.PlayerTickData)
	for _, tick := range victimTicks {
		victimTickMap[tick.Tick] = tick
	}

	// Optimize worker count based on data size and CPU cores
	numWorkers := min(len(attackerTicks)/20, runtime.NumCPU())
	if numWorkers < 1 {
		numWorkers = 1
	}
	if numWorkers > MaxParallelLOSWorkers {
		numWorkers = MaxParallelLOSWorkers
	}

	// Use buffered channels to reduce blocking
	jobs := make(chan int, numWorkers*2)

	var wg sync.WaitGroup
	var mu sync.Mutex
	var firstLOSResult *LOSResult
	minTickIndex := len(attackerTicks)

	// Start workers
	for i := 0; i < numWorkers; i++ {
		wg.Add(1)
		go func(workerID int) {
			defer wg.Done()
			for tickIndex := range jobs {
				attackerTick := attackerTicks[tickIndex]

				if tickIndex%LOSCheckInterval != 0 {
					continue
				}

				if victimTick, exists := victimTickMap[attackerTick.Tick]; exists {
					hasLOS := aus.checkLineOfSight(&attackerTick, &victimTick)

					if hasLOS {
						mu.Lock()
						if tickIndex < minTickIndex {
							minTickIndex = tickIndex
							firstLOSResult = &LOSResult{
								TickIndex: tickIndex,
								Tick:      attackerTick.Tick,
								HasLOS:    hasLOS,
							}
						}
						mu.Unlock()
					}
				}
			}
		}(i)
	}

	// Send jobs
	go func() {
		for i := range attackerTicks {
			jobs <- i
		}
		close(jobs)
	}()

	// Wait for completion
	wg.Wait()

	if firstLOSResult != nil {
		return firstLOSResult.Tick, true
	}

	return 0, false
}

// findFirstAttackerLOS performs a sequential LOS search suitable for small datasets
func (aus *AimUtilityService) findFirstAttackerLOS(
	attackerTicks []types.PlayerTickData,
	victimTicks []types.PlayerTickData,
	damage types.DamageEvent,
) (int64, bool) {
	if len(attackerTicks) == 0 || len(victimTicks) == 0 {
		return 0, false
	}

	victimTickMap := make(map[int64]types.PlayerTickData)
	for _, tick := range victimTicks {
		victimTickMap[tick.Tick] = tick
	}

	for idx := 0; idx < len(attackerTicks); idx++ {
		if idx%LOSCheckInterval != 0 {
			continue
		}
		attackerTick := attackerTicks[idx]
		if victimTick, exists := victimTickMap[attackerTick.Tick]; exists {
			hasLOS := aus.checkLineOfSight(&attackerTick, &victimTick)
			if hasLOS {
				return attackerTick.Tick, true
			}
		}
	}

	return 0, false
}

// LOSResult represents the result of a line of sight check
type LOSResult struct {
	TickIndex int
	Tick      int64
	HasLOS    bool
}

type ShotAnalysisData struct {
	Shot         types.PlayerShootingData
	CrosshairX   float64
	CrosshairY   float64
	ReactionTime float64
	Hit          bool
	HitGroup     int
	SprayingShot bool
}

func (aus *AimUtilityService) analyzeShots(
	shots []types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
	playerID string,
	roundNumber int,
) []ShotAnalysisData {
	start := time.Now()
	if len(shots) == 0 {
		return []ShotAnalysisData{}
	}

	// Create damage lookup map for O(1) access
	damageByTick := make(map[int64][]types.DamageEvent)
	for _, damage := range damages {
		if damage.AttackerSteamID == playerID {
			damageByTick[damage.TickTimestamp] = append(damageByTick[damage.TickTimestamp], damage)
		}
	}

	// Pre-allocate slice with known capacity
	shotAnalyses := make([]ShotAnalysisData, 0, len(shots))

	for _, shot := range shots {
		crosshairStart := time.Now()
		analysis := ShotAnalysisData{
			Shot:         shot,
			SprayingShot: shot.IsSpraying,
		}

		shotHit := false
		var hitGroup int
		// Check for damage within 10 tick window using hash map lookup
		for tickOffset := int64(-10); tickOffset <= 10; tickOffset++ {
			if damages, exists := damageByTick[shot.Tick+tickOffset]; exists {
				for _, damage := range damages {
					shotHit = true
					hitGroup = damage.HitGroup
					break
				}
				if shotHit {
					break
				}
			}
		}

		analysis.Hit = shotHit
		analysis.HitGroup = hitGroup

		crosshairX, crosshairY := aus.calculateCrosshairPlacement(shot, damages, playerTickData)
		analysis.CrosshairX = crosshairX
		analysis.CrosshairY = crosshairY
		crosshairElapsed := time.Since(crosshairStart)

		reactionStart := time.Now()
		reactionTime := aus.calculateReactionTime(shot, damages, playerTickData)
		analysis.ReactionTime = reactionTime
		reactionElapsed := time.Since(reactionStart)

		if aus.logger != nil {
			aus.logger.WithFields(logrus.Fields{
				"label":        "crosshair_placement",
				"player_id":    playerID,
				"round_number": roundNumber,
				"start_time":   crosshairStart,
				"end_time":     crosshairStart.Add(crosshairElapsed),
				"duration_ms":  crosshairElapsed.Milliseconds(),
			}).Info("performance")

			aus.logger.WithFields(logrus.Fields{
				"label":        "reaction_time",
				"player_id":    playerID,
				"round_number": roundNumber,
				"start_time":   reactionStart,
				"end_time":     reactionStart.Add(reactionElapsed),
				"duration_ms":  reactionElapsed.Milliseconds(),
			}).Info("performance")
		}

		shotAnalyses = append(shotAnalyses, analysis)
	}

	if aus.logger != nil {
		totalElapsed := time.Since(start)
		aus.logger.WithFields(logrus.Fields{
			"label":        "analyze_shots_total",
			"player_id":    playerID,
			"round_number": roundNumber,
			"start_time":   start,
			"end_time":     start.Add(totalElapsed),
			"duration_ms":  totalElapsed.Milliseconds(),
		}).Info("performance")
	}

	return shotAnalyses
}

func (aus *AimUtilityService) calculatePlayerAimStatsFromAnalysis(
	playerID string,
	roundNumber int,
	shotAnalyses []ShotAnalysisData,
) types.AimAnalysisResult {

	result := types.AimAnalysisResult{
		PlayerSteamID: playerID,
		RoundNumber:   roundNumber,
		ShotsFired:    len(shotAnalyses),
	}

	shotsOnHit := 0
	sprayingShotsFired := 0
	sprayingShotsHit := 0

	var crosshairPlacementsX []float64
	var crosshairPlacementsY []float64
	var reactionTimes []float64

	headHits := 0
	upperChestHits := 0
	chestHits := 0
	legsHits := 0

	for _, analysis := range shotAnalyses {
		if analysis.Hit {
			shotsOnHit++

			switch analysis.HitGroup {
			case types.HitGroupHead:
				headHits++
			case types.HitGroupChest:
				chestHits++
			case types.HitGroupStomach:
				upperChestHits++
			case types.HitGroupLeftLeg, types.HitGroupRightLeg:
				legsHits++
			}
		}

		if analysis.SprayingShot {
			sprayingShotsFired++
			if analysis.Hit {
				sprayingShotsHit++
			}
		}

		crosshairPlacementsX = append(crosshairPlacementsX, analysis.CrosshairX)
		crosshairPlacementsY = append(crosshairPlacementsY, analysis.CrosshairY)
		if analysis.ReactionTime >= 50.0 {
			reactionTimes = append(reactionTimes, analysis.ReactionTime)
		}
	}

	result.ShotsHit = shotsOnHit
	if result.ShotsFired > 0 {
		result.AccuracyAllShots = float64(result.ShotsHit) / float64(result.ShotsFired) * 100.0
	}

	result.SprayingShotsFired = sprayingShotsFired
	result.SprayingShotsHit = sprayingShotsHit
	if sprayingShotsFired > 0 {
		result.SprayingAccuracy = float64(sprayingShotsHit) / float64(sprayingShotsFired) * 100.0
	}

	if len(crosshairPlacementsX) > 0 {
		result.AverageCrosshairPlacementX = aus.calculateAverage(crosshairPlacementsX)
		result.AverageCrosshairPlacementY = aus.calculateAverage(crosshairPlacementsY)
	}

	if result.ShotsHit > 0 {
		result.HeadshotAccuracy = float64(headHits) / float64(result.ShotsHit) * 100.0
	}

	if len(reactionTimes) > 0 {
		result.AverageTimeToDamage = aus.calculateAverage(reactionTimes)
	}

	result.HeadHitsTotal = headHits
	result.UpperChestHitsTotal = upperChestHits
	result.ChestHitsTotal = chestHits
	result.LegsHitsTotal = legsHits

	// Calculate overall aim rating (0-100)
	result.AimRating = aus.calculateAimRating(result)

	return result
}

func (aus *AimUtilityService) calculateWeaponAimStatsFromAnalysis(
	playerID string,
	roundNumber int,
	shotAnalyses []ShotAnalysisData,
) []types.WeaponAimAnalysisResult {

	weaponAnalyses := make(map[string][]ShotAnalysisData)
	for _, analysis := range shotAnalyses {
		weaponAnalyses[analysis.Shot.WeaponName] = append(weaponAnalyses[analysis.Shot.WeaponName], analysis)
	}

	var results []types.WeaponAimAnalysisResult

	for weaponName, weaponShotAnalyses := range weaponAnalyses {
		result := types.WeaponAimAnalysisResult{
			PlayerSteamID:     playerID,
			RoundNumber:       roundNumber,
			WeaponName:        weaponName,
			WeaponDisplayName: types.FormatWeaponName(weaponName),
			ShotsFired:        len(weaponShotAnalyses),
		}

		shotsOnHit := 0
		sprayingShotsFired := 0
		sprayingShotsHit := 0

		headHits := 0
		upperChestHits := 0
		chestHits := 0
		legsHits := 0

		var crosshairPlacementsX []float64
		var crosshairPlacementsY []float64

		for _, analysis := range weaponShotAnalyses {
			if analysis.Hit {
				shotsOnHit++

				// Categorize hit region
				switch analysis.HitGroup {
				case types.HitGroupHead:
					headHits++
				case types.HitGroupChest:
					chestHits++
				case types.HitGroupStomach:
					upperChestHits++
				case types.HitGroupLeftLeg, types.HitGroupRightLeg:
					legsHits++
				}
			}

			if analysis.SprayingShot {
				sprayingShotsFired++
				if analysis.Hit {
					sprayingShotsHit++
				}
			}

			crosshairPlacementsX = append(crosshairPlacementsX, analysis.CrosshairX)
			crosshairPlacementsY = append(crosshairPlacementsY, analysis.CrosshairY)
		}

		result.ShotsHit = shotsOnHit
		if result.ShotsFired > 0 {
			result.AccuracyAllShots = float64(result.ShotsHit) / float64(result.ShotsFired) * 100.0
		}

		result.SprayingShotsFired = sprayingShotsFired
		if sprayingShotsFired > 0 {
			result.SprayingShotsHit = sprayingShotsHit
			result.SprayingAccuracy = float64(sprayingShotsHit) / float64(sprayingShotsFired) * 100.0
		}

		if len(crosshairPlacementsX) > 0 {
			result.AverageCrosshairPlacementX = aus.calculateAverage(crosshairPlacementsX)
			result.AverageCrosshairPlacementY = aus.calculateAverage(crosshairPlacementsY)
		}

		if result.ShotsHit > 0 {
			result.HeadshotAccuracy = float64(headHits) / float64(result.ShotsHit) * 100.0
		}

		result.HeadHitsTotal = headHits
		result.UpperChestHitsTotal = upperChestHits
		result.ChestHitsTotal = chestHits
		result.LegsHitsTotal = legsHits

		results = append(results, result)
	}

	return results
}

func (p *ObjectPool) GetVector() *Vector3 {
	v := p.vectorPool.Get().(*Vector3)
	*v = Vector3{}
	return v
}

func (p *ObjectPool) PutVector(v *Vector3) {
	p.vectorPool.Put(v)
}

func (p *ObjectPool) GetTriangle() *Triangle {
	t := p.trianglePool.Get().(*Triangle)
	*t = Triangle{}
	return t
}

func (p *ObjectPool) PutTriangle(t *Triangle) {
	p.trianglePool.Put(t)
}

func (p *ObjectPool) GetLOSResult() *LOSResult {
	r := p.resultPool.Get().(*LOSResult)
	*r = LOSResult{}
	return r
}

func (p *ObjectPool) PutLOSResult(r *LOSResult) {
	p.resultPool.Put(r)
}

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}

// calculateAimRating calculates the overall aim rating (0-100) based on the specified weights
func (aus *AimUtilityService) calculateAimRating(result types.AimAnalysisResult) float64 {
	var totalScore float64

	// 1. Accuracy all shots (25 points)
	accuracyScore := aus.calculateLinearScore(
		result.AccuracyAllShots,
		AccuracyMaxValue, AccuracyMinValue,
		AccuracyMaxScore, AccuracyMinScore,
		true, // higher is better
	)
	totalScore += accuracyScore

	// 2. Crosshair placement (25 points) - using average of X and Y
	crosshairAvg := (result.AverageCrosshairPlacementX + result.AverageCrosshairPlacementY) / 2.0
	crosshairScore := aus.calculateLinearScore(
		crosshairAvg,
		CrosshairMaxValue, CrosshairMinValue,
		CrosshairMaxScore, CrosshairMinScore,
		false, // lower is better
	)
	totalScore += crosshairScore

	// 3. Time to damage (20 points)
	timeToDamageScore := aus.calculateLinearScore(
		result.AverageTimeToDamage,
		TimeToDamageMaxValue, TimeToDamageMinValue,
		TimeToDamageMaxScore, TimeToDamageMinScore,
		false, // lower is better
	)
	totalScore += timeToDamageScore

	// 4. Headshot accuracy (15 points)
	headshotScore := aus.calculateLinearScore(
		result.HeadshotAccuracy,
		HeadshotMaxValue, HeadshotMinValue,
		HeadshotMaxScore, HeadshotMinScore,
		true, // higher is better
	)
	totalScore += headshotScore

	// 5. Spray accuracy (15 points)
	sprayScore := aus.calculateLinearScore(
		result.SprayingAccuracy,
		SprayMaxValue, SprayMinValue,
		SprayMaxScore, SprayMinScore,
		true, // higher is better
	)
	totalScore += sprayScore

	// Ensure the result is between 0 and 100
	if totalScore > 100.0 {
		return 100.0
	}
	if totalScore < 0.0 {
		return 0.0
	}

	return totalScore
}

// calculateLinearScore calculates a linear score between minScore and maxScore based on value
// higherIsBetter determines if higher values are better (true) or lower values are better (false)
func (aus *AimUtilityService) calculateLinearScore(value, maxValue, minValue, maxScore, minScore float64, higherIsBetter bool) float64 {
	// Handle edge cases
	if maxValue == minValue {
		return minScore
	}

	var normalizedValue float64
	var score float64

	if higherIsBetter {
		// For higher-is-better metrics (accuracy, headshot accuracy, spray accuracy)
		if value >= maxValue {
			score = maxScore
		} else if value <= minValue {
			score = minScore
		} else {
			// Linear interpolation: (value - minValue) / (maxValue - minValue)
			normalizedValue = (value - minValue) / (maxValue - minValue)
			score = minScore + normalizedValue*(maxScore-minScore)
		}
	} else {
		// For lower-is-better metrics (crosshair placement, time to damage)
		if value <= maxValue {
			score = maxScore
		} else if value >= minValue {
			score = minScore
		} else {
			// Linear interpolation: (minValue - value) / (minValue - maxValue)
			normalizedValue = (minValue - value) / (minValue - maxValue)
			score = minScore + normalizedValue*(maxScore-minScore)
		}
	}

	return score
}
