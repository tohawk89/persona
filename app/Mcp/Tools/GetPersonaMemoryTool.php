<?php

namespace App\Mcp\Tools;

use App\Models\Persona;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetPersonaMemoryTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Retrieve the memory tags (facts) stored for a given persona.
        Returns all known information about the user and the persona itself, grouped by target and category.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'persona_id' => ['required', 'integer', 'exists:personas,id'],
            'target' => ['nullable', 'in:user,self'],
            'category' => ['nullable', 'string'],
        ]);

        $query = Persona::findOrFail($data['persona_id'])->memoryTags();

        if (! empty($data['target'])) {
            $query->where('target', $data['target']);
        }

        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }

        $tags = $query->orderBy('target')->orderBy('category')->get(['target', 'category', 'value', 'context']);

        return Response::json($tags->toArray());
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
                ->description('The ID of the persona whose memories to retrieve.')
                ->required(),
            'target' => $schema->string()
                ->description('Filter by target: "user" (facts about the user) or "self" (facts about the persona). Omit for all.')
                ->enum(['user', 'self'])
                ->nullable(),
            'category' => $schema->string()
                ->description('Filter by category (e.g. "music", "daily_outfit"). Omit for all.')
                ->nullable(),
        ];
    }
}
