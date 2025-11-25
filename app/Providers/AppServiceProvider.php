<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GeminiBrainService;
use App\Services\TelegramService;
use App\Services\SmartQueueService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services as singletons
        $this->app->singleton(GeminiBrainService::class, function ($app) {
            return new GeminiBrainService();
        });

        $this->app->singleton(TelegramService::class, function ($app) {
            return new TelegramService();
        });

        $this->app->singleton(SmartQueueService::class, function ($app) {
            return new SmartQueueService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
