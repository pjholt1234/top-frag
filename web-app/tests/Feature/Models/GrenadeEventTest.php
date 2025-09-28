<?php

namespace Tests\Feature\Models;

use App\Enums\GrenadeType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrenadeEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_grenade_event()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();

        $grenadeEvent = GrenadeEvent::create([
            'match_id' => $match->id,
            'round_number' => 1,
            'round_time' => 120,
            'tick_timestamp' => 12345,
            'player_steam_id' => $player->steam_id,
            'player_side' => 'CT',
            'grenade_type' => GrenadeType::FLASHBANG,
            'player_x' => 100.5,
            'player_y' => 200.3,
            'player_z' => 50.0,
            'player_aim_x' => 0.0,
            'player_aim_y' => 0.0,
            'player_aim_z' => 0.0,
            'grenade_final_x' => 150.0,
            'grenade_final_y' => 250.0,
            'grenade_final_z' => 60.0,
            'damage_dealt' => 0,
            'team_damage_dealt' => 0,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 2.5,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 2,
            'throw_type' => 'overhand',
            'effectiveness_rating' => 8,
            'flash_leads_to_kill' => true,
            'flash_leads_to_death' => false,
            'smoke_blocking_duration' => 0,
        ]);

        $this->assertDatabaseHas('grenade_events', [
            'id' => $grenadeEvent->id,
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG->value,
        ]);
    }

    public function test_it_has_fillable_attributes()
    {
        $grenadeEvent = new GrenadeEvent;
        $fillable = $grenadeEvent->getFillable();

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
            'team_damage_dealt',
            'friendly_flash_duration',
            'enemy_flash_duration',
            'friendly_players_affected',
            'enemy_players_affected',
            'throw_type',
            'effectiveness_rating',
            'flash_leads_to_kill',
            'flash_leads_to_death',
            'smoke_blocking_duration',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    public function test_it_casts_attributes_correctly()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();

        $grenadeEvent = GrenadeEvent::create([
            'match_id' => $match->id,
            'round_number' => 1,
            'round_time' => 120,
            'tick_timestamp' => 12345,
            'player_steam_id' => $player->steam_id,
            'player_side' => 'CT',
            'grenade_type' => GrenadeType::HE_GRENADE,
            'player_x' => 100.5,
            'player_y' => 200.3,
            'player_z' => 50.0,
            'player_aim_x' => 0.0,
            'player_aim_y' => 0.0,
            'player_aim_z' => 0.0,
            'grenade_final_x' => 150.0,
            'grenade_final_y' => 250.0,
            'grenade_final_z' => 60.0,
            'damage_dealt' => 25,
            'team_damage_dealt' => 0,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 0.0,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 0,
            'throw_type' => 'overhand',
            'effectiveness_rating' => 6,
            'flash_leads_to_kill' => false,
            'flash_leads_to_death' => false,
            'smoke_blocking_duration' => 0,
        ]);

        $this->assertIsInt($grenadeEvent->round_number);
        $this->assertIsInt($grenadeEvent->round_time);
        $this->assertIsInt($grenadeEvent->tick_timestamp);
        $this->assertIsString($grenadeEvent->player_side);
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
        $this->assertIsInt($grenadeEvent->team_damage_dealt);
        $this->assertIsFloat($grenadeEvent->friendly_flash_duration);
        $this->assertIsFloat($grenadeEvent->enemy_flash_duration);
        $this->assertIsInt($grenadeEvent->friendly_players_affected);
        $this->assertIsInt($grenadeEvent->enemy_players_affected);
        $this->assertIsString($grenadeEvent->throw_type);
        $this->assertIsInt($grenadeEvent->effectiveness_rating);
        $this->assertIsBool($grenadeEvent->flash_leads_to_kill);
        $this->assertIsBool($grenadeEvent->flash_leads_to_death);
        $this->assertIsInt($grenadeEvent->smoke_blocking_duration);
    }

    public function test_it_belongs_to_match()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();

        $grenadeEvent = GrenadeEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
        ]);

        $this->assertInstanceOf(GameMatch::class, $grenadeEvent->match);
        $this->assertEquals($match->id, $grenadeEvent->match->id);
    }

    public function test_it_belongs_to_player()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();

        $grenadeEvent = GrenadeEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
        ]);

        $this->assertInstanceOf(Player::class, $grenadeEvent->player);
        $this->assertEquals($player->steam_id, $grenadeEvent->player->steam_id);
    }

    public function test_it_uses_correct_table_name()
    {
        $grenadeEvent = new GrenadeEvent;
        $this->assertEquals('grenade_events', $grenadeEvent->getTable());
    }

    public function test_it_can_be_created_with_factory()
    {
        $grenadeEvent = GrenadeEvent::factory()->create();

        $this->assertDatabaseHas('grenade_events', [
            'id' => $grenadeEvent->id,
        ]);
    }

    public function test_generate_position_string_returns_correct_format()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();

        $grenadeEvent = GrenadeEvent::create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'player_x' => 100.123456,
            'player_y' => 200.789012,
            'player_z' => 50.345678,
            'player_aim_x' => 0.0,
            'player_aim_y' => 45.0,
            'player_aim_z' => 0.0,
            'grenade_type' => GrenadeType::FLASHBANG,
            'round_number' => 1,
            'round_time' => 120,
            'tick_timestamp' => 12345,
            'player_side' => 'CT',
            'grenade_final_x' => 150.0,
            'grenade_final_y' => 250.0,
            'grenade_final_z' => 60.0,
            'damage_dealt' => 0,
            'team_damage_dealt' => 0,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 2.5,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 2,
            'throw_type' => 'overhand',
            'effectiveness_rating' => 8,
            'flash_leads_to_kill' => true,
            'flash_leads_to_death' => false,
            'smoke_blocking_duration' => 0,
        ]);

        $positionString = $grenadeEvent->generatePositionString();

        $expectedString = 'setpos 100.123456 200.789012 50.345678;setang 45.000000 0.000000 0.000000';
        $this->assertEquals($expectedString, $positionString);
    }

    public function test_generate_position_string_handles_negative_coordinates()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();

        $grenadeEvent = GrenadeEvent::create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'player_x' => -100.123456,
            'player_y' => -200.789012,
            'player_z' => -50.345678,
            'player_aim_x' => 0.0,
            'player_aim_y' => -45.0,
            'player_aim_z' => 0.0,
            'grenade_type' => GrenadeType::FLASHBANG,
            'round_number' => 1,
            'round_time' => 120,
            'tick_timestamp' => 12345,
            'player_side' => 'CT',
            'grenade_final_x' => 150.0,
            'grenade_final_y' => 250.0,
            'grenade_final_z' => 60.0,
            'damage_dealt' => 0,
            'team_damage_dealt' => 0,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 2.5,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 2,
            'throw_type' => 'overhand',
            'effectiveness_rating' => 8,
            'flash_leads_to_kill' => true,
            'flash_leads_to_death' => false,
            'smoke_blocking_duration' => 0,
        ]);

        $positionString = $grenadeEvent->generatePositionString();

        $expectedString = 'setpos -100.123456 -200.789012 -50.345678;setang -45.000000 0.000000 0.000000';
        $this->assertEquals($expectedString, $positionString);
    }

    public function test_generate_position_string_handles_zero_coordinates()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();

        $grenadeEvent = GrenadeEvent::create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'player_x' => 0.0,
            'player_y' => 0.0,
            'player_z' => 0.0,
            'player_aim_x' => 0.0,
            'player_aim_y' => 0.0,
            'player_aim_z' => 0.0,
            'grenade_type' => GrenadeType::FLASHBANG,
            'round_number' => 1,
            'round_time' => 120,
            'tick_timestamp' => 12345,
            'player_side' => 'CT',
            'grenade_final_x' => 150.0,
            'grenade_final_y' => 250.0,
            'grenade_final_z' => 60.0,
            'damage_dealt' => 0,
            'team_damage_dealt' => 0,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 2.5,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 2,
            'throw_type' => 'overhand',
            'effectiveness_rating' => 8,
            'flash_leads_to_kill' => true,
            'flash_leads_to_death' => false,
            'smoke_blocking_duration' => 0,
        ]);

        $positionString = $grenadeEvent->generatePositionString();

        $expectedString = 'setpos 0.000000 0.000000 0.000000;setang 0.000000 0.000000 0.000000';
        $this->assertEquals($expectedString, $positionString);
    }
}
