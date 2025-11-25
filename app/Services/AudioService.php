<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AudioService
{
    /**
     * Generate voice audio from text using ElevenLabs API.
     *
     * @param string $text The text to convert to speech
     * @param string|null $voiceId Optional voice ID, defaults to config value
     * @return string|null The URL of the generated audio file, or null on failure
     */
    public function generateVoice(string $text, ?string $voiceId = null): ?string
    {
        try {
            $apiKey = config('services.elevenlabs.api_key');
            $voiceId = $voiceId ?? config('services.elevenlabs.voice_id');

            if (!$apiKey || !$voiceId) {
                Log::error('AudioService: ElevenLabs credentials not configured');
                return null;
            }

            Log::info('AudioService: Generating voice with ElevenLabs', [
                'text' => $text,
                'voice_id' => $voiceId,
            ]);

            // Call ElevenLabs API
            // Note: Using eleven_turbo_v2_5 which is available on free tier
            $response = Http::timeout(30)
                ->withHeaders([
                    'xi-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                    'text' => $text,
                    'model_id' => 'eleven_turbo_v2_5',
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.5,
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('AudioService: ElevenLabs API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            // Get raw audio data (MPEG)
            $audioData = $response->body();

            if (empty($audioData)) {
                Log::error('AudioService: No audio data in ElevenLabs response');
                return null;
            }

            // Generate unique filename and save to public storage
            $filename = Str::uuid() . '.mp3';
            $path = "voice_notes/{$filename}";

            Storage::disk('public')->put($path, $audioData);

            // Return full URL
            $url = Storage::disk('public')->url($path);

            Log::info('AudioService: Voice generated successfully', [
                'filename' => $filename,
                'url' => $url,
                'size' => strlen($audioData),
            ]);

            return $url;

        } catch (\Exception $e) {
            Log::error('AudioService: Voice generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
