<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agent responsible for extracting and diffing memory tags from conversations.
 *
 * Configure independently via:
 *   AI_MEMORY_PROVIDER=lmstudio
 *   AI_MEMORY_MODEL=gemma-4-e2b-it-uncensored
 */
class MemoryExtractionAgent implements Agent, Conversational
{
    use Promptable;

    /**
     * Minimal instructions — full context and schema is injected via prompt().
     */
    public function instructions(): Stringable|string
    {
        return 'You are a memory extraction assistant. Analyse conversations and output structured JSON diffs of memory tags. Return ONLY valid JSON with no markdown fences or explanation.';
    }

    /**
     * No prior messages — this is a one-shot generation call.
     */
    public function messages(): iterable
    {
        return [];
    }

    public function provider(): ?string
    {
        return config('ai.agents.memory.provider') ?: null;
    }

    public function model(): ?string
    {
        $model = config('ai.agents.memory.model')
            ?: config('ai.agents.default_model');

        return $model ?: null;
    }
}
