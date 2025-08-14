<?php

namespace App\Providers;

use App\Models\DemoProcessingJob;
use App\Observers\DemoProcessingJobObserver;
use App\Services\DemoParserService;
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

        $this->app->alias(ParserServiceConnector::class, 'parser.connector');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DemoProcessingJob::observe(DemoProcessingJobObserver::class);
    }
}
