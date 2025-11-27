<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Http, Log};
use App\Facades\{GeminiBrain, Telegram};
use Gemini;

class CheckSystemHealth extends Command
{
    protected $signature = 'app:check';
    protected $description = 'Run health checks on all external services';

    private array $results = [];

    public function handle(): int
    {
        $this->info('🔍 Running System Health Checks...');
        $this->newLine();

        // Run all checks
        $this->checkDatabase();
        $this->checkCloudflare();
        $this->checkElevenLabs();
        $this->checkGemini();
        $this->checkTelegram();

        $this->newLine();
        $this->displayResults();

        // Return exit code based on results
        $failedCount = count(array_filter($this->results, fn($result) => !$result['status']));
        
        if ($failedCount > 0) {
            $this->newLine();
            $this->error("❌ {$failedCount} service(s) failed health check");
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('✅ All services are healthy');
        return Command::SUCCESS;
    }

    private function checkDatabase(): void
    {
        $this->info('Checking Database...');

        try {
            DB::connection()->getPdo();
            $tables = DB::select('SHOW TABLES');
            
            $this->results['database'] = [
                'status' => true,
                'message' => 'Connected (' . count($tables) . ' tables)',
            ];
        } catch (\Exception $e) {
            $this->results['database'] = [
                'status' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
            Log::error('CheckSystemHealth: Database check failed', ['error' => $e->getMessage()]);
        }
    }

    private function checkCloudflare(): void
    {
        $this->info('Checking Cloudflare...');

        try {
            $accountId = config('services.cloudflare.account_id');
            $apiToken = config('services.cloudflare.api_token');

            if (empty($accountId) || empty($apiToken)) {
                $this->results['cloudflare'] = [
                    'status' => false,
                    'message' => 'Missing credentials (CLOUDFLARE_ACCOUNT_ID or CLOUDFLARE_API_TOKEN)',
                ];
                return;
            }

            // Ping Cloudflare Workers AI endpoint to verify credentials
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiToken}",
            ])->timeout(10)->get("https://api.cloudflare.com/client/v4/accounts/{$accountId}");

            if ($response->successful()) {
                $accountName = $response->json()['result']['name'] ?? 'Unknown';
                $this->results['cloudflare'] = [
                    'status' => true,
                    'message' => "Connected to account: {$accountName}",
                ];
            } else {
                $this->results['cloudflare'] = [
                    'status' => false,
                    'message' => 'API request failed (status ' . $response->status() . ')',
                ];
            }
        } catch (\Exception $e) {
            $this->results['cloudflare'] = [
                'status' => false,
                'message' => 'Request failed: ' . $e->getMessage(),
            ];
            Log::error('CheckSystemHealth: Cloudflare check failed', ['error' => $e->getMessage()]);
        }
    }

    private function checkElevenLabs(): void
    {
        $this->info('Checking ElevenLabs...');

        try {
            $apiKey = config('services.elevenlabs.api_key');
            $voiceId = config('services.elevenlabs.voice_id');

            if (empty($apiKey)) {
                $this->results['elevenlabs'] = [
                    'status' => false,
                    'message' => 'Missing API key (ELEVENLABS_API_KEY)',
                ];
                return;
            }

            // Get available voices to verify API key
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
            ])->timeout(10)->get('https://api.elevenlabs.io/v1/voices');

            if ($response->successful()) {
                $voices = $response->json()['voices'] ?? [];
                $voiceCount = count($voices);
                
                // Check if configured voice exists
                $configuredVoice = collect($voices)->firstWhere('voice_id', $voiceId);
                $voiceStatus = $configuredVoice 
                    ? "Voice '{$configuredVoice['name']}' found" 
                    : ($voiceId ? 'Configured voice ID not found' : 'No voice configured');
                
                $this->results['elevenlabs'] = [
                    'status' => true,
                    'message' => "{$voiceCount} voices available. {$voiceStatus}",
                ];
            } else {
                $this->results['elevenlabs'] = [
                    'status' => false,
                    'message' => 'API request failed (status ' . $response->status() . ')',
                ];
            }
        } catch (\Exception $e) {
            $this->results['elevenlabs'] = [
                'status' => false,
                'message' => 'Request failed: ' . $e->getMessage(),
            ];
            Log::error('CheckSystemHealth: ElevenLabs check failed', ['error' => $e->getMessage()]);
        }
    }

    private function checkGemini(): void
    {
        $this->info('Checking Gemini AI...');

        try {
            $apiKey = config('services.gemini.api_key');

            if (empty($apiKey)) {
                $this->results['gemini'] = [
                    'status' => false,
                    'message' => 'Missing API key (GEMINI_API_KEY)',
                ];
                return;
            }

            // Send a simple test prompt
            $client = Gemini::client($apiKey);
            $response = $client->generativeModel('gemini-2.5-flash')
                ->generateContent('Respond with just the word "OK"');

            $text = $response->text();

            if ($response && !empty($text)) {
                $this->results['gemini'] = [
                    'status' => true,
                    'message' => 'Model responded successfully (gemini-2.5-flash)',
                ];
            } else {
                $this->results['gemini'] = [
                    'status' => false,
                    'message' => 'Model returned empty response',
                ];
            }
        } catch (\Exception $e) {
            $this->results['gemini'] = [
                'status' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
            ];
            Log::error('CheckSystemHealth: Gemini check failed', ['error' => $e->getMessage()]);
        }
    }

    private function checkTelegram(): void
    {
        $this->info('Checking Telegram Bot...');

        try {
            $botToken = config('services.telegram.bot_token');

            if (empty($botToken)) {
                $this->results['telegram'] = [
                    'status' => false,
                    'message' => 'Missing bot token (TELEGRAM_BOT_TOKEN)',
                ];
                return;
            }

            // Call getMe endpoint to verify bot token
            $response = Telegram::getMe();

            if ($response) {
                $username = $response['username'] ?? 'Unknown';
                $firstName = $response['first_name'] ?? 'Unknown';
                
                $this->results['telegram'] = [
                    'status' => true,
                    'message' => "Bot connected: @{$username} ({$firstName})",
                ];
            } else {
                $this->results['telegram'] = [
                    'status' => false,
                    'message' => 'getMe request returned null',
                ];
            }
        } catch (\Exception $e) {
            $this->results['telegram'] = [
                'status' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
            ];
            Log::error('CheckSystemHealth: Telegram check failed', ['error' => $e->getMessage()]);
        }
    }

    private function displayResults(): void
    {
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('                    HEALTH CHECK RESULTS                        ');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        $headers = ['Service', 'Status', 'Details'];
        $rows = [];

        foreach ($this->results as $service => $result) {
            $statusIcon = $result['status'] ? '<fg=green>✅ PASS</>' : '<fg=red>❌ FAIL</>';
            $serviceName = ucfirst($service);
            
            $rows[] = [
                $serviceName,
                $statusIcon,
                $result['message'],
            ];
        }

        $this->table($headers, $rows);
    }
}
