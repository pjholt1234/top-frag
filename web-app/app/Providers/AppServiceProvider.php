<?php

namespace App\Providers;

use App\Models\DamageEvent;
use App\Models\DemoProcessingJob;
use App\Models\GrenadeEvent;
use App\Models\GunfightEvent;
use App\Models\User;
use App\Observers\DamageEventObserver;
use App\Observers\DemoProcessingJobObserver;
use App\Observers\GrenadeEventObserver;
use App\Observers\GunfightEventObserver;
use App\Observers\UserObserver;
use App\Services\Demo\DemoParserService;
use App\Services\Integrations\Parser\ParserServiceConnector;
use App\Services\Matches\MatchHistoryService;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;

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
        User::observe(UserObserver::class);

        // Register Steam provider with Socialite
        $socialite = $this->app->make(Factory::class);
        $socialite->extend('steam', function ($app) use ($socialite) {
            $config = $app['config']['services.steam'];

            return $socialite->buildProvider(\SocialiteProviders\Steam\Provider::class, $config);
        });

        // Register Discord provider with Socialite
        $socialite->extend('discord', function ($app) use ($socialite) {
            $config = $app['config']['services.discord'];

            return $socialite->buildProvider(\SocialiteProviders\Discord\Provider::class, $config);
        });
    }
}
