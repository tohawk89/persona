<?php

namespace App\Services;

use App\Contracts\ImageGeneratorInterface;
use App\Services\ImageGenerators\CloudflareFluxDriver;
use App\Services\ImageGenerators\KieAiTextToImageDriver;
use App\Services\ImageGenerators\KieAiEditDriver;

class ImageGeneratorManager
{
    public function driver(?string $driverName = null): ImageGeneratorInterface
    {
        $driver = $driverName ?? config('services.image_generator.default', 'cloudflare');

        return match ($driver) {
            'kie_ai_text_to_image' => new KieAiTextToImageDriver(
                config('services.image_generator.drivers.kie_ai.api_key')
            ),
            'kie_ai_edit' => new KieAiEditDriver(
                config('services.image_generator.drivers.kie_ai.api_key')
            ),
            default => new CloudflareFluxDriver(
                config('services.image_generator.drivers.cloudflare.account_id') ?? config('services.cloudflare.account_id'),
                config('services.image_generator.drivers.cloudflare.api_token') ?? config('services.cloudflare.api_token'),
            ),
        };
    }
}
