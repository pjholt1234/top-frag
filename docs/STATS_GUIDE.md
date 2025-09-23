# Top Frag Statistics Guide

This comprehensive guide documents all statistics and metrics calculated by the Top Frag system, including their formulas, weightings, and underlying algorithms.

## Table of Contents

1. [Impact Rating System](#impact-rating-system)
2. [Grenade Effectiveness](#grenade-effectiveness)
3. [Trade Logic](#trade-logic)
4. [Player Complexion](#player-complexion)
5. [Additional Metrics](#additional-metrics)

---

## Impact Rating System

The Impact Rating System quantifies a player's influence on round outcomes through contextualized action effectiveness.

### Core Concepts

**Impact Rating** measures how much a player's actions contributed to or detracted from their team's chances of winning a round, considering the situational context and team strength differentials.

### Key Metrics

#### Total Impact
- **Definition**: Sum of all impact values from gunfight events a player was involved in
- **Calculation**: `Sum(Player1Impact + Player2Impact + AssisterImpact + FlashAssisterImpact)`
- **Range**: Unbounded (can be negative or positive)

#### Average Impact
- **Definition**: Average impact per gunfight event the player was involved in
- **Calculation**: `Total Impact / Number of Gunfight Events Involved In`
- **Range**: Unbounded (can be negative or positive)

#### Impact Percentage
- **Definition**: Normalized impact score on a 0-100% scale
- **Calculation**: `(Total Impact / 100.0) * 100.0`
- **Range**: 0-100% (using practical maximum of 100 points)
- **Purpose**: Provides intuitive percentage scale for player comparison

#### Round Swing Percentage
- **Definition**: Player's impact as percentage of maximum possible round impact
- **Calculation**: `(Total Impact / 1000.0) * 100.0`
- **Range**: 0-100% (using theoretical maximum of 1000 points per round)

### Impact Calculation Algorithm

#### 1. Team Strength Calculation
```
Team Strength = (Man Count × 0.6) + (Equipment Value × 0.4)
```

**Weightings:**
- **Man Count Weight**: 0.6 (60% of team strength)
- **Equipment Weight**: 0.4 (40% of team strength)
- **Base Player Value**: 2000 points per alive player

#### 2. Strength Differential
```
Strength Differential = (Opponent Strength - Team Strength) / Maximum Possible Strength
```

#### 3. Base Impact Calculation
```
Base Impact = Base Action Value × (1 + Strength Differential × 0.5)
```

**Base Action Values:**
- **Kill**: +100.0 points
- **Death**: -100.0 points
- **Assist**: +50.0 points
- **Flash Assist**: +25.0 points

#### 4. Context Multipliers
- **Opening Duel**: 1.5x (first gunfight of the round)
- **Won Clutch**: 2.0x (successful clutch attempt)
- **Failed Clutch**: 0.5x (unsuccessful clutch attempt)
- **Standard Action**: 1.0x (normal circumstances)

#### 5. Assist Impact Distribution
- **Assister Impact**: 40% of attacker's impact
- **Flash Assister Impact**: 20% of attacker's impact

### Constants

```go
// Team strength calculation weights
ManCountWeight  = 0.6  // Weight for man count in team strength
EquipmentWeight = 0.4  // Weight for equipment value in team strength
BasePlayerValue = 2000 // Base value per alive player

// Strength differential multiplier
StrengthDiffMultiplier = 0.5 // Multiplier for strength differential impact

// Context multipliers
OpeningDuelMultiplier  = 1.5 // First gunfight bonus
WonClutchMultiplier    = 2.0 // Won clutch bonus
FailedClutchMultiplier = 0.5 // Failed clutch penalty
StandardMultiplier     = 1.0 // Standard action

// Assist impact weights
AssistWeight      = 0.4 // Assister gets 40% of attacker's impact
FlashAssistWeight = 0.2 // Flash assister gets 20% of attacker's impact

// Base action impact values
BaseKillImpact        = 100.0  // Base impact for a kill
BaseDeathImpact       = -100.0 // Base impact for a death
BaseAssistImpact      = 50.0   // Base impact for an assist
BaseFlashAssistImpact = 25.0   // Base impact for a flash assist

// Impact percentage calculation
MaxPracticalImpact        = 100.0  // Practical maximum impact for percentage calculation
MaxPossibleImpactPerRound = 1000.0 // Maximum possible impact per round for swing percentage
```

---

## Grenade Effectiveness

Grenade effectiveness measures the impact of utility usage on round outcomes.

### Grenade Types Tracked

1. **Flashbangs** - Blinding utility
2. **HE Grenades** - Explosive damage
3. **Molotovs/Incendiaries** - Area denial and damage
4. **Smoke Grenades** - Vision blocking
5. **Decoy Grenades** - Audio distraction

### Effectiveness Calculation

#### Flashbang Effectiveness (-100 to +100)
```
Score = (Enemy Score - Friendly Penalty) / 100 * 100

Enemy Score = (Enemy Duration × 20) + (Enemy Players × 30) + (Kills × 50)
Friendly Penalty = (Friendly Duration × 20) + (Friendly Players × 30) + (Deaths × 50)
```

**Weightings:**
- **Enemy Duration**: 20 points (capped at 3.0 seconds)
- **Enemy Players**: 30 points (capped at 5 players)
- **Kills**: 50 points (capped at 1 kill)
- **Friendly Duration**: -20 points (capped at 3.0 seconds)
- **Friendly Players**: -30 points (capped at 5 players)
- **Deaths**: -50 points (capped at 1 death)

#### Explosive Effectiveness (-100 to +100)
```
Score = (Net Damage / 100) * 100
Net Damage = Enemy Damage - Team Damage
```

#### Smoke Effectiveness (-100 to +100)
```
Score = (Time Blocked × 0.35) + (Kills Through Smoke × 0.35) - (Friendly Hurt × 0.3)
```

**Weightings:**
- **Time Blocked**: 35% (capped at 18.0 seconds)
- **Kills Through Smoke**: 35% (capped at 3 kills)
- **Friendly Hurt**: -30% (capped at 3 instances)

### Round Aggregation
```
Round Effectiveness = Average of all grenade effectiveness scores in the round
```

### Match Aggregation
```
Match Effectiveness = Average Round Effectiveness - (Value Lost Penalty × 50)
```

**Value Lost Penalty**: Penalty for wasted utility value on death

---

## Trade Logic

Trade logic identifies and quantifies successful trade kills and trade opportunities.

### Trade Definition
A **trade** occurs when a teammate kills an opponent who just killed another teammate, within specific time and distance constraints.

### Trade Parameters

#### Time Window
- **Trade Time Window**: 3.0 seconds
- **Calculation**: `Death Time + 3.0 seconds`

#### Distance Threshold
- **Trade Distance**: 250 in-game units
- **Purpose**: Ensures teammates were in position to provide support

### Trade Metrics

#### Successful Trades
- **Definition**: Number of times a player successfully traded a teammate's death
- **Calculation**: Count of kills on original killer within time window and distance threshold

#### Total Possible Trades
- **Definition**: Number of trade opportunities available to a player
- **Calculation**: Count of teammate deaths within distance threshold

#### Trade Kill Percentage
- **Definition**: Success rate of trade opportunities
- **Calculation**: `(Successful Trades / Total Possible Trades) × 100`

#### Traded Deaths
- **Definition**: Number of times a player's death was successfully traded by a teammate
- **Calculation**: Count of teammate kills on player's killer within time window

#### Trade Death Percentage
- **Definition**: Rate of deaths that were successfully traded
- **Calculation**: `(Traded Deaths / Total Deaths) × 100`

### Trade Detection Algorithm

1. **Identify Deaths**: Track all deaths in the round with timestamps and positions
2. **Find Teammates**: Identify teammates within distance threshold
3. **Check Time Window**: Look for kills on original killer within 3 seconds
4. **Verify Trade**: Confirm the kill was on the player who killed the teammate
5. **Count Opportunities**: Track all possible trade scenarios

### Constants

```go
TradeTimeWindowSeconds = 3.0   // 3 seconds window for trades
TradeDistanceThreshold = 250.0 // 250 in-game units distance for trade eligibility
```

---

## Player Complexion

Player Complexion categorizes players into archetypes based on their statistical performance patterns.

### Complexion Types

#### 1. Opener
**Role**: Entry fragger, first contact specialist

**Key Metrics & Weightings:**
- **Average Round Time of Death**: Score 25, Weight 1.0 (lower is better)
- **Average Time to Contact**: Score 20, Weight 3.0 (lower is better)
- **First Kills Plus/Minus**: Score 3, Weight 5.0 (higher is better)
- **First Kill Attempts**: Score 4, Weight 4.0 (higher is better)
- **Traded Death Percentage**: Score 50, Weight 2.0 (higher is better)

#### 2. Closer
**Role**: Clutch specialist, late-round performer

**Key Metrics & Weightings:**
- **Average Round Time to Death**: Score 40, Weight 1.0 (higher is better)
- **Average Round Time to Contact**: Score 35, Weight 1.0 (higher is better)
- **Clutch Win Percentage**: Score 25, Weight 4.0 (higher is better)
- **Total Clutch Attempts**: Score 5, Weight 2.0 (higher is better)

#### 3. Support
**Role**: Utility specialist, team support

**Key Metrics & Weightings:**
- **Total Grenades Thrown**: Score 25, Weight 1.0 (higher is better)
- **Damage Dealt from Grenades**: Score 200, Weight 2.0 (higher is better)
- **Enemy Flash Duration**: Score 30, Weight 2.0 (higher is better)
- **Average Grenade Effectiveness**: Score 50, Weight 5.0 (higher is better)
- **Total Flashes Leading to Kills**: Score 5, Weight 2.0 (higher is better)

#### 4. Fragger
**Role**: High-impact eliminator, consistent performer

**Key Metrics & Weightings:**
- **Kill/Death Ratio**: Score 1.5, Weight 2.0 (higher is better)
- **Total Kills per Round**: Score 0.9, Weight 4.0 (higher is better)
- **Average Damage per Round**: Score 90, Weight 3.0 (higher is better)
- **Trade Kill Percentage**: Score 50, Weight 3.0 (higher is better)
- **Trade Opportunities per Round**: Score 1.5, Weight 1.0 (higher is better)

### Complexion Calculation

For each complexion type:
1. **Normalize Metrics**: Convert raw values to 0-1 scale based on target scores
2. **Apply Weights**: Multiply normalized values by their respective weights
3. **Sum Scores**: Calculate total weighted score for each complexion
4. **Determine Primary**: Player is assigned the complexion with highest score

### Clutch Scenarios

Clutch situations are tracked for 1v1 through 1v5 scenarios:
- **Clutch Attempts**: Number of times player was in clutch situation
- **Clutch Wins**: Number of successful clutch outcomes
- **Clutch Win Percentage**: `(Clutch Wins / Clutch Attempts) × 100`

---

## Additional Metrics

### Economy Metrics

#### Round Classifications
- **Eco Round**: Team value ≤ 2000
- **Force Buy Round**: Team value 2001-4000
- **Full Buy Round**: Team value > 4000

#### Economy Constants
```go
EcoThreshold      = 2000 // Below or equal to this value = eco round
ForceBuyThreshold = 4000 // Between eco and this value = force buy round
```

### Damage Metrics

#### Average Damage per Round (ADR)
- **Calculation**: `Total Damage / Rounds Played`
- **Purpose**: Measures consistent damage output

#### Headshot Percentage
- **Calculation**: `(Headshots / Kills) × 100`
- **Purpose**: Measures accuracy and aim quality

### Time Metrics

#### Average Round Time of Death
- **Calculation**: `Sum(Round Time of Death) / Deaths`
- **Purpose**: Measures survival time and positioning

#### Average Time to Contact
- **Calculation**: `Sum(Time to Contact) / Rounds`
- **Purpose**: Measures engagement speed and aggression

### Utility Metrics

#### Grenade Value Lost on Death
- **Calculation**: Sum of utility value lost when player dies with unused grenades
- **Purpose**: Measures utility management efficiency

#### Flash Duration Tracking
- **Enemy Flash Duration**: Time enemies were blinded by player's flashes
- **Friendly Flash Duration**: Time teammates were blinded by player's flashes
- **Purpose**: Measures flash effectiveness and team coordination

---

## Data Flow

### Round-Level Processing
1. **Gunfight Events**: Individual combat interactions
2. **Grenade Events**: Utility usage and effectiveness
3. **Damage Events**: Health and armor damage tracking
4. **Round Events**: Round outcome and scenario data

### Match-Level Aggregation
1. **Player Round Events**: Per-round statistics
2. **Player Match Events**: Match totals and averages
3. **Round Events**: Round-level summaries
4. **Match Events**: Overall match data

### Impact Calculation Timeline
1. **Real-time**: Gunfight impacts calculated during round processing
2. **Round End**: Player and round impact aggregation
3. **Match End**: Match-level impact aggregation
4. **Post-Processing**: Final impact percentage calculations

---

## Technical Implementation

### Go Parser Service
- **Impact Calculation**: `internal/parser/impact_rating.go`
- **Round Processing**: `internal/parser/round_handler.go`
- **Grenade Analysis**: `internal/utils/grenade_rating.go`
- **Trade Logic**: `internal/parser/round_handler.go`

### Laravel Web Application
- **Player Complexion**: `app/Services/Matches/PlayerComplexionService.php`
- **Statistics Display**: `app/Services/Matches/PlayerStatsService.php`
- **Data Models**: `app/Models/PlayerMatchEvent.php`, `app/Models/PlayerRoundEvent.php`

### Database Schema
- **Player Match Events**: Match-level aggregated statistics
- **Player Round Events**: Round-level detailed statistics
- **Round Events**: Round-level summaries
- **Gunfight Events**: Individual combat interactions
- **Grenade Events**: Utility usage tracking

---

This guide provides comprehensive documentation of all statistical calculations and metrics in the Top Frag system. For implementation details, refer to the source code in the respective service directories.
