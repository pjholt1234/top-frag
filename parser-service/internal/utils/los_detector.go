package utils

import (
	"encoding/binary"
	"fmt"
	"io"
	"math"
	"os"
	"path/filepath"
)

// Vector3 represents a 3D vector
type Vector3 struct {
	X, Y, Z float32
}

// Triangle represents a triangle with three vertices
type Triangle struct {
	V0, V1, V2 Vector3
}

// Ray represents a ray with origin and direction
type Ray struct {
	Origin    Vector3
	Direction Vector3
}

// Box represents the dimensions of an object
type Box struct {
	Width  float32 // X dimension
	Height float32 // Y dimension
	Depth  float32 // Z dimension
}

// LOSDetector handles line of sight detection with triangle mesh data
type LOSDetector struct {
	triangles []Triangle
	mapName   string
}

// NewLOSDetector creates a new line of sight detector for the specified map
func NewLOSDetector(mapName string) (*LOSDetector, error) {
	// Construct path to the .tri file
	// Try relative path first, then absolute path
	triFilePath := filepath.Join("map-files", mapName+".tri")

	// If relative path doesn't work, try from the parser-service directory
	if _, err := os.Stat(triFilePath); os.IsNotExist(err) {
		// Try to find the file in the parser-service directory
		wd, _ := os.Getwd()
		for {
			testPath := filepath.Join(wd, "map-files", mapName+".tri")
			if _, err := os.Stat(testPath); err == nil {
				triFilePath = testPath
				break
			}
			parent := filepath.Dir(wd)
			if parent == wd {
				break // Reached root directory
			}
			wd = parent
		}
	}

	// Load triangles from the .tri file
	triangles, err := loadTriangles(triFilePath)
	if err != nil {
		return nil, fmt.Errorf("failed to load triangles for map %s: %v", mapName, err)
	}

	return &LOSDetector{
		triangles: triangles,
		mapName:   mapName,
	}, nil
}

// loadTriangles loads triangles from a binary .tri file
func loadTriangles(filename string) ([]Triangle, error) {
	file, err := os.Open(filename)
	if err != nil {
		return nil, err
	}
	defer file.Close()

	var triangles []Triangle
	buffer := make([]byte, 36) // 3 vertices * 3 floats * 4 bytes = 36 bytes per triangle

	for {
		n, err := file.Read(buffer)
		if err == io.EOF {
			break
		}
		if err != nil {
			return nil, err
		}
		if n != 36 {
			// Skip incomplete triangles
			continue
		}

		// Parse binary data (assuming little-endian float32 format)
		triangle := Triangle{
			V0: Vector3{
				X: math.Float32frombits(binary.LittleEndian.Uint32(buffer[0:4])),
				Y: math.Float32frombits(binary.LittleEndian.Uint32(buffer[4:8])),
				Z: math.Float32frombits(binary.LittleEndian.Uint32(buffer[8:12])),
			},
			V1: Vector3{
				X: math.Float32frombits(binary.LittleEndian.Uint32(buffer[12:16])),
				Y: math.Float32frombits(binary.LittleEndian.Uint32(buffer[16:20])),
				Z: math.Float32frombits(binary.LittleEndian.Uint32(buffer[20:24])),
			},
			V2: Vector3{
				X: math.Float32frombits(binary.LittleEndian.Uint32(buffer[24:28])),
				Y: math.Float32frombits(binary.LittleEndian.Uint32(buffer[28:32])),
				Z: math.Float32frombits(binary.LittleEndian.Uint32(buffer[32:36])),
			},
		}

		// Validate triangle (check for NaN or infinite values)
		if isValidTriangle(triangle) {
			triangles = append(triangles, triangle)
		}
	}

	return triangles, nil
}

// isValidTriangle checks if a triangle has valid coordinates
func isValidTriangle(t Triangle) bool {
	vertices := []Vector3{t.V0, t.V1, t.V2}
	for _, v := range vertices {
		if math.IsNaN(float64(v.X)) || math.IsNaN(float64(v.Y)) || math.IsNaN(float64(v.Z)) ||
			math.IsInf(float64(v.X), 0) || math.IsInf(float64(v.Y), 0) || math.IsInf(float64(v.Z), 0) {
			return false
		}
	}
	return true
}

// crossProduct calculates the cross product of two vectors
func crossProduct(a, b Vector3) Vector3 {
	return Vector3{
		X: a.Y*b.Z - a.Z*b.Y,
		Y: a.Z*b.X - a.X*b.Z,
		Z: a.X*b.Y - a.Y*b.X,
	}
}

// dotProduct calculates the dot product of two vectors
func dotProduct(a, b Vector3) float32 {
	return a.X*b.X + a.Y*b.Y + a.Z*b.Z
}

// normalize normalizes a vector
func normalize(v Vector3) Vector3 {
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

// angleBetweenVectors calculates the angle between two vectors in degrees
func angleBetweenVectors(a, b Vector3) float64 {
	dot := dotProduct(a, b)
	// Clamp dot product to avoid numerical errors
	if dot > 1.0 {
		dot = 1.0
	} else if dot < -1.0 {
		dot = -1.0
	}
	return math.Acos(float64(dot)) * 180.0 / math.Pi
}

// isWithinFOV checks if a target is within the player's field of view
// aimX and aimY are the horizontal and vertical aim angles in degrees
// fov is the field of view angle in degrees (default 90 for CS2)
func isWithinFOV(playerPos, targetPos Vector3, aimX, aimY, fov float64) bool {
	// Calculate direction from player to target
	direction := Vector3{
		X: targetPos.X - playerPos.X,
		Y: targetPos.Y - playerPos.Y,
		Z: targetPos.Z - playerPos.Z,
	}
	direction = normalize(direction)

	// Convert aim angles to direction vector
	// Based on your test results: aimX=pitch, aimY=yaw
	// Try different rotation order: pitch first, then yaw
	// Convert from degrees to radians
	pitch := aimX * math.Pi / 180.0
	yaw := aimY * math.Pi / 180.0

	// CS2 coordinate system: X=Forward, Y=Left, Z=Up
	// Apply pitch first, then yaw (different rotation order)
	aimDirection := Vector3{
		X: float32(math.Cos(yaw) * math.Cos(pitch)), // Forward
		Y: float32(math.Sin(yaw) * math.Cos(pitch)), // Left
		Z: float32(math.Sin(pitch)),                 // Up
	}

	// Calculate angle between aim direction and target direction
	angle := angleBetweenVectors(aimDirection, direction)

	// Check if target is within FOV cone
	// FOV is 106 degrees total, so we use the full FOV as the angle from center to edge
	return angle <= fov
}

// BarycentricRayTriangleIntersection - working algorithm
func (d *LOSDetector) BarycentricRayTriangleIntersection(ray Ray, triangle Triangle) (bool, float32) {
	// Calculate triangle edges
	edge1 := Vector3{
		X: triangle.V1.X - triangle.V0.X,
		Y: triangle.V1.Y - triangle.V0.Y,
		Z: triangle.V1.Z - triangle.V0.Z,
	}
	edge2 := Vector3{
		X: triangle.V2.X - triangle.V0.X,
		Y: triangle.V2.Y - triangle.V0.Y,
		Z: triangle.V2.Z - triangle.V0.Z,
	}

	// Calculate triangle normal
	normal := crossProduct(edge1, edge2)
	normal = normalize(normal)

	// Calculate denominator (ray direction dot normal)
	denom := dotProduct(ray.Direction, normal)

	// If denominator is close to zero, ray is parallel to triangle
	if math.Abs(float64(denom)) < 1e-6 {
		return false, 0
	}

	// Calculate vector from ray origin to triangle vertex
	vecToTriangle := Vector3{
		X: triangle.V0.X - ray.Origin.X,
		Y: triangle.V0.Y - ray.Origin.Y,
		Z: triangle.V0.Z - ray.Origin.Z,
	}

	// Calculate t (distance along ray to intersection point)
	t := dotProduct(vecToTriangle, normal) / denom

	// If t is negative, intersection is behind ray origin
	if t < 0 {
		return false, 0
	}

	// Calculate intersection point
	intersectionPoint := Vector3{
		X: ray.Origin.X + ray.Direction.X*t,
		Y: ray.Origin.Y + ray.Direction.Y*t,
		Z: ray.Origin.Z + ray.Direction.Z*t,
	}

	// Check if intersection point is inside triangle using barycentric coordinates
	v0p := Vector3{
		X: intersectionPoint.X - triangle.V0.X,
		Y: intersectionPoint.Y - triangle.V0.Y,
		Z: intersectionPoint.Z - triangle.V0.Z,
	}

	// Calculate barycentric coordinates
	dot00 := dotProduct(edge2, edge2)
	dot01 := dotProduct(edge2, edge1)
	dot02 := dotProduct(edge2, v0p)
	dot11 := dotProduct(edge1, edge1)
	dot12 := dotProduct(edge1, v0p)

	// Calculate barycentric coordinates
	invDenom := 1.0 / (dot00*dot11 - dot01*dot01)
	u := (dot11*dot02 - dot01*dot12) * invDenom
	v_coord := (dot00*dot12 - dot01*dot02) * invDenom

	// Check if point is inside triangle
	return (u >= 0) && (v_coord >= 0) && (u+v_coord <= 1), t
}

// CheckLineOfSight checks if there's a clear line of sight between two points
func (d *LOSDetector) CheckLineOfSight(pos1, pos2 Vector3) (bool, []Triangle) {
	// Create ray from pos1 to pos2
	direction := Vector3{
		X: pos2.X - pos1.X,
		Y: pos2.Y - pos1.Y,
		Z: pos2.Z - pos1.Z,
	}
	direction = normalize(direction)

	ray := Ray{
		Origin:    pos1,
		Direction: direction,
	}

	var blockingTriangles []Triangle
	hasLOS := true

	// Calculate distance between points
	distance := float32(math.Sqrt(float64((pos2.X-pos1.X)*(pos2.X-pos1.X) +
		(pos2.Y-pos1.Y)*(pos2.Y-pos1.Y) +
		(pos2.Z-pos1.Z)*(pos2.Z-pos1.Z))))

	// Check intersection with all triangles
	for _, triangle := range d.triangles {
		intersects, t := d.BarycentricRayTriangleIntersection(ray, triangle)
		if intersects && t > 0.001 { // Small epsilon to avoid self-intersection
			// Check if intersection point is between the two positions
			intersectionPoint := Vector3{
				X: ray.Origin.X + ray.Direction.X*t,
				Y: ray.Origin.Y + ray.Direction.Y*t,
				Z: ray.Origin.Z + ray.Direction.Z*t,
			}

			// Calculate distance from pos1 to intersection point
			distToIntersection := float32(math.Sqrt(
				float64((intersectionPoint.X-pos1.X)*(intersectionPoint.X-pos1.X) +
					(intersectionPoint.Y-pos1.Y)*(intersectionPoint.Y-pos1.Y) +
					(intersectionPoint.Z-pos1.Z)*(intersectionPoint.Z-pos1.Z))))

			// If intersection is closer than target, line of sight is blocked
			if distToIntersection < distance-0.1 { // Small epsilon for floating point precision
				blockingTriangles = append(blockingTriangles, triangle)
				hasLOS = false
			}
		}
	}

	return hasLOS, blockingTriangles
}

// CheckLineOfSightWithBox checks if there's a clear line of sight between two objects with given dimensions
// pos1 and pos2 are the center-bottom positions of the objects
// box1 and box2 are the dimensions of the objects
func (d *LOSDetector) CheckLineOfSightWithBox(pos1, pos2 Vector3, box1, box2 Box) (bool, []Triangle) {
	// Generate sample points for both objects
	points1 := d.generateBoxPoints(pos1, box1)
	points2 := d.generateBoxPoints(pos2, box2)

	// Check if any point from object1 can see any point from object2
	var lastBlockingTriangles []Triangle
	for _, p1 := range points1 {
		for _, p2 := range points2 {
			hasLOS, blockingTriangles := d.CheckLineOfSight(p1, p2)
			if hasLOS {
				return true, nil
			}
			// Keep track of blocking triangles from the last check
			lastBlockingTriangles = blockingTriangles
		}
	}

	// If no point-to-point visibility found, return the blocking triangles from the last check
	return false, lastBlockingTriangles
}

// generateBoxPoints generates sample points around a box for visibility testing
func (d *LOSDetector) generateBoxPoints(center Vector3, box Box) []Vector3 {
	// The center position is the x-z center and lowest y point
	// So we need to offset the points around this center

	// Generate points at key locations of the box
	points := []Vector3{
		// Bottom corners
		{center.X - box.Width/2, center.Y, center.Z - box.Depth/2},
		{center.X + box.Width/2, center.Y, center.Z - box.Depth/2},
		{center.X - box.Width/2, center.Y, center.Z + box.Depth/2},
		{center.X + box.Width/2, center.Y, center.Z + box.Depth/2},

		// Top corners
		{center.X - box.Width/2, center.Y + box.Height, center.Z - box.Depth/2},
		{center.X + box.Width/2, center.Y + box.Height, center.Z - box.Depth/2},
		{center.X - box.Width/2, center.Y + box.Height, center.Z + box.Depth/2},
		{center.X + box.Width/2, center.Y + box.Height, center.Z + box.Depth/2},

		// Center points at different heights
		{center.X, center.Y + box.Height/4, center.Z},
		{center.X, center.Y + box.Height/2, center.Z},
		{center.X, center.Y + 3*box.Height/4, center.Z},
	}

	return points
}

// CheckLineOfSightWithFOV checks if there's a clear line of sight between two points considering FOV
func (d *LOSDetector) CheckLineOfSightWithFOV(pos1, pos2 Vector3, aimX1, aimY1, aimX2, aimY2 float64) (bool, bool, []Triangle) {
	// First check if both players can see each other within their FOV
	// Try 60 degrees FOV
	player1CanSee := isWithinFOV(pos1, pos2, aimX1, aimY1, 60.0)
	player2CanSee := isWithinFOV(pos2, pos1, aimX2, aimY2, 60.0)

	// Check geometric line of sight
	hasLOS, blockingTriangles := d.CheckLineOfSight(pos1, pos2)

	// Both players must be able to see each other AND have clear geometric LOS
	player1LOS := hasLOS && player1CanSee
	player2LOS := hasLOS && player2CanSee

	return player1LOS, player2LOS, blockingTriangles
}

// CheckLineOfSightWithFOVAndBox checks if there's a clear line of sight between two objects considering FOV and box dimensions
func (d *LOSDetector) CheckLineOfSightWithFOVAndBox(pos1, pos2 Vector3, box1, box2 Box, aimX1, aimY1, aimX2, aimY2 float64) (bool, bool, []Triangle) {
	// First check if both players can see each other within their FOV
	// Try 60 degrees FOV
	player1CanSee := isWithinFOV(pos1, pos2, aimX1, aimY1, 60.0)
	player2CanSee := isWithinFOV(pos2, pos1, aimX2, aimY2, 60.0)

	// Check geometric line of sight with box dimensions
	hasLOS, blockingTriangles := d.CheckLineOfSightWithBox(pos1, pos2, box1, box2)

	// Both players must be able to see each other AND have clear geometric LOS
	player1LOS := hasLOS && player1CanSee
	player2LOS := hasLOS && player2CanSee

	return player1LOS, player2LOS, blockingTriangles
}

// CheckLineOfSightFromPosition is the main public method that determines line of sight
// from a given position to an object, considering camera angles and object dimensions
func (d *LOSDetector) CheckLineOfSightFromPosition(
	playerPos Vector3, // Player position (x, y, z)
	cameraAngleX float64, // Camera angle X (pitch)
	cameraAngleY float64, // Camera angle Y (yaw)
	objectPos Vector3, // Object position (x, y, z)
	objectSize Vector3, // Object size (width, height, depth) - defaults to player size (32, 72, 32)
) (bool, []Triangle) {
	// Convert object size to Box struct
	objectBox := Box{
		Width:  objectSize.X,
		Height: objectSize.Y,
		Depth:  objectSize.Z,
	}

	// Default player box dimensions (72 units tall, 32 units wide, 32 units deep)
	playerBox := Box{
		Width:  32, // X dimension
		Height: 72, // Y dimension
		Depth:  32, // Z dimension
	}

	// Use FOV-aware LOS checking with box dimensions
	// For this method, we assume the object is not aiming, so we use neutral angles
	playerLOS, _, blockingTriangles := d.CheckLineOfSightWithFOVAndBox(
		playerPos, objectPos, playerBox, objectBox,
		cameraAngleX, cameraAngleY,
		0.0, 0.0, // Object is not aiming
	)

	return playerLOS, blockingTriangles
}

// GetTriangleCount returns the number of triangles loaded for this map
func (d *LOSDetector) GetTriangleCount() int {
	return len(d.triangles)
}

// GetMapName returns the name of the map this detector is configured for
func (d *LOSDetector) GetMapName() string {
	return d.mapName
}
