# Line of Sight Detection System

This document explains how the Top Frag system calculates line of sight (LOS) detection for CS2 demo analysis, including the technical implementation and algorithms used.

## Table of Contents

1. [Overview](#overview)
2. [Map Data Processing](#map-data-processing)
3. [Triangle Mesh System](#triangle-mesh-system)
4. [Ray-Triangle Intersection](#ray-triangle-intersection)
5. [Field of View Detection](#field-of-view-detection)
6. [Box-Based Visibility](#box-based-visibility)
7. [Implementation Details](#implementation-details)
8. [Performance Considerations](#performance-considerations)

---

## Overview

The Line of Sight (LOS) detection system determines whether players can see objects by checking for geometric obstructions between their positions. This is essential for:

- **Grenade Effectiveness**: Determining if smokes block enemy line of sight
- **Position + Aim Analysis**: Evaluating player positioning and sightlines

### Key Components

- **Triangle Mesh**: 3D map geometry loaded from `.tri` files
- **Ray Casting**: Mathematical ray-triangle intersection algorithms
- **Field of View**: Player camera angle and FOV cone calculations
- **Box Collision**: Player and object bounding box visibility testing

---

## Map Data Processing

### Triangle File Format

The system uses binary `.tri` files containing map geometry:

```
File Format: Binary
Structure: 3 vertices per triangle × 3 coordinates × 4 bytes (float32)
Total Size: 36 bytes per triangle
Endianness: Little-endian
```

### Data Loading Process

1. **File Discovery**: Automatically locates `.tri` files in `map-files/` directory
2. **Binary Parsing**: Reads 36-byte chunks representing individual triangles
3. **Validation**: Filters out invalid triangles (NaN, infinite values)
4. **Memory Storage**: Loads all triangles into memory for fast access

### Supported Maps

- Ancient
- Anubis
- Inferno
- Overpass
- Dust2
- Vertigo
- Nuke
- Mirage 
- Train

---

## Triangle Mesh System

### Triangle Structure

```go
type Triangle struct {
    V0, V1, V2 Vector3  // Three vertices
}

type Vector3 struct {
    X, Y, Z float32     // 3D coordinates
}
```

### Coordinate System

- **X Axis**: Forward/Backward movement
- **Y Axis**: Left/Right movement  
- **Z Axis**: Up/Down movement
- **Units**: Source engine units (1 unit ≈ 1 inch)

### Triangle Validation

```go
func isValidTriangle(t Triangle) bool {
    // Check for NaN or infinite values
    for _, v := range []Vector3{t.V0, t.V1, t.V2} {
        if math.IsNaN(float64(v.X)) || math.IsInf(float64(v.X), 0) {
            return false
        }
        // Similar checks for Y and Z
    }
    return true
}
```

---

## Ray-Triangle Intersection

### Barycentric Coordinate Method

The system uses the Moller-Trumbore algorithm for efficient ray-triangle intersection:

```go
func (d *LOSDetector) BarycentricRayTriangleIntersection(ray Ray, triangle Triangle) (bool, float32) {
    // Calculate triangle edges
    edge1 := triangle.V1 - triangle.V0
    edge2 := triangle.V2 - triangle.V0
    
    // Calculate triangle normal
    normal := crossProduct(edge1, edge2)
    normal = normalize(normal)
    
    // Ray direction dot normal
    denom := dotProduct(ray.Direction, normal)
    
    // Check for parallel ray
    if math.Abs(float64(denom)) < 1e-6 {
        return false, 0
    }
    
    // Calculate intersection distance
    vecToTriangle := triangle.V0 - ray.Origin
    t := dotProduct(vecToTriangle, normal) / denom
    
    // Check if intersection is in front of ray
    if t < 0 {
        return false, 0
    }
    
    // Calculate barycentric coordinates
    intersectionPoint := ray.Origin + ray.Direction * t
    // ... barycentric coordinate calculation
    
    // Check if point is inside triangle
    return (u >= 0) && (v >= 0) && (u + v <= 1), t
}
```

### Algorithm Steps

1. **Edge Calculation**: Compute triangle edge vectors
2. **Normal Calculation**: Calculate triangle surface normal
3. **Ray Intersection**: Find intersection point along ray
4. **Barycentric Test**: Verify intersection is inside triangle
5. **Distance Check**: Ensure intersection is in front of ray origin

---

## Field of View Detection

### FOV Cone Calculation

The system determines if targets are within a player's field of view:

```go
func isWithinFOV(playerPos, targetPos Vector3, aimX, aimY, fov float64) bool {
    // Calculate direction from player to target
    direction := normalize(targetPos - playerPos)
    
    // Convert aim angles to direction vector
    pitch := aimX * math.Pi / 180.0  // Vertical angle
    yaw := aimY * math.Pi / 180.0    // Horizontal angle
    
    // CS2 coordinate system rotation
    aimDirection := Vector3{
        X: float32(math.Cos(yaw) * math.Cos(pitch)),  // Forward
        Y: float32(math.Sin(yaw) * math.Cos(pitch)),  // Left
        Z: float32(math.Sin(pitch)),                  // Up
    }
    
    // Calculate angle between directions
    angle := angleBetweenVectors(aimDirection, direction)
    
    // Check if within FOV cone
    return angle <= fov
}
```

### FOV Parameters

- **Default FOV**: 60 degrees (configurable)
- **CS2 Standard**: 106 degrees total FOV
- **Angle Calculation**: Uses dot product and arccos
- **Coordinate System**: CS2-specific rotation order

---

## Box-Based Visibility

### Player Bounding Box

```go
type Box struct {
    Width  float32  // X dimension (32 units)
    Height float32  // Y dimension (72 units) 
    Depth  float32  // Z dimension (32 units)
}
```

### Box Point Generation

The system generates sample points around player bounding boxes:

```go
func (d *LOSDetector) generateBoxPoints(center Vector3, box Box) []Vector3 {
    return []Vector3{
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
}
```

### Visibility Testing

1. **Point Generation**: Create sample points around both objects
2. **Pair Testing**: Check visibility between all point pairs
3. **Success Criteria**: Any point-to-point visibility = object visibility
4. **Blocking Detection**: Return blocking triangles if no visibility found

---

## Implementation Details

### Core Methods

#### Basic Line of Sight
```go
func (d *LOSDetector) CheckLineOfSight(pos1, pos2 Vector3) (bool, []Triangle)
```
- Creates ray from pos1 to pos2
- Tests intersection with all triangles
- Returns visibility status and blocking triangles

#### FOV-Aware Detection
```go
func (d *LOSDetector) CheckLineOfSightWithFOV(pos1, pos2 Vector3, aimX1, aimY1, aimX2, aimY2 float64) (bool, bool, []Triangle)
```
- Combines geometric LOS with FOV checks
- Returns separate visibility for each player
- Considers camera angles and FOV cones

#### Box-Based Detection
```go
func (d *LOSDetector) CheckLineOfSightWithBox(pos1, pos2 Vector3, box1, box2 Box) (bool, []Triangle)
```
- Tests visibility between objects with dimensions
- Uses multiple sample points per object
- Accounts for object size and shape

#### Complete Detection
```go
func (d *LOSDetector) CheckLineOfSightFromPosition(playerPos Vector3, cameraAngleX, cameraAngleY float64, objectPos Vector3, objectSize Vector3) (bool, []Triangle)
```
- Main public method for player-to-object visibility
- Combines all detection methods
- Uses default player box dimensions (32×72×32)

---

## Usage Examples

### Basic Visibility Check
```go
detector, err := NewLOSDetector("de_ancient")
if err != nil {
    log.Fatal(err)
}

pos1 := Vector3{X: 100, Y: 200, Z: 50}
pos2 := Vector3{X: 300, Y: 400, Z: 60}

hasLOS, blockingTriangles := detector.CheckLineOfSight(pos1, pos2)
if hasLOS {
    fmt.Println("Clear line of sight")
} else {
    fmt.Printf("Blocked by %d triangles\n", len(blockingTriangles))
}
```

### FOV-Aware Detection
```go
playerPos := Vector3{X: 100, Y: 200, Z: 50}
targetPos := Vector3{X: 300, Y: 400, Z: 60}
aimX, aimY := 10.0, 45.0  // Camera angles in degrees

player1LOS, player2LOS, blocking := detector.CheckLineOfSightWithFOV(
    playerPos, targetPos, aimX, aimY, 0.0, 0.0)
```

### Box-Based Detection
```go
playerBox := Box{Width: 32, Height: 72, Depth: 32}
objectBox := Box{Width: 16, Height: 32, Depth: 16}

hasLOS, blocking := detector.CheckLineOfSightWithBox(
    playerPos, objectPos, playerBox, objectBox)
```

---

## Technical Notes

### Coordinate System
- **CS2 Standard**: X=Forward, Y=Left, Z=Up
- **Units**: Source engine units (1 unit ≈ 1 inch)
- **Precision**: 32-bit float coordinates

### Triangle Data
- **Source**: Generated from CS2 VPK files using cs2-map-parser
- **Format**: Binary .tri files with little-endian float32
- **Validation**: Automatic filtering of invalid triangles

### Algorithm Accuracy
- **Numerical Stability**: Epsilon checks for floating-point precision
- **Edge Cases**: Proper handling of parallel rays and degenerate triangles
- **Performance**: Optimized for real-time demo analysis

---

This line of sight detection system provides the foundation for accurate visibility analysis in CS2 demo processing, enabling sophisticated grenade effectiveness calculations and gunfight analysis.
