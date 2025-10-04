package types

import "testing"

func TestGetWeaponCategory(t *testing.T) {
	tests := []struct {
		weaponName string
		expected   string
	}{
		// SMG weapons
		{WeaponBizon, WeaponCategorySMG},
		{WeaponP90, WeaponCategorySMG},
		{WeaponUMP45, WeaponCategorySMG},
		{WeaponMAC10, WeaponCategorySMG},
		{WeaponMP9, WeaponCategorySMG},

		// Rifle weapons
		{WeaponAK47, WeaponCategoryRifle},
		{WeaponM4A4, WeaponCategoryRifle},
		{WeaponM4A1, WeaponCategoryRifle},
		{WeaponAUG, WeaponCategoryRifle},
		{WeaponSG556, WeaponCategoryRifle},
		{WeaponFamas, WeaponCategoryRifle},
		{WeaponGalil, WeaponCategoryRifle},

		// Other weapons
		{WeaponAWP, WeaponCategoryOther},
		{WeaponDeagle, WeaponCategoryOther},
		{WeaponUSP, WeaponCategoryOther},
		{WeaponGlock, WeaponCategoryOther},
		{"unknown_weapon", WeaponCategoryOther},
	}

	for _, tt := range tests {
		t.Run(tt.weaponName, func(t *testing.T) {
			result := GetWeaponCategory(tt.weaponName)
			if result != tt.expected {
				t.Errorf("GetWeaponCategory(%s) = %s, expected %s", tt.weaponName, result, tt.expected)
			}
		})
	}
}

func TestIsSprayWeapon(t *testing.T) {
	tests := []struct {
		weaponName string
		expected   bool
	}{
		// SMG weapons - should be spray weapons
		{WeaponBizon, true},
		{WeaponP90, true},
		{WeaponUMP45, true},
		{WeaponMAC10, true},
		{WeaponMP9, true},

		// Rifle weapons - should be spray weapons
		{WeaponAK47, true},
		{WeaponM4A4, true},
		{WeaponM4A1, true},
		{WeaponAUG, true},
		{WeaponSG556, true},
		{WeaponFamas, true},
		{WeaponGalil, true},

		// Other weapons - should not be spray weapons
		{WeaponAWP, false},
		{WeaponDeagle, false},
		{WeaponUSP, false},
		{WeaponGlock, false},
		{"unknown_weapon", false},
	}

	for _, tt := range tests {
		t.Run(tt.weaponName, func(t *testing.T) {
			result := IsSprayWeapon(tt.weaponName)
			if result != tt.expected {
				t.Errorf("IsSprayWeapon(%s) = %v, expected %v", tt.weaponName, result, tt.expected)
			}
		})
	}
}
