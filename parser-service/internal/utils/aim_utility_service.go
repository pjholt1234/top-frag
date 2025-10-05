package utils

import (
	"math"
	"parser-service/internal/types"
	"runtime"
	"sort"
	"strings"
	"sync"
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
}

const (
	EngagementGapSeconds     = 5
	ReactionTimeSearchWindow = 128
	MaxParallelLOSWorkers    = 9
	LOSCheckInterval         = 4
	MovementThreshold        = 5.0
)

func NewAimUtilityService(mapName string) (*AimUtilityService, error) {
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
	}, nil
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
		if playerID != "76561198081165057" {
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

	// Sort damage events by timestamp
	sortedDamageEvents := make([]types.DamageEvent, len(damageEvents))
	copy(sortedDamageEvents, damageEvents)

	// Simple bubble sort by tick timestamp
	for i := 0; i < len(sortedDamageEvents)-1; i++ {
		for j := 0; j < len(sortedDamageEvents)-i-1; j++ {
			if sortedDamageEvents[j].TickTimestamp > sortedDamageEvents[j+1].TickTimestamp {
				sortedDamageEvents[j], sortedDamageEvents[j+1] = sortedDamageEvents[j+1], sortedDamageEvents[j]
			}
		}
	}

	// Find first shots using time gap approach
	firstShots := aus.identifyFirstShots(sortedDamageEvents)

	// Calculate reaction times for first shots
	for _, damage := range firstShots {
		if damage.AttackerSteamID != "76561198081165057" {
			continue
		}
		reactionTime := aus.calculateReactionTimeForFirstShot(damage, playerTickData)
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

// findFirstAttackerLOS finds the OLDEST (first) occurrence where attacker has LOS on victim
// We only care if attacker can see victim, not vice versa
func (aus *AimUtilityService) findFirstAttackerLOS(
	attackerTicks []types.PlayerTickData,
	victimTicks []types.PlayerTickData,
	damage types.DamageEvent,
) (int64, bool) {
	// Create a map for quick victim tick lookup
	victimTickMap := make(map[int64]types.PlayerTickData)
	for _, tick := range victimTicks {
		victimTickMap[tick.Tick] = tick
	}

	// Sort attacker ticks by tick number (oldest first) - O(n log n) instead of O(n²)
	sort.Slice(attackerTicks, func(i, j int) bool {
		return attackerTicks[i].Tick < attackerTicks[j].Tick
	})

	// Find the OLDEST (first) occurrence where attacker has LOS on victim
	for _, attackerTick := range attackerTicks {
		if victimTick, exists := victimTickMap[attackerTick.Tick]; exists {
			// Check if attacker has LOS to victim at this tick
			hasLOS := aus.checkLineOfSight(&attackerTick, &victimTick)

			if hasLOS {
				return attackerTick.Tick, true
			}
		}
	}

	return 0, false
}

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

func (aus *AimUtilityService) checkLineOfSight(attacker, victim *types.PlayerTickData) bool {
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

func (aus *AimUtilityService) calculatePlayerAimStats(
	playerID string,
	roundNumber int,
	shots []types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
) types.AimAnalysisResult {

	result := types.AimAnalysisResult{
		PlayerSteamID: playerID,
		RoundNumber:   roundNumber,
		ShotsFired:    len(shots),
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

	for _, shot := range shots {
		shotHit := false
		for _, damage := range damages {
			if damage.AttackerSteamID == shot.PlayerID &&
				abs(damage.TickTimestamp-shot.Tick) <= 10 {
				shotHit = true
				shotsOnHit++

				switch damage.HitGroup {
				case types.HitGroupHead:
					headHits++
				case types.HitGroupChest:
					chestHits++
				case types.HitGroupStomach:
					upperChestHits++
				case types.HitGroupLeftLeg, types.HitGroupRightLeg:
					legsHits++
				}
				break
			}
		}

		if shot.IsSpraying {
			sprayingShotsFired++
			if shotHit {
				sprayingShotsHit++
			}
		}

		crosshairX, crosshairY := aus.calculateCrosshairPlacement(shot, damages, playerTickData)
		crosshairPlacementsX = append(crosshairPlacementsX, crosshairX)
		crosshairPlacementsY = append(crosshairPlacementsY, crosshairY)

		reactionTime := aus.calculateReactionTime(shot, damages, playerTickData)
		reactionTimes = append(reactionTimes, reactionTime)
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

	return result
}

func (aus *AimUtilityService) calculateWeaponAimStats(
	playerID string,
	roundNumber int,
	shots []types.PlayerShootingData,
	damages []types.DamageEvent,
	playerTickData []types.PlayerTickData,
) []types.WeaponAimAnalysisResult {

	weaponShots := make(map[string][]types.PlayerShootingData)
	for _, shot := range shots {
		weaponShots[shot.WeaponName] = append(weaponShots[shot.WeaponName], shot)
	}

	var results []types.WeaponAimAnalysisResult

	for weaponName, weaponShotData := range weaponShots {
		result := types.WeaponAimAnalysisResult{
			PlayerSteamID:     playerID,
			RoundNumber:       roundNumber,
			WeaponName:        weaponName,
			WeaponDisplayName: types.FormatWeaponName(weaponName),
			ShotsFired:        len(weaponShotData),
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

		for _, shot := range weaponShotData {
			shotHit := false
			for _, damage := range damages {
				if damage.AttackerSteamID == shot.PlayerID &&
					abs(damage.TickTimestamp-shot.Tick) <= 10 {
					shotHit = true
					shotsOnHit++

					switch damage.HitGroup {
					case types.HitGroupHead:
						headHits++
					case types.HitGroupChest:
						chestHits++
					case types.HitGroupStomach:
						upperChestHits++
					case types.HitGroupLeftLeg, types.HitGroupRightLeg:
						legsHits++
					}
					break
				}
			}

			if shot.IsSpraying {
				sprayingShotsFired++
				if shotHit {
					sprayingShotsHit++
				}
			}

			crosshairX, crosshairY := aus.calculateCrosshairPlacement(shot, damages, playerTickData)
			crosshairPlacementsX = append(crosshairPlacementsX, crosshairX)
			crosshairPlacementsY = append(crosshairPlacementsY, crosshairY)
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

	var playerTick *types.PlayerTickData
	for _, tick := range playerTickData {
		if tick.PlayerID == shot.PlayerID && tick.Tick == shot.Tick {
			playerTick = &tick
			break
		}
	}

	if playerTick == nil {
		return 0.0, 0.0
	}

	var closestDamage *types.DamageEvent
	minTimeDiff := int64(10)

	for i, damage := range damages {
		timeDiff := abs(shot.Tick - damage.TickTimestamp)
		if timeDiff <= minTimeDiff {
			minTimeDiff = timeDiff
			closestDamage = &damages[i]
		}
	}

	if closestDamage == nil {
		return 0.0, 0.0
	}

	var victimTick *types.PlayerTickData
	for _, tick := range playerTickData {
		if tick.PlayerID == closestDamage.VictimSteamID &&
			abs(tick.Tick-closestDamage.TickTimestamp) <= 5 {
			victimTick = &tick
			break
		}
	}

	if victimTick == nil {
		return 0.0, 0.0
	}

	crosshairX, crosshairY := aus.calculateCrosshairPlacementWithLOS(playerTick, victimTick)

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

	var closestDamage *types.DamageEvent
	minTimeDiff := int64(10)

	for i, damage := range damages {
		timeDiff := abs(shot.Tick - damage.TickTimestamp)
		if timeDiff <= minTimeDiff {
			minTimeDiff = timeDiff
			closestDamage = &damages[i]
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

	victimTickMap := make(map[int64]types.PlayerTickData)
	for _, tick := range victimTicks {
		victimTickMap[tick.Tick] = tick
	}

	jobs := make(chan int, len(attackerTicks))
	results := make(chan LOSResult, len(attackerTicks))

	numWorkers := min(len(attackerTicks)/10, runtime.NumCPU())
	if numWorkers < 1 {
		numWorkers = 1
	}
	if numWorkers > MaxParallelLOSWorkers {
		numWorkers = MaxParallelLOSWorkers
	}

	var wg sync.WaitGroup

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

					results <- LOSResult{
						TickIndex: tickIndex,
						Tick:      attackerTick.Tick,
						HasLOS:    hasLOS,
					}
				}
			}
		}(i)
	}

	go func() {
		for i := range attackerTicks {
			jobs <- i
		}
		close(jobs)
	}()

	go func() {
		wg.Wait()
		close(results)
	}()

	var firstLOSResult *LOSResult
	minTickIndex := len(attackerTicks)

	for result := range results {
		if result.HasLOS && result.TickIndex < minTickIndex {
			minTickIndex = result.TickIndex
			firstLOSResult = &result
		}
	}

	if firstLOSResult != nil {
		return firstLOSResult.Tick, true
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
	var shotAnalyses []ShotAnalysisData

	for _, shot := range shots {
		analysis := ShotAnalysisData{
			Shot:         shot,
			SprayingShot: shot.IsSpraying,
		}

		shotHit := false
		var hitGroup int
		for _, damage := range damages {
			if damage.AttackerSteamID == shot.PlayerID &&
				abs(damage.TickTimestamp-shot.Tick) <= 10 {
				shotHit = true
				hitGroup = damage.HitGroup
				break
			}
		}

		analysis.Hit = shotHit
		analysis.HitGroup = hitGroup

		crosshairX, crosshairY := aus.calculateCrosshairPlacement(shot, damages, playerTickData)
		analysis.CrosshairX = crosshairX
		analysis.CrosshairY = crosshairY

		reactionTime := aus.calculateReactionTime(shot, damages, playerTickData)
		analysis.ReactionTime = reactionTime

		shotAnalyses = append(shotAnalyses, analysis)
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

func (aus *AimUtilityService) shouldCheckLOS(prevTick, currentTick types.PlayerTickData) bool {
	movement := math.Sqrt(
		math.Pow(currentTick.PositionX-prevTick.PositionX, 2) +
			math.Pow(currentTick.PositionY-prevTick.PositionY, 2) +
			math.Pow(currentTick.PositionZ-prevTick.PositionZ, 2))

	return movement > MovementThreshold
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
