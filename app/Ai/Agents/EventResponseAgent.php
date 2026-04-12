<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agent responsible for generating proactive scheduled event messages.
 *
 * Configure independently via:
 *   AI_EVENT_PROVIDER=lmstudio
 *   AI_EVENT_MODEL=gemma-4-e2b-it-uncensored
 */
class EventResponseAgent implements Agent, Conversational
{
    use Promptable;

    /**
     * Minimal instructions — persona system prompt + event context is injected via prompt().
     */
    public function instructions(): Stringable|string
    {
        return 'You are a virtual companion. When given an event trigger instruction, generate a natural, warm message to send to the user. Keep it concise and human-like.';
    }

    /**
     * No prior messages — context is built into the prompt.
     */
    public function messages(): iterable
    {
        return [];
    }

    public function provider(): ?string
    {
        return config('ai.agents.event.provider') ?: null;
    }

    public function model(): ?string
    {
        $model = config('ai.agents.event.model')
            ?: config('ai.agents.default_model');

        return $model ?: null;
    }
}
