<?php

namespace App\Mcp\Tools;

use App\Ai\Agents\PersonaAgent;
use App\Facades\GeminiBrain;
use App\Models\Persona;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GeneratePersonaReplyTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Generate a conversational reply from a persona using the Gemini AI model.
        Provide a persona_id and a user_message to get an in-character response that
        incorporates the persona's memory context, personality, and conversation style.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'persona_id' => ['required', 'integer', 'exists:personas,id'],
            'user_message' => ['required', 'string', 'max:2000'],
            'chat_history' => ['array'],
            'chat_history.*.role' => ['required', 'in:user,assistant'],
            'chat_history.*.content' => ['required', 'string'],
        ]);

        $persona = Persona::findOrFail($data['persona_id']);

        $agentResponse = PersonaAgent::make(
            persona: $persona,
            chatHistory: $data['chat_history'] ?? [],
        )->prompt($data['user_message'], provider: 'gemini');

        $reply = GeminiBrain::processMediaTags($agentResponse->text, $persona);

        return Response::text($reply);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'persona_id' => $schema->integer()
                ->description('The ID of the persona to generate a reply for.')
                ->required(),
            'user_message' => $schema->string()
                ->description('The message sent by the user.')
                ->required(),
            'chat_history' => $schema->array()
                ->description('Optional previous messages in the conversation. Each item must have role (user|assistant) and content.')
                ->items($schema->object([
                    'role' => $schema->string()->required(),
                    'content' => $schema->string()->required(),
                ])),
        ];
    }
}
