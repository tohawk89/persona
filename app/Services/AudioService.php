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
     * @param \App\Models\Persona|null $persona Optional persona for MediaLibrary storage
     * @param string|null $voiceId Optional voice ID, defaults to config value
     * @return string|null The URL of the generated audio file, or null on failure
     */
    public function generateVoice(string $text, ?\App\Models\Persona $persona = null, ?string $voiceId = null): ?string
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

            // Generate unique filename
            $filename = Str::uuid() . '.mp3';

            // If persona is available, use MediaLibrary
            if ($persona) {
                try {
                    // Save temporarily
                    $tempPath = sys_get_temp_dir() . '/' . $filename;
                    file_put_contents($tempPath, $audioData);

                    // Add to MediaLibrary
                    $media = $persona->addMedia($tempPath)
                        ->usingFileName($filename)
                        ->toMediaCollection('voice_notes');

                    $url = $media->getUrl();

                    // Clean up temp file
                    @unlink($tempPath);

                    Log::info('AudioService: Voice generated successfully via MediaLibrary', [
                        'filename' => $filename,
                        'url' => $url,
                        'size' => strlen($audioData),
                        'media_id' => $media->id,
                    ]);

                    return $url;
                } catch (\Exception $e) {
                    // Clean up temp file on error
                    @unlink($tempPath);
                    Log::error('AudioService: MediaLibrary save failed', [
                        'error' => $e->getMessage(),
                    ]);
                    // Fall through to Storage fallback
                }
            }

            // Fallback to Storage if no persona or MediaLibrary failed
            $path = "voice_notes/{$filename}";
            Storage::disk('public')->put($path, $audioData);
            $url = asset('storage/' . $path);

            Log::info('AudioService: Voice generated successfully (fallback)', [
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
