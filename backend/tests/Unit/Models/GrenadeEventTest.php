<?php

namespace Tests\Unit\Models;

use App\Models\GrenadeEvent;
use App\Models\GameMatch;
use App\Models\Player;
use App\Enums\GrenadeType;
use App\Enums\ThrowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GrenadeEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_grenade_event()
    {
        $grenadeEvent = GrenadeEvent::factory()->create([
            'round_number' => 3,
            'round_time' => 90,
            'tick_timestamp' => 123456789,
            'grenade_type' => GrenadeType::FLASHBANG,
            'player_x' => 100.5,
            'player_y' => 200.5,
            'player_z' => 50.0,
            'player_aim_x' => 150.5,
            'player_aim_y' => 250.5,
            'player_aim_z' => 50.0,
            'grenade_final_x' => 175.5,
            'grenade_final_y' => 275.5,
            'grenade_final_z' => 50.0,
            'damage_dealt' => 25,
            'flash_duration' => 2.5,
            'affected_players' => ['player1', 'player2'],
            'throw_type' => ThrowType::LINEUP,
            'effectiveness_rating' => 8,
        ]);

        $this->assertInstanceOf(GrenadeEvent::class, $grenadeEvent);
        $this->assertEquals(3, $grenadeEvent->round_number);
        $this->assertEquals(90, $grenadeEvent->round_time);
        $this->assertEquals(GrenadeType::FLASHBANG, $grenadeEvent->grenade_type);
        $this->assertEquals(ThrowType::LINEUP, $grenadeEvent->throw_type);
        $this->assertEquals(25, $grenadeEvent->damage_dealt);
        $this->assertEquals(2.5, $grenadeEvent->flash_duration);
        $this->assertEquals(['player1', 'player2'], $grenadeEvent->affected_players);
        $this->assertEquals(8, $grenadeEvent->effectiveness_rating);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $grenadeEvent = new GrenadeEvent();

        $expectedFillable = [
            'match_id',
            'round_number',
            'round_time',
            'tick_timestamp',
            'player_id',
            'grenade_type',
            'player_x',
            'player_y',
            'player_z',
            'player_aim_x',
            'player_aim_y',
            'player_aim_z',
            'grenade_final_x',
            'grenade_final_y',
            'grenade_final_z',
            'damage_dealt',
            'flash_duration',
            'affected_players',
            'throw_type',
            'effectiveness_rating',
        ];
        $this->assertEquals($expectedFillable, $grenadeEvent->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $grenadeEvent = GrenadeEvent::factory()->create([
            'round_number' => 7,
            'round_time' => 150,
            'tick_timestamp' => 987654321,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'player_x' => 200.0,
            'player_y' => 300.0,
            'player_z' => 75.0,
            'player_aim_x' => 250.0,
            'player_aim_y' => 350.0,
            'player_aim_z' => 75.0,
            'grenade_final_x' => 275.0,
            'grenade_final_y' => 375.0,
            'grenade_final_z' => 75.0,
            'damage_dealt' => 0,
            'flash_duration' => 0.0,
            'affected_players' => ['player3'],
            'throw_type' => ThrowType::UTILITY,
            'effectiveness_rating' => 6,
        ]);

        $this->assertIsInt($grenadeEvent->round_number);
        $this->assertIsInt($grenadeEvent->round_time);
        $this->assertIsInt($grenadeEvent->tick_timestamp);
        $this->assertInstanceOf(GrenadeType::class, $grenadeEvent->grenade_type);
        $this->assertIsFloat($grenadeEvent->player_x);
        $this->assertIsFloat($grenadeEvent->player_y);
        $this->assertIsFloat($grenadeEvent->player_z);
        $this->assertIsFloat($grenadeEvent->player_aim_x);
        $this->assertIsFloat($grenadeEvent->player_aim_y);
        $this->assertIsFloat($grenadeEvent->player_aim_z);
        $this->assertIsFloat($grenadeEvent->grenade_final_x);
        $this->assertIsFloat($grenadeEvent->grenade_final_y);
        $this->assertIsFloat($grenadeEvent->grenade_final_z);
        $this->assertIsInt($grenadeEvent->damage_dealt);
        $this->assertIsFloat($grenadeEvent->flash_duration);
        $this->assertIsArray($grenadeEvent->affected_players);
        $this->assertInstanceOf(ThrowType::class, $grenadeEvent->throw_type);
        $this->assertIsInt($grenadeEvent->effectiveness_rating);
    }

    #[Test]
    public function it_belongs_to_match()
    {
        $match = GameMatch::factory()->create();
        $grenadeEvent = GrenadeEvent::factory()->create(['match_id' => $match->id]);

        $this->assertInstanceOf(GameMatch::class, $grenadeEvent->match);
        $this->assertEquals($match->id, $grenadeEvent->match->id);
    }

    #[Test]
    public function it_belongs_to_player()
    {
        $player = Player::factory()->create();
        $grenadeEvent = GrenadeEvent::factory()->create(['player_id' => $player->id]);

        $this->assertInstanceOf(Player::class, $grenadeEvent->player);
        $this->assertEquals($player->id, $grenadeEvent->player->id);
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $grenadeEvent = new GrenadeEvent();
        $this->assertEquals('grenade_events', $grenadeEvent->getTable());
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $grenadeEvent = GrenadeEvent::factory()->create();

        $this->assertDatabaseHas('grenade_events', [
            'id' => $grenadeEvent->id,
            'round_number' => $grenadeEvent->round_number,
            'round_time' => $grenadeEvent->round_time,
        ]);
    }

    #[Test]
    public function it_can_have_empty_affected_players()
    {
        $grenadeEvent = GrenadeEvent::factory()->create([
            'affected_players' => [],
        ]);

        $this->assertIsArray($grenadeEvent->affected_players);
        $this->assertEmpty($grenadeEvent->affected_players);
    }

    #[Test]
    public function it_can_have_zero_damage_and_flash_duration()
    {
        $grenadeEvent = GrenadeEvent::factory()->create([
            'damage_dealt' => 0,
            'flash_duration' => 0.0,
        ]);

        $this->assertEquals(0, $grenadeEvent->damage_dealt);
        $this->assertEquals(0.0, $grenadeEvent->flash_duration);
    }
}
