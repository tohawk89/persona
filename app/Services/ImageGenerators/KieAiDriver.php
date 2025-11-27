<?php

namespace App\Services\ImageGenerators;

use App\Contracts\ImageGeneratorInterface;
use App\Models\Persona;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KieAiDriver implements ImageGeneratorInterface
{
    private const API_ENDPOINT = 'https://api.kie.ai/v1/chat/completions';
    private const MODEL = 'bytedance/seedream-v4-text-to-image';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 2; // seconds

    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    public function generate(string $prompt, Persona $persona): string
    {
        $apiKey = $this->apiKey ?? config('services.image_generator.drivers.kie_ai.api_key');

        if (!$apiKey) {
            Log::error('KieAiDriver: Missing API key');
            return '';
        }

        Log::info('KieAiDriver: Generating image', [
            'persona_id' => $persona->id,
            'prompt_length' => strlen($prompt),
            'model' => self::MODEL,
        ]);

        // Attempt generation with retries
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = Http::timeout(60)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->post(self::API_ENDPOINT, [
                        'model' => self::MODEL,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                    ]);

                if (!$response->successful()) {
                    Log::error('KieAiDriver: API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'attempt' => $attempt,
                    ]);

                    if ($attempt < self::MAX_RETRIES) {
                        Log::info('KieAiDriver: Retrying in ' . self::RETRY_DELAY . 's...', [
                            'attempt' => $attempt,
                            'max_retries' => self::MAX_RETRIES,
                        ]);
                        sleep(self::RETRY_DELAY);
                        continue;
                    }

                    return '';
                }

                $data = $response->json();

                Log::info('KieAiDriver: API response received', ['data' => $data]);

                // Extract image URL from response - check multiple possible formats
                $imageUrl = $data['output']['image_url'] ??
                           $data['image_url'] ??
                           $data['url'] ??
                           $data['data'][0]['url'] ??
                           null;

                if (!$imageUrl) {
                    Log::error('KieAiDriver: No image URL in response', ['response' => $data]);
                    return '';
                }

                // Download and save the image using MediaLibrary
                return $this->downloadAndSaveImage($imageUrl, $persona);

            } catch (\Exception $e) {
                Log::error('KieAiDriver: Generation failed', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'trace' => $e->getTraceAsString(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY);
                    continue;
                }

                return '';
            }
        }

        return '';
    }

    private function downloadAndSaveImage(string $imageUrl, Persona $persona): string
    {
        try {
            // Download the image
            $imageData = Http::timeout(30)->get($imageUrl)->body();

            if (empty($imageData)) {
                Log::error('KieAiDriver: Failed to download image', ['url' => $imageUrl]);
                return '';
            }

            // Save to temporary file
            $filename = Str::uuid() . '.png';
            $tempPath = sys_get_temp_dir() . '/' . $filename;
            file_put_contents($tempPath, $imageData);

            try {
                // Add to MediaLibrary
                $media = $persona->addMedia($tempPath)
                    ->usingFileName($filename)
                    ->toMediaCollection('generated_images');

                $url = $media->getUrl();

                Log::info('KieAiDriver: Image saved via MediaLibrary', [
                    'filename' => $filename,
                    'url' => $url,
                    'media_id' => $media->id,
                    'size' => strlen($imageData),
                ]);

                return $url;
            } finally {
                @unlink($tempPath);
            }

        } catch (\Exception $e) {
            Log::error('KieAiDriver: Failed to save image', [
                'error' => $e->getMessage(),
                'url' => $imageUrl,
            ]);
            return '';
        }
    }
}
