<?php

namespace App\Providers;

use App\Contracts\ImageGeneratorInterface;
use App\Services\BrainService;
use App\Services\ImageGeneratorManager;
use App\Services\SmartQueueService;
use App\Services\TelegramService;
use App\Services\WardrobeService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services as singletons
        $this->app->singleton(ImageGeneratorManager::class, function ($app) {
            return new ImageGeneratorManager;
        });

        // Bind the ImageGeneratorInterface to the configured driver via the manager
        $this->app->bind(ImageGeneratorInterface::class, function ($app) {
            return $app->make(ImageGeneratorManager::class)->driver();
        });

        $this->app->singleton(BrainService::class, function ($app) {
            return new BrainService($app->make(ImageGeneratorManager::class));
        });

        $this->app->singleton(TelegramService::class, function ($app) {
            return new TelegramService;
        });

        $this->app->singleton(SmartQueueService::class, function ($app) {
            return new SmartQueueService;
        });

        $this->app->singleton(WardrobeService::class, function ($app) {
            return new WardrobeService;
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
