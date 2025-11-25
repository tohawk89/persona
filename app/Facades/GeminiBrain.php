<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateChatResponse(\Illuminate\Support\Collection $chatHistory, \Illuminate\Support\Collection $memoryTags, string $systemPrompt)
 * @method static array generateDailyPlan(\Illuminate\Support\Collection $memoryTags, string $systemPrompt, string $wakeTime, string $sleepTime)
 * @method static array extractMemoryTags(\Illuminate\Support\Collection $chatHistory, string $systemPrompt)
 * @method static string|null generateImage(string $prompt)
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
