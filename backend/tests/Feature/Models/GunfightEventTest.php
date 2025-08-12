<?php

namespace Tests\Feature\Models;

use App\Models\GameMatch;
use App\Models\GunfightEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GunfightEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_gunfight_event()
    {
        $gunfightEvent = GunfightEvent::factory()->create([
            'round_number' => 5,
            'round_time' => 120,
            'tick_timestamp' => 123456789,
            'player_1_hp_start' => 100,
            'player_2_hp_start' => 85,
            'player_1_armor' => 100,
            'player_2_armor' => 0,
            'player_1_flashed' => false,
            'player_2_flashed' => true,
            'player_1_weapon' => 'ak47',
            'player_2_weapon' => 'm4a1',
            'player_1_equipment_value' => 4000,
            'player_2_equipment_value' => 3000,
            'player_1_x' => 100.5,
            'player_1_y' => 200.5,
            'player_1_z' => 50.0,
            'player_2_x' => 150.5,
            'player_2_y' => 250.5,
            'player_2_z' => 50.0,
            'distance' => 75.5,
            'headshot' => true,
            'wallbang' => false,
            'penetrated_objects' => 0,
            'damage_dealt' => 100,
        ]);

        $this->assertInstanceOf(GunfightEvent::class, $gunfightEvent);
        $this->assertEquals(5, $gunfightEvent->round_number);
        $this->assertEquals(120, $gunfightEvent->round_time);
        $this->assertEquals(100, $gunfightEvent->player_1_hp_start);
        $this->assertEquals(85, $gunfightEvent->player_2_hp_start);
        $this->assertTrue($gunfightEvent->headshot);
        $this->assertFalse($gunfightEvent->wallbang);
        $this->assertEquals(100, $gunfightEvent->damage_dealt);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $gunfightEvent = new GunfightEvent;
        $expectedFillable = [
            'match_id',
            'round_number',
            'round_time',
            'tick_timestamp',
            'player_1_steam_id',
            'player_2_steam_id',
            'player_1_hp_start',
            'player_2_hp_start',
            'player_1_armor',
            'player_2_armor',
            'player_1_flashed',
            'player_2_flashed',
            'player_1_weapon',
            'player_2_weapon',
            'player_1_equipment_value',
            'player_2_equipment_value',
            'player_1_x',
            'player_1_y',
            'player_1_z',
            'player_2_x',
            'player_2_y',
            'player_2_z',
            'distance',
            'headshot',
            'wallbang',
            'penetrated_objects',
            'victor_steam_id',
            'damage_dealt',
        ];
        $this->assertEquals($expectedFillable, $gunfightEvent->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $gunfightEvent = GunfightEvent::factory()->create([
            'round_number' => 10,
            'round_time' => 180,
            'tick_timestamp' => 987654321,
            'player_1_hp_start' => 75,
            'player_2_hp_start' => 100,
            'player_1_armor' => 50,
            'player_2_armor' => 100,
            'player_1_flashed' => true,
            'player_2_flashed' => false,
            'player_1_equipment_value' => 5000,
            'player_2_equipment_value' => 4500,
            'player_1_x' => 200.0,
            'player_1_y' => 300.0,
            'player_1_z' => 75.0,
            'player_2_x' => 250.0,
            'player_2_y' => 350.0,
            'player_2_z' => 75.0,
            'distance' => 100.0,
            'headshot' => false,
            'wallbang' => true,
            'penetrated_objects' => 2,
            'damage_dealt' => 75,
        ]);

        $this->assertIsInt($gunfightEvent->round_number);
        $this->assertIsInt($gunfightEvent->round_time);
        $this->assertIsInt($gunfightEvent->tick_timestamp);
        $this->assertIsInt($gunfightEvent->player_1_hp_start);
        $this->assertIsInt($gunfightEvent->player_2_hp_start);
        $this->assertIsInt($gunfightEvent->player_1_armor);
        $this->assertIsInt($gunfightEvent->player_2_armor);
        $this->assertIsBool($gunfightEvent->player_1_flashed);
        $this->assertIsBool($gunfightEvent->player_2_flashed);
        $this->assertIsInt($gunfightEvent->player_1_equipment_value);
        $this->assertIsInt($gunfightEvent->player_2_equipment_value);
        $this->assertIsFloat($gunfightEvent->player_1_x);
        $this->assertIsFloat($gunfightEvent->player_1_y);
        $this->assertIsFloat($gunfightEvent->player_1_z);
        $this->assertIsFloat($gunfightEvent->player_2_x);
        $this->assertIsFloat($gunfightEvent->player_2_y);
        $this->assertIsFloat($gunfightEvent->player_2_z);
        $this->assertIsFloat($gunfightEvent->distance);
        $this->assertIsBool($gunfightEvent->headshot);
        $this->assertIsBool($gunfightEvent->wallbang);
        $this->assertIsInt($gunfightEvent->penetrated_objects);
        $this->assertIsInt($gunfightEvent->damage_dealt);
    }

    #[Test]
    public function it_belongs_to_match()
    {
        $match = GameMatch::factory()->create();
        $gunfightEvent = GunfightEvent::factory()->create(['match_id' => $match->id]);

        $this->assertInstanceOf(GameMatch::class, $gunfightEvent->match);
        $this->assertEquals($match->id, $gunfightEvent->match->id);
    }

    #[Test]
    public function it_belongs_to_player1()
    {
        $player1 = Player::factory()->create();
        $gunfightEvent = GunfightEvent::factory()->create(['player_1_steam_id' => $player1->steam_id]);

        $this->assertInstanceOf(Player::class, $gunfightEvent->player1);
        $this->assertEquals($player1->steam_id, $gunfightEvent->player1->steam_id);
    }

    #[Test]
    public function it_belongs_to_player2()
    {
        $player2 = Player::factory()->create();
        $gunfightEvent = GunfightEvent::factory()->create(['player_2_steam_id' => $player2->steam_id]);

        $this->assertInstanceOf(Player::class, $gunfightEvent->player2);
        $this->assertEquals($player2->steam_id, $gunfightEvent->player2->steam_id);
    }

    #[Test]
    public function it_belongs_to_victor()
    {
        $victor = Player::factory()->create();
        $gunfightEvent = GunfightEvent::factory()->create(['victor_steam_id' => $victor->steam_id]);

        $this->assertInstanceOf(Player::class, $gunfightEvent->victor);
        $this->assertEquals($victor->steam_id, $gunfightEvent->victor->steam_id);
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $gunfightEvent = new GunfightEvent;
        $this->assertEquals('gunfight_events', $gunfightEvent->getTable());
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $gunfightEvent = GunfightEvent::factory()->create();

        $this->assertDatabaseHas('gunfight_events', [
            'id' => $gunfightEvent->id,
            'match_id' => $gunfightEvent->match_id,
            'round_number' => $gunfightEvent->round_number,
        ]);
    }

    #[Test]
    public function it_can_have_null_victor()
    {
        $gunfightEvent = GunfightEvent::factory()->create(['victor_steam_id' => null]);

        $this->assertNull($gunfightEvent->victor);
        $this->assertNull($gunfightEvent->victor_steam_id);
    }
}
