<?php

namespace Tests\Feature\Models;

use App\Models\MatchPlayer;
use App\Models\GameMatch;
use App\Models\Player;
use App\Enums\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MatchPlayerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_match_player()
    {
        $matchPlayer = MatchPlayer::factory()->create([
            'team' => Team::TEAM_A,
        ]);

        $this->assertInstanceOf(MatchPlayer::class, $matchPlayer);
        $this->assertEquals(Team::TEAM_A, $matchPlayer->team);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $matchPlayer = new MatchPlayer();

        $expectedFillable = ['match_id', 'player_id', 'team'];
        $this->assertEquals($expectedFillable, $matchPlayer->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $matchPlayer = MatchPlayer::factory()->create([
            'team' => Team::TEAM_B,
        ]);

        $this->assertInstanceOf(Team::class, $matchPlayer->team);
        $this->assertEquals(Team::TEAM_B, $matchPlayer->team);
    }

    #[Test]
    public function it_belongs_to_match()
    {
        $match = GameMatch::factory()->create();
        $matchPlayer = MatchPlayer::factory()->create(['match_id' => $match->id]);

        $this->assertInstanceOf(GameMatch::class, $matchPlayer->match);
        $this->assertEquals($match->id, $matchPlayer->match->id);
    }

    #[Test]
    public function it_belongs_to_player()
    {
        $player = Player::factory()->create();
        $matchPlayer = MatchPlayer::factory()->create(['player_id' => $player->id]);

        $this->assertInstanceOf(Player::class, $matchPlayer->player);
        $this->assertEquals($player->id, $matchPlayer->player->id);
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $matchPlayer = new MatchPlayer();
        $this->assertEquals('match_players', $matchPlayer->getTable());
    }

    #[Test]
    public function it_can_be_created_with_valid_data()
    {
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();

        $matchPlayer = MatchPlayer::create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => Team::TEAM_A,
        ]);

        $this->assertDatabaseHas('match_players', [
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => Team::TEAM_A->value,
        ]);
    }

    #[Test]
    public function it_has_correct_fillable_fields()
    {
        $matchPlayer = new MatchPlayer();
        $expectedFillable = ['match_id', 'player_id', 'team'];
        $this->assertEquals($expectedFillable, $matchPlayer->getFillable());
    }

    #[Test]
    public function it_casts_team_to_enum()
    {
        $matchPlayer = MatchPlayer::factory()->create([
            'team' => Team::TEAM_A,
        ]);

        $this->assertInstanceOf(Team::class, $matchPlayer->team);
        $this->assertEquals(Team::TEAM_A, $matchPlayer->team);
    }

    #[Test]
    public function it_can_have_different_team_values()
    {
        $matchPlayer = MatchPlayer::factory()->create([
            'team' => Team::TEAM_A,
        ]);

        $this->assertEquals(Team::TEAM_A, $matchPlayer->team);
        $this->assertNotEquals(Team::TEAM_B, $matchPlayer->team);
    }
}
