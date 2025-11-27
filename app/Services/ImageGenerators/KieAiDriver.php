<?php

namespace App\Services\ImageGenerators;

use App\Contracts\ImageGeneratorInterface;
use App\Models\Persona;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KieAiDriver implements ImageGeneratorInterface
{
    private const API_BASE_URL = 'https://api.kie.ai';
    private const CREATE_TASK_ENDPOINT = '/api/v1/jobs/createTask';
    private const RECORD_INFO_ENDPOINT = '/api/v1/jobs/recordInfo';
    private const MODEL = 'bytedance/seedream-v4-text-to-image';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 2; // seconds
    private const MAX_POLL_TIME = 120; // 2 minutes max wait
    private const POLL_INTERVAL = 5; // Check every 5 seconds

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
        ]);

        // Step 1: Submit generation task
        $taskId = $this->submitGenerationTask($apiKey, $prompt);
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

    private function submitGenerationTask(string $apiKey, string $prompt): ?string
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
                            'image_size' => 'square_hd', // Square HD aspect ratio
                            'image_resolution' => '2K',   // High quality 2K resolution
                            'max_images' => 1,
                        ],
                    ]);

                if (!$response->successful()) {
                    Log::error('KieAiDriver: Task submission failed', [
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
                    Log::error('KieAiDriver: Task submission returned error', [
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
                    Log::error('KieAiDriver: No taskId in response', ['response' => $data]);
                    return null;
                }

                Log::info('KieAiDriver: Task submitted successfully', ['taskId' => $taskId]);
                return $taskId;

            } catch (\Exception $e) {
                Log::error('KieAiDriver: Task submission exception', [
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
                    Log::warning('KieAiDriver: Status check failed', [
                        'status' => $response->status(),
                        'taskId' => $taskId,
                    ]);
                    sleep(self::POLL_INTERVAL);
                    continue;
                }

                $data = $response->json();

                if (($data['code'] ?? null) !== 200) {
                    Log::warning('KieAiDriver: Status response error', ['response' => $data]);
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
                        Log::error('KieAiDriver: No images in completed task', ['taskData' => $taskData]);
                        return null;
                    }

                    Log::info('KieAiDriver: Generation completed', [
                        'taskId' => $taskId,
                        'imageUrl' => $imageUrls[0],
                        'elapsed' => time() - $startTime,
                        'costTime' => $taskData['costTime'] ?? null,
                    ]);

                    return $imageUrls[0];
                }

                if ($state === 'fail') {
                    Log::error('KieAiDriver: Generation failed', [
                        'taskId' => $taskId,
                        'failCode' => $taskData['failCode'] ?? null,
                        'failMsg' => $taskData['failMsg'] ?? 'Unknown error',
                    ]);
                    return null;
                }

                // Still waiting
                Log::info('KieAiDriver: Generation in progress', [
                    'taskId' => $taskId,
                    'state' => $state,
                ]);

                sleep(self::POLL_INTERVAL);

            } catch (\Exception $e) {
                Log::warning('KieAiDriver: Polling exception', [
                    'taskId' => $taskId,
                    'error' => $e->getMessage(),
                ]);
                sleep(self::POLL_INTERVAL);
            }
        }

        Log::error('KieAiDriver: Polling timeout', [
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
