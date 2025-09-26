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
- **Definition**: Player's influence on round outcomes with outcome-based weighting
- **Calculation**: `(Player Impact / 500.0) × (1 + Outcome Bonus) × 100.0`
- **Range**: Variable (can be negative for negative impact)
- **Outcome Bonus**: +0.3 for round wins, -0.1 for round losses
- **Purpose**: Measures actual influence on round outcomes, rewarding impactful plays in winning rounds

**Examples:**
- **Round Win**: 200 impact → `(200/500) × (1 + 0.3) × 100 = 52%`
- **Round Loss**: 200 impact → `(200/500) × (1 - 0.1) × 100 = 36%`
- **Round Win (Negative)**: -100 impact → `(-100/500) × (1 + 0.3) × 100 = -26%`
- **Round Loss (Negative)**: -100 impact → `(-100/500) × (1 - 0.1) × 100 = -18%`

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
	
	// Round swing percentage calculation
	TeamMaxImpactPerRound     = 500.0  // Theoretical maximum team impact per round
	RoundWinOutcomeBonus      = 0.3   // Bonus multiplier for round wins
	RoundLossOutcomePenalty   = -0.1  // Penalty multiplier for round losses
```

---

## Grenade Effectiveness

Grenade effectiveness measures the impact of utility usage on round outcomes using a comprehensive scoring system that rewards both utility investment and effective usage.

### Grenade Types Tracked

1. **Flashbangs** - Blinding utility
2. **HE Grenades** - Explosive damage
3. **Molotovs/Incendiaries** - Damage
4. **Smoke Grenades** - Vision blocking

### New Grenade Effectiveness Formula

The grenade effectiveness system uses a three-component scoring approach:

```
Final Score = Grenade Used Bonus + Utility Management Score + Grenade Effectiveness Average
```

#### Component 1: Grenade Used Bonus
- **Formula**: `10 points × Total Grenades Thrown`
- **Purpose**: Rewards utility investment and participation
- **Range**: 0+ points (scales with grenades thrown)

#### Component 2: Utility Management Score
- **Formula**: `20 × (1 - (Grenade Value Lost / 1300))`
- **Purpose**: Rewards surviving with utility intact
- **Range**: 0-20 points
- **Parameters**:
  - **No utility lost**: 20 points (full score)
  - **650 utility lost**: 10 points (50% score)
  - **1300 utility lost**: 0 points (no score)

#### Component 3: Grenade Effectiveness Average
- **Formula**: `Sum of Individual Grenade Scores / Number of Measured Grenades`
- **Purpose**: Measures quality of utility usage
- **Range**: -50 to +50 per grenade (averaged)

### Individual Grenade Scoring

#### Flashbang Effectiveness (-50 to +50)
```
Score = (Enemy Duration × 10) + (Enemy Count × 10) + (Leads to Kill × 25)
       - (Friendly Duration × 7) - (Friendly Count × 7) - (Leads to Death × 25)
```

**Weightings:**
- **Enemy Duration**: +10 points per second
- **Enemy Count**: +10 points per enemy flashed
- **Leads to Kill**: +25 points
- **Friendly Duration**: -7 points per second
- **Friendly Count**: -7 points per friendly flashed
- **Leads to Death**: -25 points

#### HE Grenade Effectiveness (-50 to +50)
```
Score = (Enemy Damage × 2) - (Team Damage × 1)
```

**Weightings:**
- **Enemy Damage**: +2 points per damage
- **Team Damage**: -1 point per damage

#### Molotov/Incendiary Effectiveness (-50 to +50)
```
Score = (Enemy Damage × 1) - (Team Damage × 0.5)
```

**Weightings:**
- **Enemy Damage**: +1 point per damage
- **Team Damage**: -0.5 points per damage

#### Smoke Grenade Effectiveness (-100 to +100)
```
Score = (Blocking Duration / 64) × 100
```

**Weightings:**
- **Blocking Duration**: 1 point per 64 ticks blocked
- **Maximum Effectiveness**: 18 seconds (1152 ticks) = 18 points
- **Scaling**: Normalized to -100 to +100 range

**Calculation Process:**
1. **Tick-by-Tick Analysis**: System checks every tick during smoke duration (18 seconds = 1152 ticks)
2. **Enemy Detection**: Identifies enemy players within effective range (250 units)
3. **Line of Sight Check**: Determines if smoke blocks enemy line of sight to teammates
4. **Blocking Count**: Tracks number of ticks where at least one enemy has blocked LOS
5. **Effectiveness Scoring**: Converts blocking ticks to effectiveness points (1 point per 64 ticks)

### Round-Level Calculation

#### Inclusion Criteria
- **Must have grenades thrown OR grenades lost on death**
- **No grenades thrown AND no grenades lost**: Excluded from calculation

#### Final Score Calculation
```go
// Calculate components
grenadeUsedBonus := float64(totalGrenadesThrown) * 10.0
utilityManagementScore := 20.0 * (1.0 - (grenadeValueLost / 1300.0))
grenadeEffectivenessAverage := totalEffectivenessRating / totalMeasuredGrenades

// Final score
finalScore := grenadeUsedBonus + utilityManagementScore + grenadeEffectivenessAverage
```

### Score Ranges and Interpretation

#### Typical Score Ranges
- **Excellent (80-100)**: Many grenades + great effectiveness + no utility lost
- **Good (60-79)**: Good grenade usage + decent effectiveness + minimal utility lost
- **Average (40-59)**: Baseline performance with some utility usage
- **Below Average (20-39)**: Some bad utility usage or significant utility lost
- **Poor (0-19)**: Consistently bad utility usage or high utility loss
- **No Score**: No grenades thrown and no utility lost

**Note**: Final scores are capped at 100 points maximum.

#### Example Calculations

**Scenario 1: Decent Player (3 grenades, 0 utility lost)**
```
Grenade Used Bonus: 3 × 10 = 30 points
Utility Management: 20 points (no utility lost)
Grenade Effectiveness: (20 + 10 - 5) / 3 = 8.33 points
Final Score: 30 + 20 + 8.33 = 58 points
```

**Scenario 2: Great Player (4 grenades, 0 utility lost)**
```
Grenade Used Bonus: 4 × 10 = 40 points
Utility Management: 20 points (no utility lost)
Grenade Effectiveness: (40 + 30 + 25 + 35) / 4 = 32.5 points
Final Score: 40 + 20 + 32.5 = 92 points
```

**Scenario 3: Exceptional Player (6 grenades, 0 utility lost)**
```
Grenade Used Bonus: 6 × 10 = 60 points
Utility Management: 20 points (no utility lost)
Grenade Effectiveness: (50 + 45 + 40 + 35 + 30 + 25) / 6 = 37.5 points
Raw Score: 60 + 20 + 37.5 = 117.5 points
Final Score: 100 points (capped at 100)
```

**Scenario 4: Bad Player (3 grenades, 0 utility lost)**
```
Grenade Used Bonus: 3 × 10 = 30 points
Utility Management: 20 points (no utility lost)
Grenade Effectiveness: (-30 - 20 - 15) / 3 = -21.67 points
Final Score: 30 + 20 - 21.67 = 28 points
```

### Benefits of New System

1. **Encourages Utility Investment**: 10 points per grenade thrown
2. **Rewards Quality Usage**: Individual grenade effectiveness matters
3. **Penalizes Bad Usage**: Team damage and friendly flashes hurt scores
4. **Values Utility Management**: Surviving with utility intact is rewarded
5. **Fair Participation**: Only players who use utility get scored
6. **Quality Over Quantity**: Average effectiveness prevents spam

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

This guide provides comprehensive documentation of all statistical calculations and metrics in the Top Frag system. For implementation details, refer to the source code in the respective service directories.
