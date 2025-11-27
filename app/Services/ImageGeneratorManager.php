<?php

namespace App\Services;

use App\Contracts\ImageGeneratorInterface;
use App\Services\ImageGenerators\CloudflareFluxDriver;
use App\Services\ImageGenerators\KieAiDriver;

class ImageGeneratorManager
{
    public function driver(): ImageGeneratorInterface
    {
        $default = config('services.image_generator.default', 'cloudflare');

        if ($default === 'kie_ai') {
            return new KieAiDriver(config('services.image_generator.drivers.kie_ai.api_key'));
        }

        return new CloudflareFluxDriver(
            config('services.image_generator.drivers.cloudflare.account_id') ?? config('services.cloudflare.account_id'),
            config('services.image_generator.drivers.cloudflare.api_token') ?? config('services.cloudflare.api_token'),
        );
    }
}
