<?php

namespace App\Providers;

use App\Models\DamageEvent;
use App\Models\DemoProcessingJob;
use App\Models\GrenadeEvent;
use App\Models\GunfightEvent;
use App\Observers\DamageEventObserver;
use App\Observers\DemoProcessingJobObserver;
use App\Observers\GrenadeEventObserver;
use App\Observers\GunfightEventObserver;
use App\Services\DemoParserService;
use App\Services\MatchHistoryService;
use App\Services\ParserServiceConnector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ParserServiceConnector::class, function ($app) {
            return new ParserServiceConnector;
        });

        $this->app->singleton(DemoParserService::class, function ($app) {
            return new DemoParserService;
        });

        $this->app->singleton(MatchHistoryService::class, function ($app) {
            return new MatchHistoryService(
                matchDetailsService: $app->make(\App\Services\Matches\MatchDetailsService::class)
            );
        });

        $this->app->alias(ParserServiceConnector::class, 'parser.connector');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DemoProcessingJob::observe(DemoProcessingJobObserver::class);
        DamageEvent::observe(DamageEventObserver::class);
        GunfightEvent::observe(GunfightEventObserver::class);
        GrenadeEvent::observe(GrenadeEventObserver::class);
    }
}
