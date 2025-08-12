<?php

namespace Tests\Unit\Models;

use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
        $matchPlayer = new MatchPlayer;

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
        $matchPlayer = new MatchPlayer;
        $this->assertEquals('match_players', $matchPlayer->getTable());
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $matchPlayer = MatchPlayer::factory()->create();

        $this->assertDatabaseHas('match_players', [
            'id' => $matchPlayer->id,
            'match_id' => $matchPlayer->match_id,
            'player_id' => $matchPlayer->player_id,
        ]);
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
