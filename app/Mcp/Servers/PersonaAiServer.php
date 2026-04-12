<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GeneratePersonaReplyTool;
use App\Mcp\Tools\GetPersonaMemoryTool;
use App\Mcp\Tools\ListPersonasTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

class PersonaAiServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Persona AI Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '1.0.0';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        This server exposes the AI virtual companion (Persona) system.

        Available tools:
        - `list_personas` — list all personas with their IDs and status.
        - `get_persona_memory` — retrieve stored facts (memory tags) for a persona.
        - `generate_persona_reply` — generate an in-character AI reply from a persona given a user message and optional chat history.

        Typical workflow:
        1. Call `list_personas` to find the persona_id you want to interact with.
        2. Optionally call `get_persona_memory` to understand what the persona knows.
        3. Call `generate_persona_reply` with the persona_id and the user message.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListPersonasTool::class,
        GetPersonaMemoryTool::class,
        GeneratePersonaReplyTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
