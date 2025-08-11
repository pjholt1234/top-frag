<?php

namespace Database\Factories\DataFactories;

use Faker\Factory as Faker;

class GunfightEventDataFactory implements DataFactoryInterface
{
    /**
     * Create a single gunfight event or an array of gunfight events
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
     * Generate a single gunfight event
     *
     * @param \Faker\Generator $faker
     * @param array $attributes
     * @return array
     */
    private static function generateSingleEvent($faker, array $attributes = []): array
    {
        $weapons = [
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
            'PP-Bizon'
        ];

        $player1Weapon = $faker->randomElement($weapons);
        $player2Weapon = $faker->randomElement($weapons);

        // Generate realistic coordinates for both players
        $player1X = $faker->numberBetween(-2500, 2500);
        $player1Y = $faker->numberBetween(-1500, 1500);
        $player1Z = $faker->numberBetween(-50, 300);

        $player2X = $player1X + $faker->numberBetween(-1000, 1000);
        $player2Y = $player1Y + $faker->numberBetween(-1000, 1000);
        $player2Z = $faker->numberBetween(-50, 300);

        // Calculate distance between players
        $distance = sqrt(
            pow($player2X - $player1X, 2) +
                pow($player2Y - $player1Y, 2) +
                pow($player2Z - $player1Z, 2)
        );

        // Determine victor (randomly, but could be based on weapon/health/armor)
        $victor = $faker->randomElement(['player_1', 'player_2']);
        $victorSteamId = $victor === 'player_1' ?
            'steam_' . $faker->numberBetween(76561198000000000, 76561198999999999) :
            'steam_' . $faker->numberBetween(76561198000000000, 76561198999999999);

        $defaultAttributes = [
            'damage_dealt' => $faker->numberBetween(50, 100), // Usually lethal damage
            'distance' => $distance,
            'headshot' => $faker->boolean(25), // 25% chance of headshot
            'penetrated_objects' => $faker->numberBetween(0, 3), // 0-3 objects penetrated
            'player_1_armor' => $faker->numberBetween(0, 100),
            'player_1_equipment_value' => $faker->numberBetween(0, 10000),
            'player_1_flashed' => $faker->boolean(15), // 15% chance of being flashed
            'player_1_hp_start' => $faker->numberBetween(1, 100),
            'player_1_steam_id' => 'steam_' . $faker->numberBetween(76561198000000000, 76561198999999999),
            'player_1_weapon' => $player1Weapon,
            'player_1_x' => $player1X,
            'player_1_y' => $player1Y,
            'player_1_z' => $player1Z,
            'player_2_armor' => $faker->numberBetween(0, 100),
            'player_2_equipment_value' => $faker->numberBetween(0, 10000),
            'player_2_flashed' => $faker->boolean(15), // 15% chance of being flashed
            'player_2_hp_start' => $faker->numberBetween(1, 100),
            'player_2_steam_id' => 'steam_' . $faker->numberBetween(76561198000000000, 76561198999999999),
            'player_2_weapon' => $player2Weapon,
            'player_2_x' => $player2X,
            'player_2_y' => $player2Y,
            'player_2_z' => $player2Z,
            'round_number' => $faker->numberBetween(1, 30),
            'round_time' => $faker->numberBetween(0, 115),
            'tick_timestamp' => $faker->numberBetween(0, 999999999),
            'victor_steam_id' => $victorSteamId,
            'wallbang' => $faker->boolean(10), // 10% chance of wallbang
        ];

        return array_merge($defaultAttributes, $attributes);
    }

    /**
     * Create a gunfight event with headshot
     *
     * @param array $attributes
     * @return array
     */
    public static function createHeadshot(array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['headshot' => true]));
    }

    /**
     * Create a gunfight event with wallbang
     *
     * @param array $attributes
     * @return array
     */
    public static function createWallbang(array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['wallbang' => true]));
    }

    /**
     * Create a gunfight event with specific weapons
     *
     * @param string $player1Weapon
     * @param string $player2Weapon
     * @param array $attributes
     * @return array
     */
    public static function createWithWeapons(string $player1Weapon, string $player2Weapon, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, [
            'player_1_weapon' => $player1Weapon,
            'player_2_weapon' => $player2Weapon
        ]));
    }

    /**
     * Create a gunfight event for a specific round
     *
     * @param int $roundNumber
     * @param array $attributes
     * @return array
     */
    public static function createForRound(int $roundNumber, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['round_number' => $roundNumber]));
    }

    /**
     * Create a gunfight event with specific distance range
     *
     * @param int $minDistance
     * @param int $maxDistance
     * @param array $attributes
     * @return array
     */
    public static function createWithDistanceRange(int $minDistance, int $maxDistance, array $attributes = []): array
    {
        $faker = Faker::create();
        $distance = $faker->numberBetween($minDistance, $maxDistance);

        return self::create(null, array_merge($attributes, ['distance' => $distance]));
    }

    /**
     * Create a close-range gunfight event
     *
     * @param array $attributes
     * @return array
     */
    public static function createCloseRange(array $attributes = []): array
    {
        return self::createWithDistanceRange(0, 300, $attributes);
    }

    /**
     * Create a medium-range gunfight event
     *
     * @param array $attributes
     * @return array
     */
    public static function createMediumRange(array $attributes = []): array
    {
        return self::createWithDistanceRange(300, 800, $attributes);
    }

    /**
     * Create a long-range gunfight event
     *
     * @param array $attributes
     * @return array
     */
    public static function createLongRange(array $attributes = []): array
    {
        return self::createWithDistanceRange(800, 2000, $attributes);
    }

    /**
     * Create a gunfight event with specific victor
     *
     * @param string $victorSteamId
     * @param array $attributes
     * @return array
     */
    public static function createWithVictor(string $victorSteamId, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['victor_steam_id' => $victorSteamId]));
    }

    /**
     * Create a gunfight event with flashed players
     *
     * @param bool $player1Flashed
     * @param bool $player2Flashed
     * @param array $attributes
     * @return array
     */
    public static function createWithFlashedPlayers(bool $player1Flashed = true, bool $player2Flashed = true, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, [
            'player_1_flashed' => $player1Flashed,
            'player_2_flashed' => $player2Flashed
        ]));
    }
}
