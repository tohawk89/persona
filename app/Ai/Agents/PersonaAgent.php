<?php

namespace App\Ai\Agents;

use App\Facades\GeminiBrain;
use App\Models\Persona;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class PersonaAgent implements Agent, Conversational
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
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return GeminiBrain::buildPersonaInstructions($this->persona);
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
}
