<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ScheduleEventTool;
use App\Facades\Brain;
use App\Models\Persona;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class PersonaAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Create a new PersonaAgent instance.
     *
     * @param  array<int, array{role: string, content: string}>  $chatHistory
     */
    public function __construct(
        public readonly Persona $persona,
        public readonly array $chatHistory = [],
    ) {}

    /**
     * Get the full system instructions for this persona.
     */
    public function instructions(): Stringable|string
    {
        return Brain::buildPersonaInstructions($this->persona);
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return array_map(
            fn (array $msg): Message => $msg['role'] === 'assistant'
                ? new AssistantMessage($msg['content'])
                : new UserMessage($msg['content']),
            $this->chatHistory,
        );
    }

    /**
     * Register tools available to this agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [new ScheduleEventTool($this->persona)];
    }

    public function provider(): ?string
    {
        return config('ai.agents.chat.provider') ?: null;
    }

    public function model(): ?string
    {
        $model = config('ai.agents.chat.model')
            ?: config('ai.agents.default_model');

        return $model ?: null;
    }
}
