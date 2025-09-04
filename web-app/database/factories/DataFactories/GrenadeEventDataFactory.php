<?php

namespace Database\Factories\DataFactories;

use Faker\Factory as Faker;

class GrenadeEventDataFactory implements DataFactoryInterface
{
    /**
     * Create a single grenade event or an array of grenade events
     *
     * @param  int|null  $count  Number of events to create (null for single event)
     * @param  array  $attributes  Override default attributes
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
     * Generate a single grenade event
     *
     * @param  \Faker\Generator  $faker
     */
    private static function generateSingleEvent($faker, array $attributes = []): array
    {
        $grenadeType = $faker->randomElement([
            'hegrenade',
            'smokegrenade',
            'flashbang',
            'molotov',
            'incendiary',
            'decoy',
        ]);

        // Generate realistic coordinates based on CS:GO map dimensions
        $playerX = $faker->numberBetween(-2500, 2500);
        $playerY = $faker->numberBetween(-1500, 1500);
        $playerZ = $faker->numberBetween(-50, 300);

        // Generate final grenade position (usually different from player position)
        $grenadeFinalX = $playerX + $faker->numberBetween(-800, 800);
        $grenadeFinalY = $playerY + $faker->numberBetween(-800, 800);
        $grenadeFinalZ = $faker->numberBetween(0, 200);

        // Generate player aim coordinates (relative to player position)
        $playerAimX = $faker->numberBetween(-200, 200);
        $playerAimY = $faker->numberBetween(-10, 10);
        $playerAimZ = 0; // Usually 0 for aim

        $defaultAttributes = [
            'damage_dealt' => self::generateDamageForGrenade($faker, $grenadeType),
            'grenade_final_x' => $grenadeFinalX,
            'grenade_final_y' => $grenadeFinalY,
            'grenade_final_z' => $grenadeFinalZ,
            'grenade_type' => $grenadeType,
            'player_aim_x' => $playerAimX,
            'player_aim_y' => $playerAimY,
            'player_aim_z' => $playerAimZ,
            'player_steam_id' => 'steam_'.$faker->numberBetween(76561198000000000, 76561198999999999),
            'player_x' => $playerX,
            'player_y' => $playerY,
            'player_z' => $playerZ,
            'round_number' => $faker->numberBetween(1, 30),
            'round_time' => $faker->numberBetween(0, 115),
            'throw_type' => $faker->randomElement(['lineup', 'reaction', 'pre_aim', 'utility']),
            'tick_timestamp' => $faker->numberBetween(0, 999999999),
            'flash_leads_to_kill' => $faker->boolean(20), // 20% chance of leading to kill
            'flash_leads_to_death' => $faker->boolean(10), // 10% chance of leading to death
        ];

        return array_merge($defaultAttributes, $attributes);
    }

    /**
     * Generate realistic damage values based on grenade type
     *
     * @param  \Faker\Generator  $faker
     */
    private static function generateDamageForGrenade($faker, string $grenadeType): int
    {
        return match ($grenadeType) {
            'hegrenade' => $faker->numberBetween(0, 100), // HE grenades can do 0-100 damage
            'molotov', 'incendiary' => $faker->numberBetween(0, 50), // Fire grenades do less damage
            'smokegrenade' => $faker->numberBetween(0, 5), // Smoke grenades do minimal damage
            'flashbang' => $faker->numberBetween(0, 10), // Flashbangs do minimal damage
            'decoy' => $faker->numberBetween(0, 5), // Decoys do minimal damage
            default => $faker->numberBetween(0, 20),
        };
    }

    /**
     * Create a grenade event with specific grenade type
     */
    public static function createWithGrenadeType(string $grenadeType, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['grenade_type' => $grenadeType]));
    }

    /**
     * Create a grenade event for a specific round
     */
    public static function createForRound(int $roundNumber, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['round_number' => $roundNumber]));
    }

    /**
     * Create a grenade event with specific throw type
     */
    public static function createWithThrowType(string $throwType, array $attributes = []): array
    {
        return self::create(null, array_merge($attributes, ['throw_type' => $throwType]));
    }

    /**
     * Create a HE grenade event
     */
    public static function createHEGrenade(array $attributes = []): array
    {
        return self::createWithGrenadeType('hegrenade', $attributes);
    }

    /**
     * Create a smoke grenade event
     */
    public static function createSmokeGrenade(array $attributes = []): array
    {
        return self::createWithGrenadeType('smokegrenade', $attributes);
    }

    /**
     * Create a flashbang event
     */
    public static function createFlashbang(array $attributes = []): array
    {
        return self::createWithGrenadeType('flashbang', $attributes);
    }

    /**
     * Create a molotov event
     */
    public static function createMolotov(array $attributes = []): array
    {
        return self::createWithGrenadeType('molotov', $attributes);
    }
}
