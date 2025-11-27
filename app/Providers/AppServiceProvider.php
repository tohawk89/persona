<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GeminiBrainService;
use App\Services\TelegramService;
use App\Services\SmartQueueService;
use App\Contracts\ImageGeneratorInterface;
use App\Services\ImageGeneratorManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services as singletons
        $this->app->singleton(ImageGeneratorManager::class, function ($app) {
            return new ImageGeneratorManager();
        });

        // Bind the ImageGeneratorInterface to the configured driver via the manager
        $this->app->bind(ImageGeneratorInterface::class, function ($app) {
            return $app->make(ImageGeneratorManager::class)->driver();
        });

        $this->app->singleton(GeminiBrainService::class, function ($app) {
            return new GeminiBrainService($app->make(ImageGeneratorInterface::class));
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
