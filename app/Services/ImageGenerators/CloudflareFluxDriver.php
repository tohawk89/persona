<?php

namespace App\Services\ImageGenerators;

use App\Contracts\ImageGeneratorInterface;
use App\Models\Persona;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloudflareFluxDriver implements ImageGeneratorInterface
{
    private const MODEL = '@cf/black-forest-labs/flux-1-schnell';
    private const IMAGE_NUM_STEPS = 4;

    public function __construct(
        private readonly ?string $accountId = null,
        private readonly ?string $apiToken = null,
    ) {}

    public function generate(string $prompt, Persona $persona): string
    {
        $accountId = $this->accountId ?? config('services.image_generator.drivers.cloudflare.account_id') ?? config('services.cloudflare.account_id');
        $apiToken  = $this->apiToken  ?? config('services.image_generator.drivers.cloudflare.api_token')  ?? config('services.cloudflare.api_token');

        if (!$accountId || !$apiToken) {
            Log::error('CloudflareFluxDriver: Missing credentials');
            return '';
        }

        $response = $this->callCloudflareImageAPI($accountId, $apiToken, $prompt);

        if (!$response->successful()) {
            $errorBody = $response->body();
            Log::error('CloudflareFluxDriver: API request failed', [
                'status' => $response->status(),
                'body' => $errorBody,
            ]);

            if ($response->status() === 400 && str_contains($errorBody, 'NSFW')) {
                Log::warning('CloudflareFluxDriver: Prompt flagged as NSFW, retrying with safe prompt');
                $safePrompt = 'Close-up portrait photograph, artistic framing showing partial face. Professional photography, soft lighting, high quality.';
                $response = $this->callCloudflareImageAPI($accountId, $apiToken, $safePrompt);

                if (!$response->successful()) {
                    Log::error('CloudflareFluxDriver: Retry with safe prompt failed');
                    return '';
                }
            } else {
                return '';
            }
        }

        return $this->saveGeneratedImage($response, $persona) ?? '';
    }

    private function callCloudflareImageAPI(string $accountId, string $apiToken, string $prompt)
    {
        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/" . self::MODEL;

        return Http::timeout(30)
            ->withHeaders(['Authorization' => "Bearer {$apiToken}"])
            ->post($endpoint, [
                'prompt' => $prompt,
                'num_steps' => self::IMAGE_NUM_STEPS,
            ]);
    }

    private function saveGeneratedImage($response, Persona $persona): ?string
    {
        $data = $response->json();

        if (!isset($data['result']['image'])) {
            Log::error('CloudflareFluxDriver: No image in response', ['response' => $data]);
            return null;
        }

        $base64Image = $data['result']['image'];
        $decodedImage = base64_decode($base64Image);

        if ($decodedImage === false) {
            Log::error('CloudflareFluxDriver: Failed to decode Base64 image');
            return null;
        }

        $filename = Str::uuid() . '.jpg';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $decodedImage);

        try {
            $media = $persona->addMedia($tempPath)
                ->usingFileName($filename)
                ->toMediaCollection('generated_images');

            $url = $media->getUrl();
        } finally {
            @unlink($tempPath);
        }

        Log::info('CloudflareFluxDriver: Image generated via MediaLibrary', [
            'filename' => $filename,
            'url' => $url ?? null,
            'media_id' => isset($media) ? $media->id : null,
        ]);

        return $url ?? null;
    }
}
