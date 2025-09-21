<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessUserSharecodesJob;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\SteamAPIConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessUserSharecodesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handles_user_with_no_sharecodes(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_sharecode' => 'CSGO-test-sharecode',
            'steam_game_auth_code' => 'test-auth-code',
        ]);

        $steamApiMock = $this->mock(SteamAPIConnector::class);
        $steamApiMock->shouldReceive('getNextMatchSharingCode')
            ->once()
            ->andReturn(null);

        $job = new ProcessUserSharecodesJob($user->id);
        $job->handle();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'steam_last_processed_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_handles_existing_sharecode(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_sharecode' => 'CSGO-test-sharecode',
            'steam_game_auth_code' => 'test-auth-code',
        ]);

        $existingSharecode = 'CSGO-existing-sharecode';
        GameMatch::factory()->create(['sharecode' => $existingSharecode]);

        $steamApiMock = $this->mock(SteamAPIConnector::class);
        $steamApiMock->shouldReceive('getNextMatchSharingCode')
            ->once()
            ->andReturn($existingSharecode);
        $steamApiMock->shouldReceive('getNextMatchSharingCode')
            ->once()
            ->andReturn(null);

        $job = new ProcessUserSharecodesJob($user->id);
        $job->handle();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'steam_sharecode' => $existingSharecode,
        ]);
    }

    public function test_dispatches_download_job_for_new_sharecode(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_sharecode' => 'CSGO-test-sharecode',
            'steam_game_auth_code' => 'test-auth-code',
        ]);

        $newSharecode = 'CSGO-new-sharecode';

        $steamApiMock = $this->mock(SteamAPIConnector::class);
        $steamApiMock->shouldReceive('getNextMatchSharingCode')
            ->once()
            ->andReturn($newSharecode);
        $steamApiMock->shouldReceive('getNextMatchSharingCode')
            ->once()
            ->andReturn(null);

        $job = new ProcessUserSharecodesJob($user->id);
        $job->handle();

        Queue::assertPushed(\App\Jobs\DownloadDemoJob::class, 1);
    }

    public function test_respects_max_sharecodes_per_job_limit(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_sharecode' => 'CSGO-test-sharecode',
            'steam_game_auth_code' => 'test-auth-code',
        ]);

        $steamApiMock = $this->mock(SteamAPIConnector::class);

        // Mock 15 sharecodes (more than the default limit of 10)
        for ($i = 0; $i < 15; $i++) {
            $steamApiMock->shouldReceive('getNextMatchSharingCode')
                ->andReturn("CSGO-sharecode-{$i}");
        }

        $job = new ProcessUserSharecodesJob($user->id);
        $job->handle();

        // Should only dispatch 10 jobs (the limit)
        Queue::assertPushed(\App\Jobs\DownloadDemoJob::class, 10);
    }
}
