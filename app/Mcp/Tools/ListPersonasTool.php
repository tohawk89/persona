<?php

namespace App\Mcp\Tools;

use App\Models\Persona;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListPersonasTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        List all available AI personas in the system.
        Returns persona ids, names, and active status so you can reference the correct persona_id in other tools.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $personas = Persona::query()
            ->select(['id', 'name', 'gender', 'is_active', 'wake_time', 'sleep_time'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Response::json($personas->toArray());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
