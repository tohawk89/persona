<?php

use App\Ai\Agents\PersonaAgent;
use App\Models\Persona;
use GuzzleHttp\Client;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Ai\AnonymousAgent;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "Testing LM Studio connection...\n";
echo 'Provider: '.config('ai.default')."\n";
echo 'URL: '.config('ai.providers.lmstudio.url')."\n\n";

// First, check what models LM Studio exposes
echo "Fetching available models from LM Studio...\n";
try {
    $client = new Client;
    $response = $client->get('http://127.0.0.1:1234/v1/models', [
        'headers' => ['Authorization' => 'Bearer lm-studio'],
        'timeout' => 5,
    ]);
    $models = json_decode($response->getBody(), true);
    $modelIds = array_map(fn ($m) => $m['id'], $models['data'] ?? []);
    echo 'Available models: '.implode(', ', $modelIds)."\n\n";
    $firstModel = $modelIds[0] ?? null;
} catch (Throwable $e) {
    echo 'Could not fetch models: '.$e->getMessage()."\n\n";
    $firstModel = null;
}

// Test text generation via the Laravel AI SDK
echo "Testing text generation...\n";
try {
    $persona = Persona::first();

    if (! $persona) {
        echo "No persona found in database. Testing with anonymous agent...\n";
        $agent = new AnonymousAgent('You are a helpful assistant.');
        $response = $agent->prompt('Say hello in one short sentence.', model: $firstModel);
    } else {
        $agent = PersonaAgent::make($persona);
        $response = $agent->prompt('Say hello in one short sentence.', model: $firstModel);
    }

    echo 'Response: '.$response->text."\n";
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'Class: '.get_class($e)."\n";
}
