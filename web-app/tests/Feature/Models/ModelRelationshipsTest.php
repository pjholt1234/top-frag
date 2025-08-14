<?php

namespace Tests\Feature\Models;

use App\Enums\GrenadeType;
use App\Enums\MatchType;
use App\Enums\Team;
use App\Enums\ThrowType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\GunfightEvent;
use App\Models\MatchPlayer;
use App\Models\MatchSummary;
use App\Models\Player;
use App\Models\PlayerMatchSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_complete_match_with_all_relationships()
    {
        // Create a match
        $match = GameMatch::factory()->create([
            'match_hash' => 'test_match_123',
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => MatchType::MATCHMAKING,
        ]);

        // Create players
        $player1 = Player::factory()->create(['steam_id' => 'STEAM_1', 'name' => 'Player1']);
        $player2 = Player::factory()->create(['steam_id' => 'STEAM_2', 'name' => 'Player2']);
        $player3 = Player::factory()->create(['steam_id' => 'STEAM_3', 'name' => 'Player3']);

        // Create pivot records
        $matchPlayer1 = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player1->id,
            'team' => Team::TEAM_A,
        ]);
        $matchPlayer2 = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player2->id,
            'team' => Team::TEAM_B,
        ]);
        $matchPlayer3 = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player3->id,
            'team' => Team::TEAM_A,
        ]);

        // Create gunfight events
        $gunfightEvent1 = GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $player1->steam_id,
            'player_2_steam_id' => $player2->steam_id,
            'victor_steam_id' => $player1->steam_id,
            'headshot' => true,
            'damage_dealt' => 100,
        ]);
        $gunfightEvent2 = GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $player2->steam_id,
            'player_2_steam_id' => $player3->steam_id,
            'victor_steam_id' => $player2->steam_id,
            'headshot' => false,
            'damage_dealt' => 75,
        ]);

        // Create grenade events
        $grenadeEvent1 = GrenadeEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player1->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
            'throw_type' => ThrowType::LINEUP,
            'damage_dealt' => 25,
            'flash_duration' => 2.5,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 2.5,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 2,
        ]);
        $grenadeEvent2 = GrenadeEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player2->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'throw_type' => ThrowType::UTILITY,
            'damage_dealt' => 0,
            'flash_duration' => 0.0,
            'friendly_flash_duration' => 0.0,
            'enemy_flash_duration' => 0.0,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 0,
        ]);

        // Create match summary
        $matchSummary = MatchSummary::factory()->create([
            'match_id' => $match->id,
            'total_kills' => 30,
            'total_deaths' => 30,
            'total_assists' => 15,
            'total_headshots' => 12,
            'total_wallbangs' => 3,
            'total_damage' => 5000,
            'total_he_damage' => 500,
            'total_effective_flashes' => 8,
            'total_smokes_used' => 6,
            'total_molotovs_used' => 4,
            'total_first_kills' => 5,
            'total_first_deaths' => 5,
            'total_clutches_1v1_attempted' => 3,
            'total_clutches_1v1_successful' => 2,
            'total_clutches_1v2_attempted' => 2,
            'total_clutches_1v2_successful' => 1,
            'total_clutches_1v3_attempted' => 1,
            'total_clutches_1v3_successful' => 0,
            'total_clutches_1v4_attempted' => 0,
            'total_clutches_1v4_successful' => 0,
            'total_clutches_1v5_attempted' => 0,
            'total_clutches_1v5_successful' => 0,
        ]);

        // Create player match summaries
        $playerSummary1 = PlayerMatchSummary::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player1->id,
            'kills' => 15,
            'deaths' => 10,
            'assists' => 5,
            'headshots' => 8,
            'wallbangs' => 2,
            'first_kills' => 3,
            'first_deaths' => 2,
            'total_damage' => 2500,
            'average_damage_per_round' => 83.33,
            'damage_taken' => 2000,
            'he_damage' => 200,
            'effective_flashes' => 4,
            'smokes_used' => 3,
            'molotovs_used' => 2,
            'flashbangs_used' => 5,
            'clutches_1v1_attempted' => 2,
            'clutches_1v1_successful' => 1,
            'clutches_1v2_attempted' => 1,
            'clutches_1v2_successful' => 0,
            'clutches_1v3_attempted' => 0,
            'clutches_1v3_successful' => 0,
            'clutches_1v4_attempted' => 0,
            'clutches_1v4_successful' => 0,
            'clutches_1v5_attempted' => 0,
            'clutches_1v5_successful' => 0,
            'kd_ratio' => 1.5,
            'headshot_percentage' => 53.33,
            'clutch_success_rate' => 33.33,
        ]);

        $playerSummary2 = PlayerMatchSummary::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player2->id,
            'kills' => 10,
            'deaths' => 12,
            'assists' => 6,
            'headshots' => 3,
            'wallbangs' => 1,
            'first_kills' => 2,
            'first_deaths' => 3,
            'total_damage' => 2000,
            'average_damage_per_round' => 66.67,
            'damage_taken' => 2400,
            'he_damage' => 150,
            'effective_flashes' => 2,
            'smokes_used' => 2,
            'molotovs_used' => 1,
            'flashbangs_used' => 3,
            'clutches_1v1_attempted' => 1,
            'clutches_1v1_successful' => 1,
            'clutches_1v2_attempted' => 1,
            'clutches_1v2_successful' => 1,
            'clutches_1v3_attempted' => 1,
            'clutches_1v3_successful' => 0,
            'clutches_1v4_attempted' => 0,
            'clutches_1v4_successful' => 0,
            'clutches_1v5_attempted' => 0,
            'clutches_1v5_successful' => 0,
            'kd_ratio' => 0.83,
            'headshot_percentage' => 30.0,
            'clutch_success_rate' => 66.67,
        ]);

        // Test relationships from match perspective
        $this->assertCount(3, $match->matchPlayers);
        $this->assertCount(3, $match->players);
        $this->assertCount(2, $match->gunfightEvents);
        $this->assertCount(2, $match->grenadeEvents);
        $this->assertInstanceOf(MatchSummary::class, $match->matchSummary);
        $this->assertCount(2, $match->playerMatchSummaries);

        // Test relationships from player perspective
        $this->assertCount(1, $player1->matchPlayers);
        $this->assertCount(1, $player1->matches);
        $this->assertCount(1, $player1->gunfightEventsAsPlayer1);
        $this->assertCount(0, $player1->gunfightEventsAsPlayer2);
        $this->assertCount(1, $player1->gunfightEventsAsVictor);
        $this->assertCount(1, $player1->grenadeEvents);
        $this->assertCount(1, $player1->playerMatchSummaries);

        // Test relationships from match player perspective
        $this->assertInstanceOf(GameMatch::class, $matchPlayer1->match);
        $this->assertInstanceOf(Player::class, $matchPlayer1->player);
        $this->assertEquals($match->id, $matchPlayer1->match->id);
        $this->assertEquals($player1->id, $matchPlayer1->player->id);

        // Test relationships from gunfight event perspective
        $this->assertInstanceOf(GameMatch::class, $gunfightEvent1->match);
        $this->assertInstanceOf(Player::class, $gunfightEvent1->player1);
        $this->assertInstanceOf(Player::class, $gunfightEvent1->player2);
        $this->assertInstanceOf(Player::class, $gunfightEvent1->victor);
        $this->assertEquals($match->id, $gunfightEvent1->match->id);
        $this->assertEquals($player1->id, $gunfightEvent1->player1->id);
        $this->assertEquals($player2->id, $gunfightEvent1->player2->id);
        $this->assertEquals($player1->id, $gunfightEvent1->victor->id);

        // Test relationships from grenade event perspective
        $this->assertInstanceOf(GameMatch::class, $grenadeEvent1->match);
        $this->assertInstanceOf(Player::class, $grenadeEvent1->player);
        $this->assertEquals($match->id, $grenadeEvent1->match->id);
        $this->assertEquals($player1->id, $grenadeEvent1->player->id);

        // Test relationships from match summary perspective
        $this->assertInstanceOf(GameMatch::class, $matchSummary->match);
        $this->assertEquals($match->id, $matchSummary->match->id);

        // Test relationships from player match summary perspective
        $this->assertInstanceOf(GameMatch::class, $playerSummary1->match);
        $this->assertInstanceOf(Player::class, $playerSummary1->player);
        $this->assertEquals($match->id, $playerSummary1->match->id);
        $this->assertEquals($player1->id, $playerSummary1->player->id);

        // Test pivot data
        $this->assertEquals('A', $match->players->first()->pivot->team);
    }

    #[Test]
    public function it_can_query_relationships_efficiently()
    {
        // Create multiple matches and players to test relationship queries
        $match1 = GameMatch::factory()->create(['match_hash' => 'match1']);
        $match2 = GameMatch::factory()->create(['match_hash' => 'match2']);

        $player1 = Player::factory()->create(['steam_id' => 'STEAM_1']);
        $player2 = Player::factory()->create(['steam_id' => 'STEAM_2']);

        // Create relationships
        MatchPlayer::factory()->create([
            'match_id' => $match1->id,
            'player_id' => $player1->id,
            'team' => Team::TEAM_A,
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match1->id,
            'player_id' => $player2->id,
            'team' => Team::TEAM_B,
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match2->id,
            'player_id' => $player1->id,
            'team' => Team::TEAM_B,
        ]);

        // Test eager loading
        $matchesWithPlayers = GameMatch::with('players')->get();
        $this->assertCount(2, $matchesWithPlayers);
        $this->assertTrue($matchesWithPlayers->first()->relationLoaded('players'));

        $playersWithMatches = Player::with('matches')->get();
        $this->assertCount(2, $playersWithMatches);
        $this->assertTrue($playersWithMatches->first()->relationLoaded('matches'));

        // Test whereHas queries
        $matchesWithTerrorists = GameMatch::whereHas('players', function ($query) {
            $query->where('team', Team::TEAM_A);
        })->get();
        $this->assertCount(1, $matchesWithTerrorists);
        $this->assertEquals('match1', $matchesWithTerrorists->first()->match_hash);

        $playersInMatch1 = Player::whereHas('matches', function ($query) use ($match1) {
            $query->where('match_id', $match1->id);
        })->get();
        $this->assertCount(2, $playersInMatch1);
    }

    #[Test]
    public function it_can_handle_complex_relationship_queries()
    {
        // Create test data
        $match = GameMatch::factory()->create();
        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();

        // Create gunfight events
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $player1->steam_id,
            'player_2_steam_id' => $player2->steam_id,
            'victor_steam_id' => $player1->steam_id,
            'headshot' => true,
        ]);
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $player2->steam_id,
            'player_2_steam_id' => $player1->steam_id,
            'victor_steam_id' => $player2->steam_id,
            'headshot' => false,
        ]);

        // Create grenade events
        GrenadeEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player1->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
        ]);
        GrenadeEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player2->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        // Test complex queries
        $playerWithHeadshots = Player::whereHas('gunfightEventsAsVictor', function ($query) {
            $query->where('headshot', true);
        })->get();
        $this->assertCount(1, $playerWithHeadshots);
        $this->assertEquals($player1->id, $playerWithHeadshots->first()->id);

        $playerWithFlashbangs = Player::whereHas('grenadeEvents', function ($query) {
            $query->where('grenade_type', GrenadeType::FLASHBANG);
        })->get();
        $this->assertCount(1, $playerWithFlashbangs);
        $this->assertEquals($player1->id, $playerWithFlashbangs->first()->id);

        $matchWithHeadshots = GameMatch::whereHas('gunfightEvents', function ($query) {
            $query->where('headshot', true);
        })->get();
        $this->assertCount(1, $matchWithHeadshots);
        $this->assertEquals($match->id, $matchWithHeadshots->first()->id);
    }
}
