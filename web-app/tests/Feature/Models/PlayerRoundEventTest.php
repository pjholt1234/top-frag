<?php

namespace Tests\Feature\Models;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerRoundEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerRoundEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_player_round_event()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create(['steam_id' => 'STEAM_12345']);

        $playerRoundEvent = PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 1,
            'kills' => 2,
            'assists' => 1,
            'died' => false,
            'damage' => 150,
            'headshots' => 1,
            'first_kill' => true,
            'first_death' => false,
            'kills_with_awp' => 0,
            'damage_dealt' => 25,
            'flashes_thrown' => 1,
            'friendly_flash_duration' => 0.5,
            'enemy_flash_duration' => 2.1,
            'successful_trades' => 1,
            'total_possible_trades' => 2,
            'clutch_attempts_1v2' => 1,
            'clutch_wins_1v2' => 1,
            'time_to_contact' => 15.3,
            'is_eco' => false,
            'is_force_buy' => true,
            'is_full_buy' => false,
            'kills_vs_eco' => 1,
            'grenade_value_lost_on_death' => 0,
        ]);

        $this->assertInstanceOf(PlayerRoundEvent::class, $playerRoundEvent);
        $this->assertEquals($match->id, $playerRoundEvent->match_id);
        $this->assertEquals($player->steam_id, $playerRoundEvent->player_steam_id);
        $this->assertEquals(1, $playerRoundEvent->round_number);
        $this->assertEquals(2, $playerRoundEvent->kills);
        $this->assertEquals(1, $playerRoundEvent->assists);
        $this->assertFalse($playerRoundEvent->died);
        $this->assertEquals(150, $playerRoundEvent->damage);
        $this->assertEquals(1, $playerRoundEvent->headshots);
        $this->assertTrue($playerRoundEvent->first_kill);
        $this->assertFalse($playerRoundEvent->first_death);
        $this->assertEquals(0, $playerRoundEvent->kills_with_awp);
        $this->assertEquals(25, $playerRoundEvent->damage_dealt);
        $this->assertEquals(1, $playerRoundEvent->flashes_thrown);
        $this->assertEquals(0.5, $playerRoundEvent->friendly_flash_duration);
        $this->assertEquals(2.1, $playerRoundEvent->enemy_flash_duration);
        $this->assertEquals(1, $playerRoundEvent->successful_trades);
        $this->assertEquals(2, $playerRoundEvent->total_possible_trades);
        $this->assertEquals(1, $playerRoundEvent->clutch_attempts_1v2);
        $this->assertEquals(1, $playerRoundEvent->clutch_wins_1v2);
        $this->assertEquals(15.3, $playerRoundEvent->time_to_contact);
        $this->assertFalse($playerRoundEvent->is_eco);
        $this->assertTrue($playerRoundEvent->is_force_buy);
        $this->assertFalse($playerRoundEvent->is_full_buy);
        $this->assertEquals(1, $playerRoundEvent->kills_vs_eco);
        $this->assertEquals(0, $playerRoundEvent->grenade_value_lost_on_death);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $playerRoundEvent = new PlayerRoundEvent;
        $expectedFillable = [
            'match_id',
            'player_steam_id',
            'round_number',

            // Gun Fight fields
            'kills',
            'assists',
            'died',
            'damage',
            'headshots',
            'first_kill',
            'first_death',
            'round_time_of_death',
            'kills_with_awp',

            // Grenade fields
            'damage_dealt',
            'flashes_thrown',
            'friendly_flash_duration',
            'enemy_flash_duration',
            'friendly_players_affected',
            'enemy_players_affected',
            'flashes_leading_to_kill',
            'flashes_leading_to_death',
            'grenade_effectiveness',

            // Trade fields
            'successful_trades',
            'total_possible_trades',
            'successful_traded_deaths',
            'total_possible_traded_deaths',

            // Clutch fields
            'clutch_attempts_1v1',
            'clutch_attempts_1v2',
            'clutch_attempts_1v3',
            'clutch_attempts_1v4',
            'clutch_attempts_1v5',
            'clutch_wins_1v1',
            'clutch_wins_1v2',
            'clutch_wins_1v3',
            'clutch_wins_1v4',
            'clutch_wins_1v5',

            'time_to_contact',

            // Economy fields
            'is_eco',
            'is_force_buy',
            'is_full_buy',
            'kills_vs_eco',
            'kills_vs_force_buy',
            'kills_vs_full_buy',
            'grenade_value_lost_on_death',
        ];

        $this->assertEquals($expectedFillable, $playerRoundEvent->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $playerRoundEvent = PlayerRoundEvent::factory()->create([
            'died' => true,
            'first_kill' => true,
            'first_death' => false,
            'flashes_thrown' => 2,
            'friendly_flash_duration' => 0.567,
            'enemy_flash_duration' => 2.890,
            'grenade_effectiveness' => 0.7891,
            'time_to_contact' => 15.678,
            'is_eco' => true,
            'is_force_buy' => false,
            'is_full_buy' => true,
        ]);

        $this->assertIsBool($playerRoundEvent->died);
        $this->assertTrue($playerRoundEvent->died);

        $this->assertIsBool($playerRoundEvent->first_kill);
        $this->assertTrue($playerRoundEvent->first_kill);

        $this->assertIsBool($playerRoundEvent->first_death);
        $this->assertFalse($playerRoundEvent->first_death);

        $this->assertIsNumeric($playerRoundEvent->flashes_thrown);
        $this->assertEquals(2, (int)$playerRoundEvent->flashes_thrown);

        $this->assertIsNumeric($playerRoundEvent->friendly_flash_duration);
        $this->assertEquals(0.567, (float)$playerRoundEvent->friendly_flash_duration);

        $this->assertIsNumeric($playerRoundEvent->enemy_flash_duration);
        $this->assertEquals(2.890, (float)$playerRoundEvent->enemy_flash_duration);

        $this->assertIsNumeric($playerRoundEvent->grenade_effectiveness);
        $this->assertEquals(0.7891, (float)$playerRoundEvent->grenade_effectiveness);

        $this->assertIsNumeric($playerRoundEvent->time_to_contact);
        $this->assertEquals(15.678, (float)$playerRoundEvent->time_to_contact);

        $this->assertIsBool($playerRoundEvent->is_eco);
        $this->assertTrue($playerRoundEvent->is_eco);

        $this->assertIsBool($playerRoundEvent->is_force_buy);
        $this->assertFalse($playerRoundEvent->is_force_buy);

        $this->assertIsBool($playerRoundEvent->is_full_buy);
        $this->assertTrue($playerRoundEvent->is_full_buy);
    }

    #[Test]
    public function it_belongs_to_a_match()
    {
        $match = GameMatch::factory()->create();
        $playerRoundEvent = PlayerRoundEvent::factory()->create(['match_id' => $match->id]);

        $this->assertInstanceOf(GameMatch::class, $playerRoundEvent->match);
        $this->assertEquals($match->id, $playerRoundEvent->match->id);
    }

    #[Test]
    public function match_has_many_player_round_events()
    {
        $match = GameMatch::factory()->create();
        $playerRoundEvent1 = PlayerRoundEvent::factory()->create(['match_id' => $match->id]);
        $playerRoundEvent2 = PlayerRoundEvent::factory()->create(['match_id' => $match->id]);

        $this->assertCount(2, $match->playerRoundEvents);
        $this->assertInstanceOf(PlayerRoundEvent::class, $match->playerRoundEvents->first());
        $this->assertTrue($match->playerRoundEvents->contains($playerRoundEvent1));
        $this->assertTrue($match->playerRoundEvents->contains($playerRoundEvent2));
    }

    #[Test]
    public function player_has_many_player_round_events()
    {
        $player = Player::factory()->create(['steam_id' => 'STEAM_12345']);
        $playerRoundEvent1 = PlayerRoundEvent::factory()->create(['player_steam_id' => $player->steam_id]);
        $playerRoundEvent2 = PlayerRoundEvent::factory()->create(['player_steam_id' => $player->steam_id]);

        $this->assertCount(2, $player->playerRoundEvents);
        $this->assertInstanceOf(PlayerRoundEvent::class, $player->playerRoundEvents->first());
        $this->assertTrue($player->playerRoundEvents->contains($playerRoundEvent1));
        $this->assertTrue($player->playerRoundEvents->contains($playerRoundEvent2));
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $playerRoundEvent = PlayerRoundEvent::factory()->create();

        $this->assertDatabaseHas('player_round_events', [
            'id' => $playerRoundEvent->id,
            'match_id' => $playerRoundEvent->match_id,
            'player_steam_id' => $playerRoundEvent->player_steam_id,
            'round_number' => $playerRoundEvent->round_number,
        ]);
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $playerRoundEvent = new PlayerRoundEvent;
        $this->assertEquals('player_round_events', $playerRoundEvent->getTable());
    }

    #[Test]
    public function it_can_query_by_match_and_player()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create(['steam_id' => 'STEAM_12345']);
        $otherPlayer = Player::factory()->create(['steam_id' => 'STEAM_67890']);

        $playerRoundEvent1 = PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 1,
        ]);

        $playerRoundEvent2 = PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 2,
        ]);

        // Different player
        $otherPlayerRoundEvent = PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $otherPlayer->steam_id,
            'round_number' => 1,
        ]);

        $playerEvents = PlayerRoundEvent::where('match_id', $match->id)
            ->where('player_steam_id', $player->steam_id)
            ->get();

        $this->assertCount(2, $playerEvents);
        $this->assertTrue($playerEvents->contains($playerRoundEvent1));
        $this->assertTrue($playerEvents->contains($playerRoundEvent2));
        $this->assertFalse($playerEvents->contains($otherPlayerRoundEvent));
    }

    #[Test]
    public function it_can_query_by_round_number()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create(['steam_id' => 'STEAM_12345']);

        $round1Event = PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 1,
        ]);

        $round2Event = PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 2,
        ]);

        $round1Events = PlayerRoundEvent::where('match_id', $match->id)
            ->where('round_number', 1)
            ->get();

        $this->assertCount(1, $round1Events);
        $this->assertTrue($round1Events->contains($round1Event));
        $this->assertFalse($round1Events->contains($round2Event));
    }

    #[Test]
    public function it_handles_null_values_correctly()
    {
        $playerRoundEvent = PlayerRoundEvent::factory()->create([
            'round_time_of_death' => null,
        ]);

        $this->assertNull($playerRoundEvent->round_time_of_death);
    }

    #[Test]
    public function it_has_default_values_for_most_fields()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create(['steam_id' => 'STEAM_12345']);

        $playerRoundEvent = PlayerRoundEvent::create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 1,
        ]);

        // Check that defaults are applied via database defaults
        $this->assertEquals(0, $playerRoundEvent->kills);
        $this->assertEquals(0, $playerRoundEvent->assists);
        $this->assertEquals(0, $playerRoundEvent->died); // Database defaults to 0, not false
        $this->assertEquals(0, $playerRoundEvent->damage);
        $this->assertEquals(0, $playerRoundEvent->headshots);
        $this->assertEquals(0, $playerRoundEvent->first_kill); // Database defaults to 0, not false
        $this->assertEquals(0, $playerRoundEvent->first_death); // Database defaults to 0, not false
        $this->assertEquals(0, $playerRoundEvent->kills_with_awp);
        $this->assertEquals(0, $playerRoundEvent->damage_dealt);
        $this->assertEquals(0, $playerRoundEvent->flashes_thrown);
        $this->assertEquals(0.0, $playerRoundEvent->friendly_flash_duration);
        $this->assertEquals(0.0, $playerRoundEvent->enemy_flash_duration);
        $this->assertEquals(0, $playerRoundEvent->friendly_players_affected);
        $this->assertEquals(0, $playerRoundEvent->enemy_players_affected);
        $this->assertEquals(0, $playerRoundEvent->flashes_leading_to_kill);
        $this->assertEquals(0, $playerRoundEvent->flashes_leading_to_death);
        $this->assertEquals(0.0, $playerRoundEvent->grenade_effectiveness);
        $this->assertEquals(0, $playerRoundEvent->successful_trades);
        $this->assertEquals(0, $playerRoundEvent->total_possible_trades);
        $this->assertEquals(0, $playerRoundEvent->successful_traded_deaths);
        $this->assertEquals(0, $playerRoundEvent->total_possible_traded_deaths);
        $this->assertEquals(0, $playerRoundEvent->clutch_attempts_1v1);
        $this->assertEquals(0, $playerRoundEvent->clutch_wins_1v1);
        $this->assertEquals(0.0, $playerRoundEvent->time_to_contact);
        $this->assertEquals(0, $playerRoundEvent->is_eco); // Database defaults to 0, not false
        $this->assertEquals(0, $playerRoundEvent->is_force_buy); // Database defaults to 0, not false
        $this->assertEquals(0, $playerRoundEvent->is_full_buy); // Database defaults to 0, not false
        $this->assertEquals(0, $playerRoundEvent->kills_vs_eco);
        $this->assertEquals(0, $playerRoundEvent->kills_vs_force_buy);
        $this->assertEquals(0, $playerRoundEvent->kills_vs_full_buy);
        $this->assertEquals(0, $playerRoundEvent->grenade_value_lost_on_death);
    }

    #[Test]
    public function it_can_calculate_aggregated_stats_for_player_across_rounds()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create(['steam_id' => 'STEAM_12345']);

        // Create events for multiple rounds
        PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 1,
            'kills' => 2,
            'damage' => 150,
            'died' => false,
            'clutch_wins_1v1' => 1,
            'clutch_wins_1v2' => 0,
        ]);

        PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 2,
            'kills' => 1,
            'damage' => 75,
            'died' => false,
            'clutch_wins_1v1' => 0,
            'clutch_wins_1v2' => 1,
        ]);

        PlayerRoundEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 3,
            'kills' => 0,
            'damage' => 25,
            'died' => true,
            'clutch_wins_1v1' => 0,
            'clutch_wins_1v2' => 0,
        ]);

        $playerEvents = PlayerRoundEvent::where('match_id', $match->id)
            ->where('player_steam_id', $player->steam_id)
            ->get();

        $totalKills = $playerEvents->sum('kills');
        $totalDamage = $playerEvents->sum('damage');
        $totalDeaths = $playerEvents->where('died', true)->count();
        $totalClutchWins = $playerEvents->sum('clutch_wins_1v1') + $playerEvents->sum('clutch_wins_1v2');

        $this->assertEquals(3, $totalKills);
        $this->assertEquals(250, $totalDamage);
        $this->assertEquals(1, $totalDeaths);
        $this->assertEquals(2, $totalClutchWins);
    }
}
