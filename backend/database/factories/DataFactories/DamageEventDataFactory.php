<?php

namespace Database\Factories\DataFactories;

use Faker\Factory as Faker;

class DamageEventDataFactory implements DataFactoryInterface
{
    /**
     * Create a single damage event or an array of damage events
     *
     * @param int|null $count Number of events to create (null for single event)
     * @param array $attributes Override default attributes
     * @return array|array[]
     */
    public static function create(?int $count = null, array $attributes = []): array
    {
        $faker = Faker::create();

        if ($count === null) {
            return self::generateSingleEvent($faker, $attributes);
        }

        $events = [];
        for ($i = 0; $i < $count; $i++) {
            $events[] = self::generateSingleEvent($faker, $attributes);
        }

        return $events;
    }

    /**
     * Generate a single damage event
     *
     * @param \Faker\Generator $faker
     * @param array $attributes
     * @return array
     */
    private static function generateSingleEvent($faker, array $attributes = []): array
    {
        $weapon = $faker->randomElement([
            'USP-S',
            'Glock-18',
            'P250',
            'Desert Eagle',
            'Five-SeveN',
            'Tec-9',
            'AK-47',
            'M4A1',
            'M4A4',
            'Galil AR',
            'FAMAS',
            'AWP',
            'SSG 08',
            'MAC-10',
            'MP9',
            'UMP-45',
            'P90',
            'PP-Bizon',
            'HE Grenade',
            'Molotov',
            'Incendiary Grenade',
            'Smoke Grenade',
            'Flashbang',
            'Decoy Grenade'
        ]);

        // Determine if it's a grenade weapon
        $isGrenade = in_array($weapon, [
            'HE Grenade',
            'Molotov',
            'Incendiary Grenade',
            'Smoke Grenade',
            'Flashbang',
            'Decoy Grenade'
        ]);

        // Generate realistic damage values based on weapon type
        $damage = self::generateDamageForWeapon($faker, $weapon);
        $armorDamage = self::generateArmorDamage($faker, $weapon, $damage);
        $healthDamage = $damage - $armorDamage;

        $defaultAttributes = [
            'armor_damage' => $armorDamage,
            'attacker_steam_id' => 'steam_' . $faker->numberBetween(76561198000000000, 76561198999999999),
            'damage' => $damage,
            'headshot' => $faker->boolean(20), // 20% chance of headshot
            'health_damage' => $healthDamage,
            'round_number' => $faker->numberBetween(1, 24),
            'round_time' => $faker->numberBetween(0, 115),
            'tick_timestamp' => $faker->numberBetween(0, 999999999),
            'victim_steam_id' => 'steam_' . $faker->numberBetween(76561198000000000, 76561198999999999),
            'weapon' => $weapon,
        ];

        return array_merge($defaultAttributes, $attributes);
    }

    /**
     * Generate realistic damage values based on weapon type
     *
     * @param \Faker\Generator $faker
     * @param string $weapon
     * @return int
     */
    private static function generateDamageForWeapon($faker, string $weapon): int
    {
        return match ($weapon) {
            // Pistols
            'USP-S' => $faker->numberBetween(25, 35),
            'Glock-18' => $faker->numberBetween(18, 30),
            'P250' => $faker->numberBetween(30, 40),
            'Desert Eagle' => $faker->numberBetween(50, 140),
            'Five-SeveN' => $faker->numberBetween(20, 30),
            'Tec-9' => $faker->numberBetween(25, 35),

            // Rifles
            'AK-47' => $faker->numberBetween(30, 125),
            'M4A1', 'M4A4' => $faker->numberBetween(25, 100),
            'Galil AR' => $faker->numberBetween(30, 90),
            'FAMAS' => $faker->numberBetween(25, 85),
            'AWP' => $faker->numberBetween(110, 140),
            'SSG 08' => $faker->numberBetween(80, 110),

            // SMGs
            'MAC-10' => $faker->numberBetween(15, 25),
            'MP9' => $faker->numberBetween(15, 25),
            'UMP-45' => $faker->numberBetween(20, 35),
            'P90' => $faker->numberBetween(20, 30),
            'PP-Bizon' => $faker->numberBetween(15, 25),

            // Grenades
            'HE Grenade' => $faker->numberBetween(0, 100),
            'Molotov', 'Incendiary Grenade' => $faker->numberBetween(0, 50),
            'Smoke Grenade' => $faker->numberBetween(0, 5),
            'Flashbang' => $faker->numberBetween(0, 10),
            'Decoy Grenade' => $faker->numberBetween(0, 5),

            default => $faker->numberBetween(20, 50),
        };
    }

    /**
     * Generate realistic armor damage based on weapon and total damage
     *
     * @param \Faker\Generator $faker
     * @param string $weapon
     * @param int $totalDamage
     * @return int
     */
    private static function generateArmorDamage($faker, string $weapon, int $totalDamage): int
    {
        // Grenades typically don't do much armor damage
        if (str_contains($weapon, 'Grenade') || $weapon === 'Molotov' || $weapon === 'Incendiary Grenade') {
            return $faker->numberBetween(0, min(20, $totalDamage));
        }

        // Pistols do moderate armor damage
        if (in_array($weapon, ['USP-S', 'Glock-18', 'P250', 'Five-SeveN', 'Tec-9'])) {
            return $faker->numberBetween(0, min(10, $totalDamage));
        }

        // Desert Eagle does significant armor damage
        if ($weapon === 'Desert Eagle') {
            return $faker->numberBetween(0, min(30, $totalDamage));
        }

        // Rifles do significant armor damage
        if (in_array($weapon, ['AK-47', 'M4A1', 'M4A4', 'Galil AR', 'FAMAS'])) {
            return $faker->numberBetween(0, min(25, $totalDamage));
        }

        // AWP does massive armor damage
        if ($weapon === 'AWP') {
            return $faker->numberBetween(0, min(40, $totalDamage));
        }

        // SMGs do moderate armor damage
        if (in_array($weapon, ['MAC-10', 'MP9', 'UMP-45', 'P90', 'PP-Bizon'])) {
            return $faker->numberBetween(0, min(15, $totalDamage));
        }

        return $faker->numberBetween(0, min(20, $totalDamage));
    }

    /**
     * Create a damage event with headshot
     *
     * @param array $attributes
     * @return array
     */
    public static function createHeadshot(array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['headshot' => true]));
    }

    /**
     * Create a damage event with specific weapon
     *
     * @param string $weapon
     * @param array $attributes
     * @return array
     */
    public static function createWithWeapon(string $weapon, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['weapon' => $weapon]));
    }

    /**
     * Create a damage event for a specific round
     *
     * @param int $roundNumber
     * @param array $attributes
     * @return array
     */
    public static function createForRound(int $roundNumber, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['round_number' => $roundNumber]));
    }
}
