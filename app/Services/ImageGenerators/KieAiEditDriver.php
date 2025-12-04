<?php

namespace App\Services\ImageGenerators;

use App\Contracts\ImageGeneratorInterface;
use App\Models\Persona;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KieAiEditDriver implements ImageGeneratorInterface
{
    private const API_BASE_URL = 'https://api.kie.ai';
    private const CREATE_TASK_ENDPOINT = '/api/v1/jobs/createTask';
    private const RECORD_INFO_ENDPOINT = '/api/v1/jobs/recordInfo';
    private const MODEL = 'bytedance/seedream-v4-edit';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 2; // seconds
    private const MAX_POLL_TIME = 120; // 2 minutes max wait
    private const POLL_INTERVAL = 5; // Check every 5 seconds

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?array $referenceImages = null,
    ) {}

    public function generate(string $prompt, Persona $persona): string
    {
        $apiKey = $this->apiKey ?? config('services.image_generator.drivers.kie_ai.api_key');

        if (!$apiKey) {
            Log::error('KieAiEditDriver: Missing API key');
            return '';
        }

        // Get reference images for the persona
        $imageUrls = $this->getReferenceImageUrls($persona);

        if (empty($imageUrls)) {
            Log::warning('KieAiEditDriver: No reference images found for persona', [
                'persona_id' => $persona->id,
            ]);
            // You could fallback to a default image or return empty
            return '';
        }

        Log::info('KieAiEditDriver: Generating image with edit', [
            'persona_id' => $persona->id,
            'prompt_length' => strlen($prompt),
            'reference_images' => count($imageUrls),
        ]);

        // Step 1: Submit generation task
        $taskId = $this->submitGenerationTask($apiKey, $prompt, $imageUrls);
        if (!$taskId) {
            return '';
        }

        // Step 2: Poll for completion
        $imageUrl = $this->pollForCompletion($apiKey, $taskId);
        if (!$imageUrl) {
            return '';
        }

        // Step 3: Download and save
        return $this->downloadAndSaveImage($imageUrl, $persona);
    }

    public function editImage(string $referenceImageUrl, string $prompt, Persona $persona): string
    {
        $apiKey = $this->apiKey ?? config('services.image_generator.drivers.kie_ai.api_key');

        if (!$apiKey) {
            Log::error('KieAiEditDriver: Missing API key');
            return '';
        }

        Log::info('KieAiEditDriver: Editing image with reference', [
            'persona_id' => $persona->id,
            'prompt_length' => strlen($prompt),
            'reference_url' => $referenceImageUrl,
        ]);

        // Step 1: Submit generation task with single reference image
        $taskId = $this->submitGenerationTask($apiKey, $prompt, [$referenceImageUrl]);
        if (!$taskId) {
            return '';
        }

        // Step 2: Poll for completion
        $imageUrl = $this->pollForCompletion($apiKey, $taskId);
        if (!$imageUrl) {
            return '';
        }

        // Step 3: Download and save
        return $this->downloadAndSaveImage($imageUrl, $persona);
    }

    private function getReferenceImageUrls(Persona $persona): array
    {
        // Use manually provided reference images if available
        if ($this->referenceImages !== null) {
            return $this->referenceImages;
        }

        // Otherwise, get from persona's reference_images media collection
        $media = $persona->getMedia('reference_images');

        if ($media->isEmpty()) {
            // Fallback to avatar if no reference images
            $avatarMedia = $persona->getMedia('avatars');
            if ($avatarMedia->isNotEmpty()) {
                return [$avatarMedia->first()->getUrl()];
            }
            return [];
        }

        // Return up to 10 image URLs (API limit)
        return $media->take(10)->pluck('original_url')->toArray();
    }

    private function submitGenerationTask(string $apiKey, string $prompt, array $imageUrls): ?string
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->post(self::API_BASE_URL . self::CREATE_TASK_ENDPOINT, [
                        'model' => self::MODEL,
                        'input' => [
                            'prompt' => $prompt,
                            'image_urls' => $imageUrls,
                            'image_size' => 'square_hd',      // Square HD aspect ratio
                            'image_resolution' => '2K',        // High quality 2K resolution
                            'max_images' => 1,
                        ],
                    ]);

                if (!$response->successful()) {
                    Log::error('KieAiEditDriver: Task submission failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'attempt' => $attempt,
                    ]);

                    if ($attempt < self::MAX_RETRIES) {
                        sleep(self::RETRY_DELAY);
                        continue;
                    }

                    return null;
                }

                $data = $response->json();

                if (($data['code'] ?? null) !== 200) {
                    Log::error('KieAiEditDriver: Task submission returned error', [
                        'response' => $data,
                        'attempt' => $attempt,
                    ]);

                    if ($attempt < self::MAX_RETRIES) {
                        sleep(self::RETRY_DELAY);
                        continue;
                    }

                    return null;
                }

                $taskId = $data['data']['taskId'] ?? null;

                if (!$taskId) {
                    Log::error('KieAiEditDriver: No taskId in response', ['response' => $data]);
                    return null;
                }

                Log::info('KieAiEditDriver: Task submitted successfully', ['taskId' => $taskId]);
                return $taskId;

            } catch (\Exception $e) {
                Log::error('KieAiEditDriver: Task submission exception', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY);
                    continue;
                }

                return null;
            }
        }

        return null;
    }

    private function pollForCompletion(string $apiKey, string $taskId): ?string
    {
        $startTime = time();

        while ((time() - $startTime) < self::MAX_POLL_TIME) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                    ])
                    ->get(self::API_BASE_URL . self::RECORD_INFO_ENDPOINT, [
                        'taskId' => $taskId,
                    ]);

                if (!$response->successful()) {
                    Log::warning('KieAiEditDriver: Status check failed', [
                        'status' => $response->status(),
                        'taskId' => $taskId,
                    ]);
                    sleep(self::POLL_INTERVAL);
                    continue;
                }

                $data = $response->json();

                if (($data['code'] ?? null) !== 200) {
                    Log::warning('KieAiEditDriver: Status response error', ['response' => $data]);
                    sleep(self::POLL_INTERVAL);
                    continue;
                }

                $taskData = $data['data'] ?? [];
                $state = $taskData['state'] ?? null;

                // state: waiting, success, fail
                if ($state === 'success') {
                    // Parse resultJson which contains {resultUrls: []}
                    $resultJson = json_decode($taskData['resultJson'] ?? '{}', true);
                    $imageUrls = $resultJson['resultUrls'] ?? [];

                    if (empty($imageUrls)) {
                        Log::error('KieAiEditDriver: No images in completed task', ['taskData' => $taskData]);
                        return null;
                    }

                    Log::info('KieAiEditDriver: Generation completed', [
                        'taskId' => $taskId,
                        'imageUrl' => $imageUrls[0],
                        'elapsed' => time() - $startTime,
                        'costTime' => $taskData['costTime'] ?? null,
                    ]);

                    return $imageUrls[0];
                }

                if ($state === 'fail') {
                    Log::error('KieAiEditDriver: Generation failed', [
                        'taskId' => $taskId,
                        'failCode' => $taskData['failCode'] ?? null,
                        'failMsg' => $taskData['failMsg'] ?? 'Unknown error',
                    ]);
                    return null;
                }

                // Still waiting
                Log::info('KieAiEditDriver: Generation in progress', [
                    'taskId' => $taskId,
                    'state' => $state,
                ]);

                sleep(self::POLL_INTERVAL);

            } catch (\Exception $e) {
                Log::warning('KieAiEditDriver: Polling exception', [
                    'taskId' => $taskId,
                    'error' => $e->getMessage(),
                ]);
                sleep(self::POLL_INTERVAL);
            }
        }

        Log::error('KieAiEditDriver: Polling timeout', [
            'taskId' => $taskId,
            'maxTime' => self::MAX_POLL_TIME,
        ]);

        return null;
    }

    private function downloadAndSaveImage(string $imageUrl, Persona $persona): string
    {
        try {
            // Download the image
            $imageData = Http::timeout(30)->get($imageUrl)->body();

            if (empty($imageData)) {
                Log::error('KieAiEditDriver: Failed to download image', ['url' => $imageUrl]);
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

                Log::info('KieAiEditDriver: Image saved via MediaLibrary', [
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
            Log::error('KieAiEditDriver: Failed to save image', [
                'error' => $e->getMessage(),
                'url' => $imageUrl,
            ]);
            return '';
        }
    }
}
