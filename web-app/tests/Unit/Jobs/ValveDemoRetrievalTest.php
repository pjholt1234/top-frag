<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ParseDemo;
use App\Jobs\ValveDemoRetrieval;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\DemoDownloadService;
use App\Services\ParserServiceConnector;
use App\Services\SteamApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ValveDemoRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private SteamApiService $steamApiService;

    private DemoDownloadService $demoDownloadService;

    private ParserServiceConnector $parserServiceConnector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->steamApiService = $this->createMock(SteamApiService::class);
        $this->demoDownloadService = $this->createMock(DemoDownloadService::class);
        $this->parserServiceConnector = $this->createMock(ParserServiceConnector::class);

        $this->app->instance(SteamApiService::class, $this->steamApiService);
        $this->app->instance(DemoDownloadService::class, $this->demoDownloadService);
        $this->app->instance(ParserServiceConnector::class, $this->parserServiceConnector);
    }

    public function test_handle_skips_processing_when_services_unhealthy(): void
    {
        $this->steamApiService->expects($this->once())
            ->method('checkServiceHealth')
            ->willReturn(false);

        $this->parserServiceConnector->expects($this->never())
            ->method('checkServiceHealth');

        $this->demoDownloadService->expects($this->never())
            ->method('cleanupOldTempFiles');

        $job = new ValveDemoRetrieval;
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_handle_processes_eligible_users(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_sharecode' => 'CSGO-12345-67890-ABCDE-FGHIJ-KLMNO',
            'steam_game_auth_code' => 'AAAA-AAAAA-AAAA',
            'steam_match_processing_enabled' => true,
            'steam_last_processed_at' => null,
        ]);

        $this->steamApiService->expects($this->once())
            ->method('checkServiceHealth')
            ->willReturn(true);

        $this->parserServiceConnector->expects($this->once())
            ->method('checkServiceHealth');

        $this->steamApiService->expects($this->once())
            ->method('getNextMatchSharingCode')
            ->with($user->steam_id, $user->steam_game_auth_code, $user->steam_sharecode)
            ->willReturn('CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY');

        $this->demoDownloadService->expects($this->once())
            ->method('cleanupOldTempFiles');

        $this->demoDownloadService->expects($this->once())
            ->method('downloadDemo')
            ->with('CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY')
            ->willReturn('/tmp/test_demo.dem');

        Queue::fake();

        $job = new ValveDemoRetrieval;
        $job->handle();

        Queue::assertPushed(ParseDemo::class);

        $this->assertDatabaseHas('matches', [
            'sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
            'uploaded_by' => $user->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'steam_last_processed_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_handle_skips_user_when_no_new_sharecode(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_sharecode' => 'CSGO-12345-67890-ABCDE-FGHIJ-KLMNO',
            'steam_game_auth_code' => 'AAAA-AAAAA-AAAA',
            'steam_match_processing_enabled' => true,
            'steam_last_processed_at' => null,
        ]);

        $this->steamApiService->expects($this->once())
            ->method('checkServiceHealth')
            ->willReturn(true);

        $this->parserServiceConnector->expects($this->once())
            ->method('checkServiceHealth');

        $this->steamApiService->expects($this->once())
            ->method('getNextMatchSharingCode')
            ->with($user->steam_id, $user->steam_game_auth_code, $user->steam_sharecode)
            ->willReturn(null);

        $this->demoDownloadService->expects($this->once())
            ->method('cleanupOldTempFiles');

        $this->demoDownloadService->expects($this->never())
            ->method('downloadDemo');

        Queue::fake();

        $job = new ValveDemoRetrieval;
        $job->handle();

        Queue::assertNotPushed(ParseDemo::class);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'steam_last_processed_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_handle_skips_user_when_sharecode_already_exists(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_sharecode' => 'CSGO-12345-67890-ABCDE-FGHIJ-KLMNO',
            'steam_game_auth_code' => 'AAAA-AAAAA-AAAA',
            'steam_match_processing_enabled' => true,
            'steam_last_processed_at' => null,
        ]);

        GameMatch::factory()->create([
            'sharecode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
        ]);

        $this->steamApiService->expects($this->once())
            ->method('checkServiceHealth')
            ->willReturn(true);

        $this->parserServiceConnector->expects($this->once())
            ->method('checkServiceHealth');

        $this->steamApiService->expects($this->once())
            ->method('getNextMatchSharingCode')
            ->with($user->steam_id, $user->steam_game_auth_code, $user->steam_sharecode)
            ->willReturn('CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY');

        $this->demoDownloadService->expects($this->once())
            ->method('cleanupOldTempFiles');

        $this->demoDownloadService->expects($this->never())
            ->method('downloadDemo');

        Queue::fake();

        $job = new ValveDemoRetrieval;
        $job->handle();

        Queue::assertNotPushed(ParseDemo::class);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'steam_last_processed_at' => now()->toDateTimeString(),
        ]);
    }
}
