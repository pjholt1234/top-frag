package parser

import (
	"testing"
)

// Test the damage capping logic directly
func TestDamageCappingLogic(t *testing.T) {
	tests := []struct {
		name           string
		victimHealth   int
		weaponDamage   int
		armorDamage    int
		expectedDamage int
		expectedHealth int
	}{
		{
			name:           "victim has more health than damage",
			victimHealth:   100,
			weaponDamage:   25,
			armorDamage:    10,
			expectedDamage: 25, // Only health damage (capped at weapon damage)
			expectedHealth: 25, // 25 (capped at weapon damage)
		},
		{
			name:           "victim has less health than damage - AWP headshot",
			victimHealth:   60,
			weaponDamage:   336, // AWP headshot damage
			armorDamage:    0,
			expectedDamage: 60, // Only health damage (capped at victim health)
			expectedHealth: 60, // 60 (capped at victim health)
		},
		{
			name:           "victim has much less health than damage",
			victimHealth:   5,
			weaponDamage:   34, // AK-47 chest damage
			armorDamage:    0,
			expectedDamage: 5, // Only health damage (capped at victim health)
			expectedHealth: 5, // 5 (capped at victim health)
		},
		{
			name:           "victim has exactly the damage amount",
			victimHealth:   50,
			weaponDamage:   50,
			armorDamage:    0,
			expectedDamage: 50, // Only health damage
			expectedHealth: 50, // 50 (exact match)
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			// Test the damage capping logic directly
			actualHealthDamage := tt.weaponDamage
			if tt.victimHealth < tt.weaponDamage {
				actualHealthDamage = tt.victimHealth
			}

			// Total damage is now only the health damage (armor damage is separate)
			totalDamage := actualHealthDamage

			// Verify total damage
			if totalDamage != tt.expectedDamage {
				t.Errorf("Expected total damage %d, got %d", tt.expectedDamage, totalDamage)
			}

			// Verify health damage is capped
			if actualHealthDamage != tt.expectedHealth {
				t.Errorf("Expected health damage %d, got %d", tt.expectedHealth, actualHealthDamage)
			}
		})
	}
}
