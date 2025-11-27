<?php

namespace App\Services\ImageGenerators;

use App\Contracts\ImageGeneratorInterface;
use App\Models\Persona;
use Illuminate\Support\Facades\Log;

class KieAiDriver implements ImageGeneratorInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    public function generate(string $prompt, Persona $persona): string
    {
        Log::info('KieAiDriver: Placeholder generate called', [
            'persona_id' => $persona->id,
            'prompt_length' => strlen($prompt),
        ]);

        // TODO: Implement real API call when available.
        return 'https://via.placeholder.com/1024x1024.png?text=Kie.ai+driver+stub';
    }
}
