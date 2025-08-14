<?php

namespace Tests\Feature\Models;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerMatchSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_player_match_summary()
    {
        $summary = PlayerMatchSummary::factory()->create([
            'kills' => 25,
            'deaths' => 20,
            'assists' => 8,
            'headshots' => 12,
            'wallbangs' => 3,
            'first_kills' => 5,
            'first_deaths' => 4,
            'total_damage' => 3500,
            'average_damage_per_round' => 116.67,
            'damage_taken' => 2800,
            'he_damage' => 400,
            'effective_flashes' => 6,
            'smokes_used' => 5,
            'molotovs_used' => 4,
            'flashbangs_used' => 8,
            'clutches_1v1_attempted' => 2,
            'clutches_1v1_successful' => 1,
            'clutches_1v2_attempted' => 1,
            'clutches_1v2_successful' => 0,
            'clutches_1v3_attempted' => 1,
            'clutches_1v3_successful' => 1,
            'clutches_1v4_attempted' => 0,
            'clutches_1v4_successful' => 0,
            'clutches_1v5_attempted' => 0,
            'clutches_1v5_successful' => 0,
            'kd_ratio' => 1.25,
            'headshot_percentage' => 48.0,
            'clutch_success_rate' => 50.0,
        ]);

        $this->assertInstanceOf(PlayerMatchSummary::class, $summary);
        $this->assertEquals(25, $summary->kills);
        $this->assertEquals(20, $summary->deaths);
        $this->assertEquals(8, $summary->assists);
        $this->assertEquals(12, $summary->headshots);
        $this->assertEquals(3, $summary->wallbangs);
        $this->assertEquals(5, $summary->first_kills);
        $this->assertEquals(4, $summary->first_deaths);
        $this->assertEquals(3500, $summary->total_damage);
        $this->assertEquals(116.67, $summary->average_damage_per_round);
        $this->assertEquals(2800, $summary->damage_taken);
        $this->assertEquals(400, $summary->he_damage);
        $this->assertEquals(6, $summary->effective_flashes);
        $this->assertEquals(5, $summary->smokes_used);
        $this->assertEquals(4, $summary->molotovs_used);
        $this->assertEquals(8, $summary->flashbangs_used);
        $this->assertEquals(2, $summary->clutches_1v1_attempted);
        $this->assertEquals(1, $summary->clutches_1v1_successful);
        $this->assertEquals(1, $summary->clutches_1v2_attempted);
        $this->assertEquals(0, $summary->clutches_1v2_successful);
        $this->assertEquals(1, $summary->clutches_1v3_attempted);
        $this->assertEquals(1, $summary->clutches_1v3_successful);
        $this->assertEquals(0, $summary->clutches_1v4_attempted);
        $this->assertEquals(0, $summary->clutches_1v4_successful);
        $this->assertEquals(0, $summary->clutches_1v5_attempted);
        $this->assertEquals(0, $summary->clutches_1v5_successful);
        $this->assertEquals(1.25, $summary->kd_ratio);
        $this->assertEquals(48.0, $summary->headshot_percentage);
        $this->assertEquals(50.0, $summary->clutch_success_rate);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $summary = new PlayerMatchSummary;

        $expectedFillable = [
            'match_id',
            'player_id',
            'kills',
            'deaths',
            'assists',
            'headshots',
            'wallbangs',
            'first_kills',
            'first_deaths',
            'total_damage',
            'average_damage_per_round',
            'damage_taken',
            'he_damage',
            'effective_flashes',
            'smokes_used',
            'molotovs_used',
            'flashbangs_used',
            'clutches_1v1_attempted',
            'clutches_1v1_successful',
            'clutches_1v2_attempted',
            'clutches_1v2_successful',
            'clutches_1v3_attempted',
            'clutches_1v3_successful',
            'clutches_1v4_attempted',
            'clutches_1v4_successful',
            'clutches_1v5_attempted',
            'clutches_1v5_successful',
            'kd_ratio',
            'headshot_percentage',
            'clutch_success_rate',
        ];
        $this->assertEquals($expectedFillable, $summary->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $summary = PlayerMatchSummary::factory()->create([
            'kills' => 15,
            'deaths' => 12,
            'assists' => 5,
            'headshots' => 8,
            'wallbangs' => 2,
            'first_kills' => 3,
            'first_deaths' => 2,
            'total_damage' => 2500,
            'average_damage_per_round' => 83.33,
            'damage_taken' => 2000,
            'he_damage' => 300,
            'effective_flashes' => 4,
            'smokes_used' => 3,
            'molotovs_used' => 2,
            'flashbangs_used' => 6,
            'clutches_1v1_attempted' => 1,
            'clutches_1v1_successful' => 1,
            'clutches_1v2_attempted' => 1,
            'clutches_1v2_successful' => 0,
            'clutches_1v3_attempted' => 0,
            'clutches_1v3_successful' => 0,
            'clutches_1v4_attempted' => 0,
            'clutches_1v4_successful' => 0,
            'clutches_1v5_attempted' => 0,
            'clutches_1v5_successful' => 0,
            'kd_ratio' => 1.25,
            'headshot_percentage' => 53.33,
            'clutch_success_rate' => 50.0,
        ]);

        $this->assertIsInt($summary->kills);
        $this->assertIsInt($summary->deaths);
        $this->assertIsInt($summary->assists);
        $this->assertIsInt($summary->headshots);
        $this->assertIsInt($summary->wallbangs);
        $this->assertIsInt($summary->first_kills);
        $this->assertIsInt($summary->first_deaths);
        $this->assertIsInt($summary->total_damage);
        $this->assertIsFloat($summary->average_damage_per_round);
        $this->assertIsInt($summary->damage_taken);
        $this->assertIsInt($summary->he_damage);
        $this->assertIsInt($summary->effective_flashes);
        $this->assertIsInt($summary->smokes_used);
        $this->assertIsInt($summary->molotovs_used);
        $this->assertIsInt($summary->flashbangs_used);
        $this->assertIsInt($summary->clutches_1v1_attempted);
        $this->assertIsInt($summary->clutches_1v1_successful);
        $this->assertIsInt($summary->clutches_1v2_attempted);
        $this->assertIsInt($summary->clutches_1v2_successful);
        $this->assertIsInt($summary->clutches_1v3_attempted);
        $this->assertIsInt($summary->clutches_1v3_successful);
        $this->assertIsInt($summary->clutches_1v4_attempted);
        $this->assertIsInt($summary->clutches_1v4_successful);
        $this->assertIsInt($summary->clutches_1v5_attempted);
        $this->assertIsInt($summary->clutches_1v5_successful);
        $this->assertIsFloat($summary->kd_ratio);
        $this->assertIsFloat($summary->headshot_percentage);
        $this->assertIsFloat($summary->clutch_success_rate);
    }

    #[Test]
    public function it_belongs_to_match()
    {
        $match = GameMatch::factory()->create();
        $summary = PlayerMatchSummary::factory()->create(['match_id' => $match->id]);

        $this->assertInstanceOf(GameMatch::class, $summary->match);
        $this->assertEquals($match->id, $summary->match->id);
    }

    #[Test]
    public function it_belongs_to_player()
    {
        $player = Player::factory()->create();
        $summary = PlayerMatchSummary::factory()->create(['player_id' => $player->id]);

        $this->assertInstanceOf(Player::class, $summary->player);
        $this->assertEquals($player->id, $summary->player->id);
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $summary = new PlayerMatchSummary;
        $this->assertEquals('player_match_summaries', $summary->getTable());
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $summary = PlayerMatchSummary::factory()->create();

        $this->assertDatabaseHas('player_match_summaries', [
            'id' => $summary->id,
            'match_id' => $summary->match_id,
            'player_id' => $summary->player_id,
        ]);
    }

    #[Test]
    public function it_can_have_zero_values()
    {
        $summary = PlayerMatchSummary::factory()->create([
            'kills' => 0,
            'deaths' => 0,
            'assists' => 0,
            'headshots' => 0,
            'wallbangs' => 0,
            'first_kills' => 0,
            'first_deaths' => 0,
            'total_damage' => 0,
            'average_damage_per_round' => 0.0,
            'damage_taken' => 0,
            'he_damage' => 0,
            'effective_flashes' => 0,
            'smokes_used' => 0,
            'molotovs_used' => 0,
            'flashbangs_used' => 0,
            'clutches_1v1_attempted' => 0,
            'clutches_1v1_successful' => 0,
            'clutches_1v2_attempted' => 0,
            'clutches_1v2_successful' => 0,
            'clutches_1v3_attempted' => 0,
            'clutches_1v3_successful' => 0,
            'clutches_1v4_attempted' => 0,
            'clutches_1v4_successful' => 0,
            'clutches_1v5_attempted' => 0,
            'clutches_1v5_successful' => 0,
            'kd_ratio' => 0.0,
            'headshot_percentage' => 0.0,
            'clutch_success_rate' => 0.0,
        ]);

        $this->assertEquals(0, $summary->kills);
        $this->assertEquals(0, $summary->deaths);
        $this->assertEquals(0, $summary->assists);
        $this->assertEquals(0, $summary->headshots);
        $this->assertEquals(0, $summary->wallbangs);
        $this->assertEquals(0, $summary->first_kills);
        $this->assertEquals(0, $summary->first_deaths);
        $this->assertEquals(0, $summary->total_damage);
        $this->assertEquals(0.0, $summary->average_damage_per_round);
        $this->assertEquals(0, $summary->damage_taken);
        $this->assertEquals(0, $summary->he_damage);
        $this->assertEquals(0, $summary->effective_flashes);
        $this->assertEquals(0, $summary->smokes_used);
        $this->assertEquals(0, $summary->molotovs_used);
        $this->assertEquals(0, $summary->flashbangs_used);
        $this->assertEquals(0, $summary->clutches_1v1_attempted);
        $this->assertEquals(0, $summary->clutches_1v1_successful);
        $this->assertEquals(0, $summary->clutches_1v2_attempted);
        $this->assertEquals(0, $summary->clutches_1v2_successful);
        $this->assertEquals(0, $summary->clutches_1v3_attempted);
        $this->assertEquals(0, $summary->clutches_1v3_successful);
        $this->assertEquals(0, $summary->clutches_1v4_attempted);
        $this->assertEquals(0, $summary->clutches_1v4_successful);
        $this->assertEquals(0, $summary->clutches_1v5_attempted);
        $this->assertEquals(0, $summary->clutches_1v5_successful);
        $this->assertEquals(0.0, $summary->kd_ratio);
        $this->assertEquals(0.0, $summary->headshot_percentage);
        $this->assertEquals(0.0, $summary->clutch_success_rate);
    }
}
