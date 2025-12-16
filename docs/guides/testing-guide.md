# Testing Guide

**Last Updated:** December 16, 2025  
**For:** AI Virtual Companion Laravel 12 Project

This comprehensive guide covers testing philosophy, strategies, and practical examples for maintaining quality in the AI Virtual Companion codebase.

---

## Table of Contents

1. [Testing Philosophy](#1-testing-philosophy)
2. [Test Types Overview](#2-test-types-overview)
3. [Running Tests](#3-running-tests)
4. [Writing Tests](#4-writing-tests)
5. [Mocking External APIs](#5-mocking-external-apis)
6. [Testing Services](#6-testing-services)
7. [Testing Jobs](#7-testing-jobs)
8. [Testing Livewire Components](#8-testing-livewire-components)
9. [Test Coverage](#9-test-coverage)
10. [Common Testing Patterns](#10-common-testing-patterns)
11. [Debugging Failed Tests](#11-debugging-failed-tests)
12. [CI/CD Integration](#12-cicd-integration)

---

## 1. Testing Philosophy

### Why Tests Matter for This Project

The AI Virtual Companion relies on complex integrations with **4 external APIs** (Gemini, Telegram, ElevenLabs, Cloudflare Workers AI), sophisticated queue-based event scheduling, and real-time user interactions. Tests are critical for:

**1. Reliability** - Ensure critical flows (chat responses, scheduled events, memory extraction) work correctly  
**2. Refactoring Confidence** - Safely improve code without breaking functionality  
**3. API Cost Control** - Mock external calls to avoid expensive test runs ($$$)  
**4. Regression Prevention** - Catch bugs before they reach production  
**5. Documentation** - Tests serve as executable specifications of how the system works  

### Current State (From TECHNICAL_DEBT.md)

```
Test Coverage: ~2-5%
Tests Passing: 34 passed, 1 failed
Duration: 41.59s

✅ Tested: Auth flows, Profile management, PersonaGallery
❌ NOT Tested: Core services, Jobs, Telegram webhooks, Memory extraction
```

**Goal:** Reach 80% coverage for core business logic (services, jobs, critical features).

---

## 2. Test Types Overview

### Unit Tests (`tests/Unit/`)

Test individual methods/classes in isolation with mocked dependencies.

**When to Use:**
- Testing service methods (e.g., `GeminiBrainService::sanitizePromptForImageGeneration()`)
- Testing DTOs, value objects, helpers
- Testing model methods with no database dependencies

**Example:**
```php
// tests/Unit/Services/GeminiBrainServiceTest.php
public function test_sanitize_prompt_removes_nsfw_content()
{
    $service = new GeminiBrainService(
        $this->mock(ImageGeneratorManager::class)
    );
    
    $unsafePrompt = "Generate naked image of person";
    $result = $service->sanitizePromptForImageGeneration($unsafePrompt);
    
    $this->assertStringNotContainsString('naked', $result);
    $this->assertStringContainsString('appropriate', $result);
}
```

### Feature Tests (`tests/Feature/`)

Test complete features with database interactions, HTTP requests, and integrated components.

**When to Use:**
- Testing API endpoints (Telegram webhook)
- Testing complete user flows (chat conversation, scheduled event execution)
- Testing Livewire components with database state

**Example:**
```php
// tests/Feature/TelegramWebhookTest.php
public function test_webhook_processes_incoming_message()
{
    $user = User::factory()->create(['telegram_chat_id' => 123]);
    $persona = Persona::factory()->create(['user_id' => $user->id]);
    
    Queue::fake();
    
    $response = $this->postJson('/api/telegram/webhook', [
        'message' => [
            'chat' => ['id' => 123],
            'text' => 'Hello!'
        ]
    ]);
    
    $response->assertOk();
    Queue::assertPushed(ProcessChatResponse::class);
}
```

### Integration Tests (Feature Subset)

Test interactions between multiple components (services + jobs + database).

**Example:**
```php
// tests/Feature/MemoryExtractionIntegrationTest.php
public function test_memory_extraction_workflow()
{
    GeminiBrain::shouldReceive('extractMemoryTags')
        ->once()
        ->andReturn([
            ['category' => 'music', 'value' => 'Linkin Park', 'target' => 'user']
        ]);
    
    $job = new ExtractMemoryTags($this->persona, ['I love Linkin Park!']);
    $job->handle();
    
    $this->assertDatabaseHas('memory_tags', [
        'persona_id' => $this->persona->id,
        'category' => 'music',
        'value' => 'Linkin Park'
    ]);
}
```

### End-to-End Tests (Future: Browser Tests)

Test complete user workflows via browser automation (Laravel Dusk).

**Not Yet Implemented** - Placeholder for future admin dashboard E2E tests.

---

## 3. Running Tests

### Basic Commands

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run specific test file
php artisan test tests/Unit/Services/GeminiBrainServiceTest.php

# Run specific test method
php artisan test --filter=test_sanitize_prompt_removes_nsfw_content

# Run with code coverage (requires PCOV or Xdebug)
php artisan test --coverage

# Run with detailed output
php artisan test --verbose

# Stop on first failure
php artisan test --stop-on-failure

# Run in parallel (faster for large test suites)
php artisan test --parallel
```

### PHPUnit Configuration (`phpunit.xml`)

```xml
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    
    <!-- Test Environment Variables -->
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="MAIL_MAILER" value="array"/>
    </php>
</phpunit>
```

**Key Points:**
- SQLite in-memory database for speed (recreated each test)
- Synchronous queue execution (no workers needed)
- Array cache/mail drivers (no external dependencies)

### Filtering Tests by Tag

Add tags to test methods:

```php
/**
 * @test
 * @group services
 * @group gemini
 */
public function gemini_generates_chat_response() { }
```

Run by group:
```bash
php artisan test --group=services
php artisan test --exclude-group=slow
```

---

## 4. Writing Tests

### Test Structure (AAA Pattern)

```php
public function test_descriptive_name_of_what_is_being_tested()
{
    // ARRANGE - Set up test data and mocks
    $persona = Persona::factory()->create();
    $chatHistory = [['role' => 'user', 'content' => 'Hi']];
    
    GeminiBrain::shouldReceive('generateTestResponse')
        ->once()
        ->andReturn('Hello! How can I help?');
    
    // ACT - Execute the code under test
    $response = GeminiBrain::generateTestResponse(
        $persona, 
        'How are you?', 
        $chatHistory
    );
    
    // ASSERT - Verify expected outcomes
    $this->assertNotEmpty($response);
    $this->assertStringContainsString('Hello', $response);
}
```

### Naming Conventions

**Test Methods:**
- Use `test_` prefix OR `/** @test */` annotation
- Snake_case: `test_method_scenario_expectedOutcome`
- Descriptive: `test_smart_queue_reschedules_event_when_user_is_active`

**Test Classes:**
- Match class under test: `GeminiBrainService` → `GeminiBrainServiceTest`
- Suffix with `Test`: `PersonaGalleryTest`, `ProcessChatResponseTest`

### Best Practices

#### 1. Use Factories for Models

```php
// database/factories/PersonaFactory.php
public function definition()
{
    return [
        'user_id' => User::factory(),
        'name' => $this->faker->firstName(),
        'system_prompt' => 'You are a helpful assistant',
        'physical_traits' => 'Brown hair, blue eyes',
        'wake_time' => '08:00',
        'sleep_time' => '23:00',
    ];
}

// In tests
$persona = Persona::factory()->create(['name' => 'Luna']);
$personas = Persona::factory()->count(3)->create();
```

#### 2. One Assertion Per Concept

```php
// ❌ BAD - Multiple unrelated assertions
public function test_persona_creation()
{
    $persona = Persona::factory()->create();
    $this->assertNotNull($persona->id);
    $this->assertDatabaseHas('personas', ['id' => $persona->id]);
    $this->assertInstanceOf(Collection::class, $persona->memoryTags);
}

// ✅ GOOD - Separate tests for separate concepts
public function test_persona_has_id_after_creation()
{
    $persona = Persona::factory()->create();
    $this->assertNotNull($persona->id);
}

public function test_persona_stored_in_database()
{
    $persona = Persona::factory()->create(['name' => 'Luna']);
    $this->assertDatabaseHas('personas', ['name' => 'Luna']);
}

public function test_persona_has_memory_tags_relationship()
{
    $persona = Persona::factory()->create();
    $this->assertInstanceOf(Collection::class, $persona->memoryTags);
}
```

#### 3. Test Edge Cases and Failure Modes

```php
public function test_generate_image_handles_cloudflare_failure()
{
    Http::fake(['*' => Http::response(null, 500)]);
    
    $result = GeminiBrain::generateImage('Test prompt', $this->persona);
    
    $this->assertStringContainsString('Adoi, ada masalah', $result);
}

public function test_sanitize_prompt_handles_null_input()
{
    $service = app(GeminiBrainService::class);
    $result = $service->sanitizePromptForImageGeneration(null);
    
    $this->assertIsString($result);
    $this->assertNotEmpty($result);
}
```

#### 4. Use setUp() for Common Initialization

```php
protected User $user;
protected Persona $persona;

protected function setUp(): void
{
    parent::setUp();
    
    $this->user = User::factory()->create();
    $this->persona = Persona::factory()->create([
        'user_id' => $this->user->id
    ]);
}

public function test_something()
{
    // $this->persona is already available
}
```

---

## 5. Mocking External APIs

**Critical:** Never call real APIs in tests. Use Laravel's mocking facilities.

### Mocking Gemini API

```php
use App\Facades\GeminiBrain;
use Illuminate\Support\Facades\Http;

public function test_chat_response_uses_gemini()
{
    // Method 1: Mock facade
    GeminiBrain::shouldReceive('generateTestResponse')
        ->once()
        ->with($this->persona, 'Hello', [])
        ->andReturn('Hi there!');
    
    // Method 2: Mock HTTP client (for raw API calls)
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Response text']]]]
            ]
        ], 200)
    ]);
    
    $response = GeminiBrain::generateTestResponse($this->persona, 'Hello');
    
    $this->assertEquals('Hi there!', $response);
}
```

### Mocking Telegram API

```php
use App\Facades\Telegram;

public function test_send_message_to_telegram()
{
    Telegram::shouldReceive('sendMessage')
        ->once()
        ->with(123456, 'Hello user!')
        ->andReturn(true);
    
    Telegram::sendMessage(123456, 'Hello user!');
}

// Alternative: Mock HTTP for raw Telegram API calls
Http::fake([
    'api.telegram.org/*' => Http::response([
        'ok' => true,
        'result' => ['message_id' => 999]
    ], 200)
]);
```

### Mocking Cloudflare Workers AI

```php
public function test_image_generation_calls_cloudflare()
{
    Http::fake([
        'api.cloudflare.com/*/ai/run/@cf/black-forest-labs/flux-1-schnell' => 
            Http::response(file_get_contents(base_path('tests/fixtures/test-image.jpg')), 200)
    ]);
    
    $result = GeminiBrain::generateImage('A sunset', $this->persona);
    
    $this->assertStringStartsWith('[IMAGE:', $result);
}
```

### Mocking ElevenLabs TTS

```php
public function test_voice_generation_uses_elevenlabs()
{
    Http::fake([
        'api.elevenlabs.io/v1/text-to-speech/*' => 
            Http::response('fake-audio-binary-data', 200)
    ]);
    
    $result = GeminiBrain::generateVoiceMessage('Hello world', $this->persona);
    
    $this->assertStringStartsWith('[AUDIO:', $result);
}
```

### Spy Pattern (Verify Real Calls)

```php
public function test_process_chat_calls_extract_memory_tags()
{
    // Partial mock - real execution + verification
    $spy = $this->spy(ExtractMemoryTags::class);
    
    // Execute code that should dispatch the job
    dispatch(new ProcessChatResponse($this->user));
    
    // Verify the job was constructed correctly
    $spy->shouldHaveReceived('__construct')
        ->with($this->persona, Mockery::type('array'));
}
```

---

## 6. Testing Services

### GeminiBrainService Examples

#### Testing Chat Response Generation

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\GeminiBrainService;
use App\Services\ImageGeneratorManager;
use App\Models\{Persona, MemoryTag};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class GeminiBrainServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected GeminiBrainService $service;
    protected Persona $persona;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new GeminiBrainService(
            $this->mock(ImageGeneratorManager::class)
        );
        
        $this->persona = Persona::factory()->create([
            'system_prompt' => 'You are Luna, a friendly AI companion.',
            'physical_traits' => 'Long brown hair, green eyes'
        ]);
    }
    
    /** @test */
    public function generates_test_response_with_memory_context()
    {
        // Create memory tags
        MemoryTag::factory()->create([
            'persona_id' => $this->persona->id,
            'category' => 'music',
            'value' => 'Linkin Park',
            'target' => 'user'
        ]);
        
        // Mock Gemini API response
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'I know you love Linkin Park! <SPLIT> Want to talk about music?']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);
        
        $response = $this->service->generateTestResponse(
            $this->persona,
            'What do you know about me?',
            []
        );
        
        $this->assertStringContainsString('Linkin Park', $response);
        $this->assertStringContainsString('<SPLIT>', $response);
    }
    
    /** @test */
    public function sanitizes_nsfw_prompts_for_image_generation()
    {
        $unsafe = "Generate naked image with explicit content";
        $safe = $this->service->sanitizePromptForImageGeneration($unsafe);
        
        $this->assertStringNotContainsString('naked', strtolower($safe));
        $this->assertStringNotContainsString('explicit', strtolower($safe));
        $this->assertStringContainsString('portrait', strtolower($safe));
    }
    
    /** @test */
    public function builds_memory_context_correctly()
    {
        MemoryTag::factory()->create([
            'persona_id' => $this->persona->id,
            'category' => 'work',
            'value' => 'Software Engineer',
            'target' => 'user',
            'context' => 'Works at Google'
        ]);
        
        MemoryTag::factory()->create([
            'persona_id' => $this->persona->id,
            'category' => 'daily_outfit',
            'value' => 'Blue dress',
            'target' => 'self'
        ]);
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildMemoryContext');
        $method->setAccessible(true);
        
        $context = $method->invoke($this->service, $this->persona->memoryTags);
        
        $this->assertStringContainsString('USER FACTS:', $context);
        $this->assertStringContainsString('work: Software Engineer', $context);
        $this->assertStringContainsString('SELF FACTS:', $context);
        $this->assertStringContainsString('daily_outfit: Blue dress', $context);
    }
}
```

### TelegramService Examples

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;

class TelegramServiceTest extends TestCase
{
    protected TelegramService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TelegramService();
    }
    
    /** @test */
    public function sends_message_successfully()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 123]
            ], 200)
        ]);
        
        $result = $this->service->sendMessage(987654, 'Test message');
        
        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage' &&
                   $request['chat_id'] === 987654 &&
                   $request['text'] === 'Test message';
        });
    }
    
    /** @test */
    public function handles_telegram_api_errors_gracefully()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: chat not found'
            ], 400)
        ]);
        
        $result = $this->service->sendMessage(999, 'Test');
        
        $this->assertFalse($result);
    }
}
```

### SmartQueueService Examples

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SmartQueueService;
use App\Models\{User, Persona, EventSchedule};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class SmartQueueServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected SmartQueueService $service;
    protected User $user;
    protected Persona $persona;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new SmartQueueService();
        $this->user = User::factory()->create();
        $this->persona = Persona::factory()->create([
            'user_id' => $this->user->id
        ]);
    }
    
    /** @test */
    public function detects_active_user_within_15_minutes()
    {
        $this->user->update([
            'last_interaction_at' => now()->subMinutes(10)
        ]);
        
        $isActive = $this->service->isUserActive($this->user);
        
        $this->assertTrue($isActive);
    }
    
    /** @test */
    public function detects_inactive_user_after_15_minutes()
    {
        $this->user->update([
            'last_interaction_at' => now()->subMinutes(20)
        ]);
        
        $isActive = $this->service->isUserActive($this->user);
        
        $this->assertFalse($isActive);
    }
    
    /** @test */
    public function reschedules_event_when_user_is_active()
    {
        $this->user->update([
            'last_interaction_at' => now()->subMinutes(5)
        ]);
        
        $event = EventSchedule::factory()->create([
            'persona_id' => $this->persona->id,
            'scheduled_at' => now(),
            'status' => 'pending'
        ]);
        
        $executed = false;
        $this->service->processEvent($event, function() use (&$executed) {
            $executed = true;
        });
        
        $this->assertFalse($executed);
        $this->assertEquals('pending', $event->fresh()->status);
        $this->assertTrue($event->fresh()->scheduled_at->gt(now()));
    }
}
```

---

## 7. Testing Jobs

### ProcessChatResponse Example

```php
<?php

namespace Tests\Feature\Jobs;

use Tests\TestCase;
use App\Jobs\{ProcessChatResponse, ExtractMemoryTags};
use App\Models\{User, Persona, Message};
use App\Facades\{GeminiBrain, Telegram};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class ProcessChatResponseTest extends TestCase
{
    use RefreshDatabase;
    
    protected User $user;
    protected Persona $persona;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'telegram_chat_id' => 123456
        ]);
        
        $this->persona = Persona::factory()->create([
            'user_id' => $this->user->id
        ]);
    }
    
    /** @test */
    public function processes_chat_and_sends_telegram_message()
    {
        // Mock dependencies
        GeminiBrain::shouldReceive('generateTestResponse')
            ->once()
            ->andReturn('Hello! <SPLIT> How are you?');
        
        Telegram::shouldReceive('sendMessage')
            ->twice() // Two messages due to <SPLIT>
            ->andReturn(true);
        
        // Execute job
        $job = new ProcessChatResponse($this->user, null, $this->persona);
        $job->handle();
        
        // Verify messages saved
        $this->assertDatabaseHas('messages', [
            'persona_id' => $this->persona->id,
            'sender_type' => 'bot',
            'content' => 'Hello!'
        ]);
    }
    
    /** @test */
    public function dispatches_memory_extraction_job()
    {
        Queue::fake();
        
        GeminiBrain::shouldReceive('generateTestResponse')
            ->once()
            ->andReturn('Got it!');
        
        Telegram::shouldReceive('sendMessage')->andReturn(true);
        
        $job = new ProcessChatResponse($this->user, null, $this->persona);
        $job->handle();
        
        Queue::assertPushed(ExtractMemoryTags::class, function ($job) {
            return $job->persona->id === $this->persona->id;
        });
    }
    
    /** @test */
    public function handles_image_generation_tags()
    {
        GeminiBrain::shouldReceive('generateTestResponse')
            ->once()
            ->andReturn('[GENERATE_IMAGE: A beautiful sunset]');
        
        GeminiBrain::shouldReceive('processImageTags')
            ->once()
            ->with('[GENERATE_IMAGE: A beautiful sunset]', $this->persona)
            ->andReturn('[IMAGE: https://example.com/sunset.jpg]');
        
        Telegram::shouldReceive('sendPhoto')
            ->once()
            ->with(123456, 'https://example.com/sunset.jpg', null)
            ->andReturn(true);
        
        $job = new ProcessChatResponse($this->user, null, $this->persona);
        $job->handle();
    }
    
    /** @test */
    public function respects_unique_job_constraint()
    {
        Queue::fake();
        
        $job1 = new ProcessChatResponse($this->user, null, $this->persona);
        $job2 = new ProcessChatResponse($this->user, null, $this->persona);
        
        $this->assertEquals($job1->uniqueId(), $job2->uniqueId());
    }
}
```

### ExtractMemoryTags Example

```php
<?php

namespace Tests\Feature\Jobs;

use Tests\TestCase;
use App\Jobs\ExtractMemoryTags;
use App\Models\{Persona, MemoryTag};
use App\Facades\GeminiBrain;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExtractMemoryTagsTest extends TestCase
{
    use RefreshDatabase;
    
    protected Persona $persona;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->persona = Persona::factory()->create();
    }
    
    /** @test */
    public function extracts_and_stores_memory_tags()
    {
        GeminiBrain::shouldReceive('extractMemoryTags')
            ->once()
            ->andReturn([
                [
                    'category' => 'music',
                    'value' => 'Jazz',
                    'context' => 'Loves jazz music',
                    'target' => 'user',
                    'expires_at' => null
                ]
            ]);
        
        $messages = ['User: I love listening to jazz music'];
        
        $job = new ExtractMemoryTags($this->persona, $messages);
        $job->handle();
        
        $this->assertDatabaseHas('memory_tags', [
            'persona_id' => $this->persona->id,
            'category' => 'music',
            'value' => 'Jazz',
            'target' => 'user'
        ]);
    }
    
    /** @test */
    public function handles_extraction_failures_gracefully()
    {
        GeminiBrain::shouldReceive('extractMemoryTags')
            ->once()
            ->andThrow(new \Exception('API Error'));
        
        $job = new ExtractMemoryTags($this->persona, ['Test message']);
        
        // Should not throw exception
        $job->handle();
        
        // Should retry (handled by queue worker)
        $this->assertTrue($job->attempts() > 0);
    }
}
```

---

## 8. Testing Livewire Components

### Basic Livewire Test Structure

```php
<?php

namespace Tests\Feature\Livewire;

use Tests\TestCase;
use App\Livewire\TestChat;
use App\Models\{User, Persona};
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestChatTest extends TestCase
{
    use RefreshDatabase;
    
    protected User $user;
    protected Persona $persona;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->persona = Persona::factory()->create([
            'user_id' => $this->user->id
        ]);
    }
    
    /** @test */
    public function component_renders_successfully()
    {
        Livewire::actingAs($this->user)
            ->test(TestChat::class)
            ->assertStatus(200)
            ->assertSee('Test Chat');
    }
    
    /** @test */
    public function sends_message_and_updates_ui()
    {
        GeminiBrain::shouldReceive('generateTestResponse')
            ->once()
            ->andReturn('Test response');
        
        Livewire::actingAs($this->user)
            ->test(TestChat::class)
            ->set('message', 'Hello')
            ->call('sendMessage')
            ->assertSet('message', '') // Input cleared
            ->assertSee('Hello') // User message
            ->assertSee('Test response'); // Bot response
    }
    
    /** @test */
    public function validates_empty_messages()
    {
        Livewire::actingAs($this->user)
            ->test(TestChat::class)
            ->set('message', '')
            ->call('sendMessage')
            ->assertHasErrors(['message' => 'required']);
    }
}
```

### Testing MemoryBrain Component

```php
/** @test */
public function creates_new_memory_tag()
{
    Livewire::actingAs($this->user)
        ->test(MemoryBrain::class, ['persona' => $this->persona])
        ->call('openCreateModal')
        ->set('form.category', 'music')
        ->set('form.value', 'Rock')
        ->set('form.target', 'user')
        ->call('save')
        ->assertDispatched('memory-tag-created');
    
    $this->assertDatabaseHas('memory_tags', [
        'persona_id' => $this->persona->id,
        'category' => 'music',
        'value' => 'Rock'
    ]);
}

/** @test */
public function deletes_memory_tag()
{
    $tag = MemoryTag::factory()->create([
        'persona_id' => $this->persona->id
    ]);
    
    Livewire::actingAs($this->user)
        ->test(MemoryBrain::class, ['persona' => $this->persona])
        ->call('delete', $tag->id)
        ->assertDispatched('memory-tag-deleted');
    
    $this->assertDatabaseMissing('memory_tags', ['id' => $tag->id]);
}
```

---

## 9. Test Coverage

### Measuring Coverage

**Install PCOV (Faster than Xdebug):**

```bash
pecl install pcov
```

Enable in `php.ini`:
```ini
extension=pcov.so
pcov.enabled=1
```

**Generate Coverage Report:**

```bash
# HTML report
php artisan test --coverage --coverage-html=coverage

# Terminal output
php artisan test --coverage

# Minimum threshold enforcement
php artisan test --coverage --min=80
```

### Interpreting Coverage Results

```
Tests:    42 passed (128 assertions)
Duration: 1.23s

  app/Services ................................................. 72.4%
  app/Jobs ..................................................... 45.2%
  app/Models ................................................... 90.1%
  app/Livewire ................................................. 55.8%

  Total Coverage ............................................... 65.9%
```

**What to Focus On:**
- **80%+ Target:** Core business logic (services, jobs)
- **50%+ Acceptable:** UI components (Livewire)
- **90%+ Expected:** Models, DTOs, simple classes

**Coverage ≠ Quality:**
- 100% coverage doesn't mean bug-free code
- Focus on testing critical paths and edge cases
- Avoid "testing for coverage" (meaningless assertions)

### Coverage Badges (CI/CD)

Add to `README.md` after CI setup:
```markdown
![Test Coverage](https://img.shields.io/badge/coverage-85%25-brightgreen)
```

---

## 10. Common Testing Patterns

### Pattern 1: Testing with Timestamps

```php
use Illuminate\Support\Facades\Date;

public function test_event_scheduled_correctly()
{
    Date::setTestNow('2025-01-15 10:00:00');
    
    $event = EventSchedule::create([
        'scheduled_at' => now()->addHours(2),
        'status' => 'pending'
    ]);
    
    $this->assertTrue($event->scheduled_at->isFuture());
    $this->assertEquals('12:00:00', $event->scheduled_at->format('H:i:s'));
}
```

### Pattern 2: Database Assertions

```php
// Check record exists
$this->assertDatabaseHas('memory_tags', ['value' => 'Jazz']);

// Check record doesn't exist
$this->assertDatabaseMissing('memory_tags', ['value' => 'Deleted']);

// Check count
$this->assertDatabaseCount('messages', 5);

// Soft deletes
$tag->delete();
$this->assertSoftDeleted($tag);
```

### Pattern 3: Queue Testing

```php
use Illuminate\Support\Facades\Queue;

Queue::fake();

// Execute code that dispatches jobs
dispatch(new ProcessChatResponse($user));

// Assert job was pushed
Queue::assertPushed(ProcessChatResponse::class);

// Assert with closure
Queue::assertPushed(ProcessChatResponse::class, function ($job) {
    return $job->user->id === 123;
});

// Assert job NOT pushed
Queue::assertNotPushed(ExtractMemoryTags::class);

// Assert job count
Queue::assertPushed(ProcessChatResponse::class, 3);
```

### Pattern 4: Event Testing

```php
use Illuminate\Support\Facades\Event;

Event::fake();

// Execute code that fires events
event(new PersonaCreated($persona));

// Assert event was dispatched
Event::assertDispatched(PersonaCreated::class);

// Assert with closure
Event::assertDispatched(PersonaCreated::class, function ($event) {
    return $event->persona->name === 'Luna';
});
```

### Pattern 5: Storage/File Testing

```php
use Illuminate\Support\Facades\Storage;

Storage::fake('public');

// Upload file
$file = UploadedFile::fake()->image('avatar.jpg');
$persona->addMedia($file)->toMediaCollection('avatar');

// Assert file stored
Storage::disk('public')->assertExists('avatars/avatar.jpg');

// Assert file doesn't exist
Storage::disk('public')->assertMissing('old-avatar.jpg');
```

### Pattern 6: Testing Private Methods (Use Sparingly)

```php
use ReflectionClass;

public function test_private_method()
{
    $service = new GeminiBrainService($this->mock(ImageGeneratorManager::class));
    
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizePromptForImageGeneration');
    $method->setAccessible(true);
    
    $result = $method->invoke($service, 'unsafe prompt');
    
    $this->assertStringContainsString('safe', $result);
}
```

**Note:** Testing private methods indicates potential design issue. Consider extracting to public method or separate class.

---

## 11. Debugging Failed Tests

### Verbose Output

```bash
php artisan test --verbose
```

### Dump & Die in Tests

```php
public function test_something()
{
    $response = $this->get('/api/endpoint');
    
    // Dump response
    dump($response->json());
    
    // Or die with content
    dd($response->getContent());
}
```

### Inspect Database State

```php
public function test_something()
{
    // Create records
    $persona = Persona::factory()->create();
    
    // Debug database
    $this->artisan('db:show'); // Laravel 10+
    
    // Or raw query
    dd(\DB::table('personas')->get());
}
```

### View Rendered Content

```php
public function test_livewire_component()
{
    $component = Livewire::test(TestChat::class);
    
    // Dump HTML
    dd($component->html());
    
    // Dump component state
    dd($component->get('messages'));
}
```

### PHPUnit Debug Options

```bash
# Stop on failure
php artisan test --stop-on-failure

# Show deprecations
php artisan test --display-deprecations

# Show warnings
php artisan test --display-warnings

# Show incomplete tests
php artisan test --display-incomplete
```

### Log Files

Tests run with `APP_ENV=testing`, check logs:
```
storage/logs/laravel-testing.log
```

Enable query logging:
```php
protected function setUp(): void
{
    parent::setUp();
    
    \DB::enableQueryLog();
}

public function test_something()
{
    // Run test
    
    dd(\DB::getQueryLog()); // See all SQL queries
}
```

---

## 12. CI/CD Integration

### GitHub Actions Workflow (Placeholder)

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: password
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv, pcov
          coverage: pcov
      
      - name: Install Composer Dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader
      
      - name: Install NPM Dependencies
        run: npm ci
      
      - name: Build Assets
        run: npm run build
      
      - name: Copy Environment File
        run: cp .env.example .env
      
      - name: Generate Application Key
        run: php artisan key:generate
      
      - name: Directory Permissions
        run: chmod -R 777 storage bootstrap/cache
      
      - name: Run Tests with Coverage
        run: php artisan test --coverage --min=70
      
      - name: Upload Coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          fail_ci_if_error: false
```

### Pre-Commit Hook

Create `.git/hooks/pre-commit`:

```bash
#!/bin/sh

echo "Running tests before commit..."

php artisan test --stop-on-failure

if [ $? -ne 0 ]; then
    echo "Tests failed! Commit aborted."
    exit 1
fi

echo "All tests passed! Proceeding with commit."
exit 0
```

Make executable:
```bash
chmod +x .git/hooks/pre-commit
```

---

## Summary Checklist

Before deploying to production, ensure:

- [ ] **Core services tested:** GeminiBrain, Telegram, SmartQueue
- [ ] **Critical jobs tested:** ProcessChatResponse, ExtractMemoryTags
- [ ] **External APIs mocked:** No real API calls in tests
- [ ] **Database state verified:** assertDatabaseHas for state changes
- [ ] **Edge cases covered:** Null inputs, API failures, timeouts
- [ ] **70%+ coverage:** For core business logic
- [ ] **CI/CD pipeline:** Automated tests on every commit
- [ ] **All tests pass:** `php artisan test` exits 0

---

## Resources

- **Laravel Testing Docs:** https://laravel.com/docs/12.x/testing
- **PHPUnit Documentation:** https://phpunit.de/documentation.html
- **Livewire Testing:** https://livewire.laravel.com/docs/testing
- **Mockery Documentation:** http://docs.mockery.io/
- **This Project's Tests:** `tests/Feature/`, `tests/Unit/`

---

**Last Updated:** December 16, 2025  
**Maintained By:** Development Team  
**Related Docs:** [TECHNICAL_DEBT.md](../project/TECHNICAL_DEBT.md), [CONTRIBUTING.md](../../CONTRIBUTING.md)
