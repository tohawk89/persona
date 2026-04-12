<?php

use App\Mcp\Servers\PersonaAiServer;
use Laravel\Mcp\Facades\Mcp;

/**
 * Register the Persona AI MCP server.
 *
 * - Local (stdio) transport: used by `php artisan mcp:start persona-ai`
 *   and integrates with GitHub Copilot, Claude Desktop, etc.
 *
 * - HTTP transport: exposes an SSE endpoint at /mcp/persona-ai
 *   for web-based MCP clients.
 */
Mcp::local('persona-ai', PersonaAiServer::class);

Mcp::web('/mcp/persona-ai', PersonaAiServer::class);
