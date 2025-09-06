<?php

namespace Tests\Feature\Models;

use App\Enums\GrenadeType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
            'friendly_flash_duration' => 1.5,
            'enemy_flash_duration' => 2.5,
            'friendly_players_affected' => 1,
            'enemy_players_affected' => 2,
            'throw_type' => 'lineup',
            'effectiveness_rating' => 8,
        ]);

        $this->assertInstanceOf(GrenadeEvent::class, $grenadeEvent);
        $this->assertEquals(3, $grenadeEvent->round_number);
        $this->assertEquals(90, $grenadeEvent->round_time);
        $this->assertEquals(GrenadeType::FLASHBANG, $grenadeEvent->grenade_type);
        $this->assertEquals('lineup', $grenadeEvent->throw_type);
        $this->assertEquals(25, $grenadeEvent->damage_dealt);
        $this->assertEquals(1.5, $grenadeEvent->friendly_flash_duration);
        $this->assertEquals(2.5, $grenadeEvent->enemy_flash_duration);
        $this->assertEquals(1, $grenadeEvent->friendly_players_affected);
        $this->assertEquals(2, $grenadeEvent->enemy_players_affected);
        $this->assertEquals(8, $grenadeEvent->effectiveness_rating);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $grenadeEvent = new GrenadeEvent;

        $expectedFillable = [
            'match_id',
            'round_number',
            'round_time',
            'tick_timestamp',
            'player_steam_id',
            'player_side',
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
            'friendly_flash_duration',
            'enemy_flash_duration',
            'friendly_players_affected',
            'enemy_players_affected',
            'throw_type',
            'effectiveness_rating',
            'flash_leads_to_kill',
            'flash_leads_to_death',
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
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 0.0,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 0,
            'throw_type' => 'utility',
            'effectiveness_rating' => 9,
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
        $this->assertIsFloat($grenadeEvent->friendly_flash_duration);
        $this->assertIsFloat($grenadeEvent->enemy_flash_duration);
        $this->assertIsInt($grenadeEvent->friendly_players_affected);
        $this->assertIsInt($grenadeEvent->enemy_players_affected);
        $this->assertIsString($grenadeEvent->throw_type);
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
        $grenadeEvent = GrenadeEvent::factory()->create(['player_steam_id' => $player->steam_id]);

        $this->assertInstanceOf(Player::class, $grenadeEvent->player);
        $this->assertEquals($player->steam_id, $grenadeEvent->player->steam_id);
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $grenadeEvent = new GrenadeEvent;
        $this->assertEquals('grenade_events', $grenadeEvent->getTable());
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $grenadeEvent = GrenadeEvent::factory()->create();

        $this->assertDatabaseHas('grenade_events', [
            'id' => $grenadeEvent->id,
            'match_id' => $grenadeEvent->match_id,
            'round_number' => $grenadeEvent->round_number,
        ]);
    }

    #[Test]
    public function it_can_have_empty_affected_players()
    {
        $grenadeEvent = GrenadeEvent::factory()->create([
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 0,
        ]);

        $this->assertEquals(0, $grenadeEvent->friendly_players_affected);
        $this->assertEquals(0, $grenadeEvent->enemy_players_affected);
    }

    #[Test]
    public function it_can_have_zero_damage_and_flash_duration()
    {
        $grenadeEvent = GrenadeEvent::factory()->create([
            'damage_dealt' => 0,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 0.0,
        ]);

        $this->assertEquals(0, $grenadeEvent->damage_dealt);
        $this->assertEquals(0.0, $grenadeEvent->friendly_flash_duration);
        $this->assertEquals(0.0, $grenadeEvent->enemy_flash_duration);
    }
}
