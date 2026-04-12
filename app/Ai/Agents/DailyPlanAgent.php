<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agent responsible for generating daily event plans as JSON.
 *
 * Configure independently via:
 *   AI_PLANNER_PROVIDER=lmstudio
 *   AI_PLANNER_MODEL=gemma-4-e2b-it-uncensored
 */
class DailyPlanAgent implements Agent, Conversational
{
    use Promptable;

    /**
     * Minimal instructions — the full context and JSON schema is injected via prompt().
     */
    public function instructions(): Stringable|string
    {
        return 'You are a scheduling assistant. When given persona context and tasks, you output a structured daily plan. Return ONLY valid JSON with no markdown fences or explanation.';
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
        return config('ai.agents.planner.provider') ?: null;
    }

    public function model(): ?string
    {
        $model = config('ai.agents.planner.model')
            ?: config('ai.agents.default_model');

        return $model ?: null;
    }
}
