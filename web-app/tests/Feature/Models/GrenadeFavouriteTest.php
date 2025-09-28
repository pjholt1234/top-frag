<?php

namespace Tests\Feature\Models;

use App\Models\GameMatch;
use App\Models\GrenadeFavourite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrenadeFavouriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_grenade_favourite()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $grenadeFavourite = GrenadeFavourite::create([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'round_number' => 1,
            'round_time' => 120,
            'tick_timestamp' => 12345,
            'player_steam_id' => '76561198000000001',
            'player_side' => 'CT',
            'grenade_type' => 'flashbang',
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
            'flash_duration' => 2.5,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 2.5,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 2,
            'throw_type' => 'overhand',
            'effectiveness_rating' => 8,
        ]);

        $this->assertDatabaseHas('grenade_favourites', [
            'id' => $grenadeFavourite->id,
            'match_id' => $match->id,
            'user_id' => $user->id,
            'player_steam_id' => '76561198000000001',
        ]);
    }

    public function test_it_has_fillable_attributes()
    {
        $grenadeFavourite = new GrenadeFavourite;
        $fillable = $grenadeFavourite->getFillable();

        $expectedFillable = [
            'match_id',
            'user_id',
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
            'flash_duration',
            'friendly_flash_duration',
            'enemy_flash_duration',
            'friendly_players_affected',
            'enemy_players_affected',
            'throw_type',
            'effectiveness_rating',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    public function test_it_belongs_to_match()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $grenadeFavourite = GrenadeFavourite::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(GameMatch::class, $grenadeFavourite->match);
        $this->assertEquals($match->id, $grenadeFavourite->match->id);
    }

    public function test_it_belongs_to_user()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $grenadeFavourite = GrenadeFavourite::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $grenadeFavourite->user);
        $this->assertEquals($user->id, $grenadeFavourite->user->id);
    }

    public function test_it_uses_correct_table_name()
    {
        $grenadeFavourite = new GrenadeFavourite;
        $this->assertEquals('grenade_favourites', $grenadeFavourite->getTable());
    }

    public function test_it_can_be_created_with_factory()
    {
        $grenadeFavourite = GrenadeFavourite::factory()->create();

        $this->assertDatabaseHas('grenade_favourites', [
            'id' => $grenadeFavourite->id,
        ]);
    }
}
