<?php

namespace Tests\Feature;

use App\Ai\Agents\PersonaAgent;
use App\Mcp\Servers\PersonaAiServer;
use App\Mcp\Tools\GeneratePersonaReplyTool;
use App\Mcp\Tools\GetPersonaMemoryTool;
use App\Mcp\Tools\ListPersonasTool;
use App\Models\MemoryTag;
use App\Models\Persona;
use App\Models\User;
use App\Services\BrainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Persona $persona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->persona = Persona::factory()->create(['user_id' => $this->user->id, 'is_active' => true]);
    }

    #[Test]
    public function list_personas_tool_returns_all_personas(): void
    {
        Persona::factory()->count(2)->create(['user_id' => $this->user->id]);

        PersonaAiServer::tool(ListPersonasTool::class)
            ->assertOk()
            ->assertSee($this->persona->name);
    }

    #[Test]
    public function get_persona_memory_tool_returns_memory_tags(): void
    {
        MemoryTag::factory()->create([
            'persona_id' => $this->persona->id,
            'target' => 'user',
            'category' => 'music',
            'value' => 'likes Linkin Park',
        ]);

        PersonaAiServer::tool(GetPersonaMemoryTool::class, [
            'persona_id' => $this->persona->id,
        ])->assertOk()->assertSee('likes Linkin Park');
    }

    #[Test]
    public function get_persona_memory_tool_filters_by_target(): void
    {
        MemoryTag::factory()->create([
            'persona_id' => $this->persona->id,
            'target' => 'user',
            'category' => 'food',
            'value' => 'loves sushi',
        ]);

        MemoryTag::factory()->create([
            'persona_id' => $this->persona->id,
            'target' => 'self',
            'category' => 'daily_outfit',
            'value' => 'blue dress',
        ]);

        PersonaAiServer::tool(GetPersonaMemoryTool::class, [
            'persona_id' => $this->persona->id,
            'target' => 'user',
        ])->assertOk()->assertSee('loves sushi')->assertDontSee('blue dress');
    }

    #[Test]
    public function get_persona_memory_tool_requires_valid_persona_id(): void
    {
        PersonaAiServer::tool(GetPersonaMemoryTool::class, [
            'persona_id' => 999999,
        ])->assertHasErrors();
    }

    #[Test]
    public function generate_persona_reply_tool_returns_ai_response(): void
    {
        PersonaAgent::fake(['Hey there! How are you doing?']);

        $this->mock(BrainService::class)
            ->shouldReceive('buildPersonaInstructions')->andReturn('You are a persona.')
            ->shouldReceive('processMediaTags')->andReturnArg(0);

        PersonaAiServer::tool(GeneratePersonaReplyTool::class, [
            'persona_id' => $this->persona->id,
            'user_message' => 'Hello!',
        ])->assertOk()->assertSee('Hey there');
    }

    #[Test]
    public function generate_persona_reply_tool_validates_required_fields(): void
    {
        PersonaAiServer::tool(GeneratePersonaReplyTool::class, [
            'persona_id' => $this->persona->id,
        ])->assertHasErrors();
    }

    #[Test]
    public function generate_persona_reply_tool_rejects_invalid_persona(): void
    {
        PersonaAiServer::tool(GeneratePersonaReplyTool::class, [
            'persona_id' => 999999,
            'user_message' => 'Hello!',
        ])->assertHasErrors();
    }

    #[Test]
    public function generate_persona_reply_tool_accepts_chat_history(): void
    {
        PersonaAgent::fake(['Sure, tell me more!']);

        $this->mock(BrainService::class)
            ->shouldReceive('buildPersonaInstructions')->andReturn('You are a persona.')
            ->shouldReceive('processMediaTags')->andReturnArg(0);

        PersonaAiServer::tool(GeneratePersonaReplyTool::class, [
            'persona_id' => $this->persona->id,
            'user_message' => 'Can we continue?',
            'chat_history' => [
                ['role' => 'user', 'content' => 'Hi there'],
                ['role' => 'assistant', 'content' => 'Hello!'],
            ],
        ])->assertOk()->assertSee('Sure, tell me more!');
    }
}
