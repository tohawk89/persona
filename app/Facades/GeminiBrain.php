<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateChatResponse(\Illuminate\Support\Collection $chatHistory, \Illuminate\Support\Collection $memoryTags, string $systemPrompt, \App\Models\Persona $persona)
 * @method static string generateTestResponse(\App\Models\Persona $persona, string $userMessage, array $chatHistory = [])
 * @method static string generateEventResponse(\App\Models\EventSchedule $event, \App\Models\Persona $persona)
 * @method static array generateDailyPlan(\Illuminate\Support\Collection $memoryTags, string $systemPrompt, string $wakeTime, string $sleepTime)
 * @method static array extractMemoryTags(\Illuminate\Support\Collection $chatHistory, string $systemPrompt)
 * @method static string|null generateImage(string $prompt, \App\Models\Persona $persona)
 *
 * @see \App\Services\GeminiBrainService
 */
class GeminiBrain extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\GeminiBrainService::class;
    }
}
