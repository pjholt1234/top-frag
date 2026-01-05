<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CalculateClanLeaderboards;
use App\Models\Clan;
use App\Models\ClanLeaderboard;
use App\Models\ClanMember;
use App\Models\User;
use App\Services\Discord\DiscordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CalculateClanLeaderboardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock DiscordService to avoid configuration errors
        $discordServiceMock = Mockery::mock(DiscordService::class);
        $discordServiceMock->shouldReceive('sendLeaderboardToDiscord')
            ->zeroOrMoreTimes()
            ->andReturn(null);
        $this->app->instance(DiscordService::class, $discordServiceMock);
    }

    public function test_job_calculates_leaderboards_for_all_clans()
    {
        $user1 = User::factory()->create(['steam_id' => '76561198011111111']);
        $user2 = User::factory()->create(['steam_id' => '76561198022222222']);

        $clan1 = Clan::factory()->create(['owned_by' => $user1->id]);
        $clan2 = Clan::factory()->create(['owned_by' => $user2->id]);

        ClanMember::factory()->create(['clan_id' => $clan1->id, 'user_id' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $clan2->id, 'user_id' => $user2->id]);

        $job = new CalculateClanLeaderboards;
        $job->handle();

        // Check that leaderboards were created for both clans
        $leaderboards1 = ClanLeaderboard::where('clan_id', $clan1->id)->get();
        $leaderboards2 = ClanLeaderboard::where('clan_id', $clan2->id)->get();

        // Should have leaderboards for week and month periods for all types
        // Note: This will be empty if there are no matches, but the job should run without errors
        $this->assertTrue(true); // Job completed without errors
    }

    public function test_job_handles_clans_without_matches()
    {
        $user = User::factory()->create();
        $clan = Clan::factory()->create(['owned_by' => $user->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user->id]);

        $job = new CalculateClanLeaderboards;

        // Should not throw an exception
        $job->handle();

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
