<?php

namespace Tests\Unit\Models;

use App\Models\MatchSummary;
use App\Models\GameMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MatchSummaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_match_summary()
    {
        $summary = MatchSummary::factory()->create([
            'total_kills' => 150,
            'total_deaths' => 150,
            'total_assists' => 75,
            'total_headshots' => 45,
            'total_wallbangs' => 12,
            'total_damage' => 15000,
            'total_he_damage' => 2500,
            'total_effective_flashes' => 30,
            'total_smokes_used' => 25,
            'total_molotovs_used' => 20,
            'total_first_kills' => 15,
            'total_first_deaths' => 15,
            'total_clutches_1v1_attempted' => 8,
            'total_clutches_1v1_successful' => 5,
            'total_clutches_1v2_attempted' => 6,
            'total_clutches_1v2_successful' => 3,
            'total_clutches_1v3_attempted' => 4,
            'total_clutches_1v3_successful' => 2,
            'total_clutches_1v4_attempted' => 2,
            'total_clutches_1v4_successful' => 1,
            'total_clutches_1v5_attempted' => 1,
            'total_clutches_1v5_successful' => 0,
        ]);

        $this->assertInstanceOf(MatchSummary::class, $summary);
        $this->assertEquals(150, $summary->total_kills);
        $this->assertEquals(150, $summary->total_deaths);
        $this->assertEquals(75, $summary->total_assists);
        $this->assertEquals(45, $summary->total_headshots);
        $this->assertEquals(12, $summary->total_wallbangs);
        $this->assertEquals(15000, $summary->total_damage);
        $this->assertEquals(2500, $summary->total_he_damage);
        $this->assertEquals(30, $summary->total_effective_flashes);
        $this->assertEquals(25, $summary->total_smokes_used);
        $this->assertEquals(20, $summary->total_molotovs_used);
        $this->assertEquals(15, $summary->total_first_kills);
        $this->assertEquals(15, $summary->total_first_deaths);
        $this->assertEquals(8, $summary->total_clutches_1v1_attempted);
        $this->assertEquals(5, $summary->total_clutches_1v1_successful);
        $this->assertEquals(6, $summary->total_clutches_1v2_attempted);
        $this->assertEquals(3, $summary->total_clutches_1v2_successful);
        $this->assertEquals(4, $summary->total_clutches_1v3_attempted);
        $this->assertEquals(2, $summary->total_clutches_1v3_successful);
        $this->assertEquals(2, $summary->total_clutches_1v4_attempted);
        $this->assertEquals(1, $summary->total_clutches_1v4_successful);
        $this->assertEquals(1, $summary->total_clutches_1v5_attempted);
        $this->assertEquals(0, $summary->total_clutches_1v5_successful);
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $summary = new MatchSummary();

        $expectedFillable = [
            'match_id',
            'total_kills',
            'total_deaths',
            'total_assists',
            'total_headshots',
            'total_wallbangs',
            'total_damage',
            'total_he_damage',
            'total_effective_flashes',
            'total_smokes_used',
            'total_molotovs_used',
            'total_first_kills',
            'total_first_deaths',
            'total_clutches_1v1_attempted',
            'total_clutches_1v1_successful',
            'total_clutches_1v2_attempted',
            'total_clutches_1v2_successful',
            'total_clutches_1v3_attempted',
            'total_clutches_1v3_successful',
            'total_clutches_1v4_attempted',
            'total_clutches_1v4_successful',
            'total_clutches_1v5_attempted',
            'total_clutches_1v5_successful',
        ];
        $this->assertEquals($expectedFillable, $summary->getFillable());
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $summary = MatchSummary::factory()->create([
            'total_kills' => 100,
            'total_deaths' => 100,
            'total_assists' => 50,
            'total_headshots' => 30,
            'total_wallbangs' => 8,
            'total_damage' => 10000,
            'total_he_damage' => 2000,
            'total_effective_flashes' => 20,
            'total_smokes_used' => 15,
            'total_molotovs_used' => 10,
            'total_first_kills' => 10,
            'total_first_deaths' => 10,
            'total_clutches_1v1_attempted' => 5,
            'total_clutches_1v1_successful' => 3,
            'total_clutches_1v2_attempted' => 4,
            'total_clutches_1v2_successful' => 2,
            'total_clutches_1v3_attempted' => 3,
            'total_clutches_1v3_successful' => 1,
            'total_clutches_1v4_attempted' => 1,
            'total_clutches_1v4_successful' => 0,
            'total_clutches_1v5_attempted' => 0,
            'total_clutches_1v5_successful' => 0,
        ]);

        $this->assertIsInt($summary->total_kills);
        $this->assertIsInt($summary->total_deaths);
        $this->assertIsInt($summary->total_assists);
        $this->assertIsInt($summary->total_headshots);
        $this->assertIsInt($summary->total_wallbangs);
        $this->assertIsInt($summary->total_damage);
        $this->assertIsInt($summary->total_he_damage);
        $this->assertIsInt($summary->total_effective_flashes);
        $this->assertIsInt($summary->total_smokes_used);
        $this->assertIsInt($summary->total_molotovs_used);
        $this->assertIsInt($summary->total_first_kills);
        $this->assertIsInt($summary->total_first_deaths);
        $this->assertIsInt($summary->total_clutches_1v1_attempted);
        $this->assertIsInt($summary->total_clutches_1v1_successful);
        $this->assertIsInt($summary->total_clutches_1v2_attempted);
        $this->assertIsInt($summary->total_clutches_1v2_successful);
        $this->assertIsInt($summary->total_clutches_1v3_attempted);
        $this->assertIsInt($summary->total_clutches_1v3_successful);
        $this->assertIsInt($summary->total_clutches_1v4_attempted);
        $this->assertIsInt($summary->total_clutches_1v4_successful);
        $this->assertIsInt($summary->total_clutches_1v5_attempted);
        $this->assertIsInt($summary->total_clutches_1v5_successful);
    }

    #[Test]
    public function it_belongs_to_match()
    {
        $match = GameMatch::factory()->create();
        $summary = MatchSummary::factory()->create(['match_id' => $match->id]);

        $this->assertInstanceOf(GameMatch::class, $summary->match);
        $this->assertEquals($match->id, $summary->match->id);
    }

    #[Test]
    public function it_uses_correct_table_name()
    {
        $summary = new MatchSummary();
        $this->assertEquals('match_summaries', $summary->getTable());
    }

    #[Test]
    public function it_can_be_created_with_factory()
    {
        $summary = MatchSummary::factory()->create();

        $this->assertDatabaseHas('match_summaries', [
            'id' => $summary->id,
            'match_id' => $summary->match_id,
            'total_kills' => $summary->total_kills,
        ]);
    }

    #[Test]
    public function it_can_have_zero_values()
    {
        $summary = MatchSummary::factory()->create([
            'total_kills' => 0,
            'total_deaths' => 0,
            'total_assists' => 0,
            'total_headshots' => 0,
            'total_wallbangs' => 0,
            'total_damage' => 0,
            'total_he_damage' => 0,
            'total_effective_flashes' => 0,
            'total_smokes_used' => 0,
            'total_molotovs_used' => 0,
            'total_first_kills' => 0,
            'total_first_deaths' => 0,
            'total_clutches_1v1_attempted' => 0,
            'total_clutches_1v1_successful' => 0,
            'total_clutches_1v2_attempted' => 0,
            'total_clutches_1v2_successful' => 0,
            'total_clutches_1v3_attempted' => 0,
            'total_clutches_1v3_successful' => 0,
            'total_clutches_1v4_attempted' => 0,
            'total_clutches_1v4_successful' => 0,
            'total_clutches_1v5_attempted' => 0,
            'total_clutches_1v5_successful' => 0,
        ]);

        $this->assertEquals(0, $summary->total_kills);
        $this->assertEquals(0, $summary->total_deaths);
        $this->assertEquals(0, $summary->total_assists);
        $this->assertEquals(0, $summary->total_headshots);
        $this->assertEquals(0, $summary->total_wallbangs);
        $this->assertEquals(0, $summary->total_damage);
        $this->assertEquals(0, $summary->total_he_damage);
        $this->assertEquals(0, $summary->total_effective_flashes);
        $this->assertEquals(0, $summary->total_smokes_used);
        $this->assertEquals(0, $summary->total_molotovs_used);
        $this->assertEquals(0, $summary->total_first_kills);
        $this->assertEquals(0, $summary->total_first_deaths);
        $this->assertEquals(0, $summary->total_clutches_1v1_attempted);
        $this->assertEquals(0, $summary->total_clutches_1v1_successful);
        $this->assertEquals(0, $summary->total_clutches_1v2_attempted);
        $this->assertEquals(0, $summary->total_clutches_1v2_successful);
        $this->assertEquals(0, $summary->total_clutches_1v3_attempted);
        $this->assertEquals(0, $summary->total_clutches_1v3_successful);
        $this->assertEquals(0, $summary->total_clutches_1v4_attempted);
        $this->assertEquals(0, $summary->total_clutches_1v4_successful);
        $this->assertEquals(0, $summary->total_clutches_1v5_attempted);
        $this->assertEquals(0, $summary->total_clutches_1v5_successful);
    }
}
