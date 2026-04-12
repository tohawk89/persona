<?php

namespace App\Facades;

use App\Services\BrainService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string generate(string $prompt)
 * @method static string generateChatResponse(\Illuminate\Support\Collection $chatHistory, \Illuminate\Support\Collection $memoryTags, string $systemPrompt, \App\Models\Persona $persona)
 * @method static string generateTestResponse(\App\Models\Persona $persona, string $userMessage, array $chatHistory = [])
 * @method static string generateEventResponse(\App\Models\EventSchedule $event, \App\Models\Persona $persona)
 * @method static array generateDailyPlan(\Illuminate\Support\Collection $memoryTags, string $systemPrompt, string $wakeTime, string $sleepTime)
 * @method static array extractMemoryTags(\Illuminate\Support\Collection $chatHistory, string $systemPrompt)
 * @method static string|null generateImage(string $prompt, \App\Models\Persona $persona)
 * @method static string buildPersonaInstructions(\App\Models\Persona $persona)
 * @method static string processMediaTags(string $text, \App\Models\Persona $persona)
 *
 * @see BrainService
 */
class Brain extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BrainService::class;
    }
}
