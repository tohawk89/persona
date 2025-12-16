# Contributing to AI Virtual Companion

Thank you for your interest in contributing! This document provides guidelines for contributing to the AI Virtual Companion project.

---

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing Requirements](#testing-requirements)
- [Pull Request Process](#pull-request-process)
- [Issue Guidelines](#issue-guidelines)
- [Good First Issues](#good-first-issues)

---

## 📜 Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inspiring community for all. Please be respectful, constructive, and kind in all interactions.

### Expected Behavior

- Use welcoming and inclusive language
- Be respectful of differing viewpoints
- Accept constructive criticism gracefully
- Focus on what's best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Harassment, trolling, or discriminatory comments
- Publishing others' private information
- Other conduct which could reasonably be considered inappropriate

---

## 🚀 Getting Started

### Prerequisites

Before contributing, ensure you have:

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ and npm
- MySQL 8+
- Git
- [Laravel Herd](https://herd.laravel.com) (recommended) or Laravel Valet
- A code editor (VS Code, PHPStorm recommended)

### Initial Setup

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/persona.git
   cd persona
   ```

3. **Add upstream remote**:
   ```bash
   git remote add upstream https://github.com/tohawk89/persona.git
   ```

4. **Run setup**:
   ```bash
   composer run setup
   ```

5. **Configure environment**:
   - Copy `.env.example` to `.env`
   - Add required API keys (see [docs/guides/development-setup.md](docs/guides/development-setup.md))
   - Run `php artisan app:check` to verify configuration

6. **Create admin user**:
   ```bash
   php artisan app:create-admin
   ```

7. **Link storage**:
   ```bash
   php artisan storage:link
   ```

### Development Server

```bash
# Run all dev services (web server, queue worker, logs, vite)
composer run dev
```

Access the app at `http://persona.test` (Herd) or `http://localhost:8000`

---

## 🔄 Development Workflow

### Branch Strategy

We use a simplified Git flow:

- **`main`** - Production-ready code
- **`feature/feature-name`** - New features
- **`bugfix/bug-name`** - Bug fixes
- **`refactor/component-name`** - Code improvements
- **`docs/topic-name`** - Documentation updates

### Creating a Branch

```bash
# Update your local main
git checkout main
git pull upstream main

# Create feature branch
git checkout -b feature/add-memory-consolidation

# Or bug fix
git checkout -b bugfix/fix-gallery-pagination
```

### Making Changes

1. **Write code** following our [coding standards](#coding-standards)
2. **Add tests** for new features or bug fixes
3. **Run tests** to ensure nothing breaks:
   ```bash
   php artisan test
   ```
4. **Check code style**:
   ```bash
   ./vendor/bin/pint
   ```
5. **Commit your changes** with clear messages:
   ```bash
   git add .
   git commit -m "feat: add memory consolidation feature"
   ```

### Commit Message Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting, no logic change)
- `refactor:` - Code refactoring
- `test:` - Adding or updating tests
- `chore:` - Maintenance tasks

**Examples:**
```bash
git commit -m "feat: add voice message support to personas"
git commit -m "fix: resolve gallery pagination error (BUG-003)"
git commit -m "docs: update installation instructions"
git commit -m "test: add service layer tests for GeminiBrain"
```

### Pushing Changes

```bash
# Push to your fork
git push origin feature/add-memory-consolidation
```

---

## 💻 Coding Standards

### PHP Standards (PSR-12)

We follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style.

**Automatic Formatting:**
```bash
# Fix all code style issues
./vendor/bin/pint

# Check without fixing
./vendor/bin/pint --test
```

### Key Conventions

#### Type Hints
Always use type hints for parameters and return types:

```php
// ✅ Good
public function generateDailyPlan(Collection $memoryTags, string $systemPrompt): array
{
    // ...
}

// ❌ Bad
public function generateDailyPlan($memoryTags, $systemPrompt)
{
    // ...
}
```

#### Naming Conventions

- **Classes**: `StudlyCase` (e.g., `GeminiBrainService`)
- **Methods**: `camelCase` (e.g., `extractMemoryTags()`)
- **Variables**: `camelCase` (e.g., `$memoryTags`)
- **Constants**: `UPPER_SNAKE_CASE` (e.g., `MAX_RETRY_ATTEMPTS`)

#### Service Layer Pattern

Business logic belongs in **Services**, not Controllers:

```php
// ✅ Good - Logic in Service
class PersonaController
{
    public function generatePlan(Persona $persona)
    {
        $plan = GeminiBrain::generateDailyPlan(
            $persona->memoryTags,
            $persona->system_prompt,
            $persona->wake_time,
            $persona->sleep_time
        );
        
        return response()->json($plan);
    }
}

// ❌ Bad - Logic in Controller
class PersonaController
{
    public function generatePlan(Persona $persona)
    {
        $gemini = new Gemini(config('services.gemini.api_key'));
        $result = $gemini->chat()->create([/* complex logic */]);
        // ...
    }
}
```

#### Error Handling

- Use try-catch blocks for external API calls
- Log errors with context:
  ```php
  Log::error('GeminiBrain: Failed to generate plan', [
      'persona_id' => $persona->id,
      'error' => $e->getMessage(),
  ]);
  ```
- Return user-friendly error messages, not exceptions

#### Comments

- Use docblocks for public methods
- Explain **why**, not **what** in inline comments
- Avoid obvious comments

```php
// ✅ Good
/**
 * Generate daily event plan for persona.
 * Uses Gemini AI with JSON output mode for structured data.
 *
 * @param Collection $memoryTags Persona's memory context
 * @param string $systemPrompt Persona's character definition
 * @param string $wakeTime Wake time in HH:MM format
 * @param string $sleepTime Sleep time in HH:MM format
 * @return array Array with 'events', 'daily_outfit', 'night_outfit' keys
 */
public function generateDailyPlan(/* ... */)

// ❌ Bad
// This function generates a daily plan
public function generateDailyPlan(/* ... */)
```

### Blade Templates

- Use components for reusable UI elements
- Keep logic minimal (use Livewire components for complex UI)
- Follow Tailwind CSS conventions
- Use `@auth`, `@can` directives for authorization

### JavaScript/Alpine.js

- Keep JavaScript minimal (Livewire handles most reactivity)
- Use Alpine.js for simple interactions
- Follow Vue.js naming conventions for Alpine components

---

## 🧪 Testing Requirements

### Test Coverage Goals

- **Minimum:** 60% overall coverage
- **Services:** 80% coverage (critical business logic)
- **Jobs:** 70% coverage
- **Livewire:** 50% coverage
- **Models:** 60% coverage

### Writing Tests

#### Unit Tests

Test individual methods in isolation:

```php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GeminiBrainService;

class GeminiBrainServiceTest extends TestCase
{
    public function test_sanitizes_nsfw_prompts(): void
    {
        $service = new GeminiBrainService();
        
        $unsafe = "Generate explicit content";
        $safe = $service->sanitizePromptForImageGeneration($unsafe);
        
        $this->assertNotEquals($unsafe, $safe);
        $this->assertStringNotContainsString('explicit', $safe);
    }
}
```

#### Feature Tests

Test complete workflows:

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Persona;

class PersonaChatTest extends TestCase
{
    public function test_user_can_send_message_to_persona(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create(['user_id' => $user->id]);
        
        $response = $this->actingAs($user)
            ->post("/personas/{$persona->id}/chat", [
                'message' => 'Hello!',
            ]);
        
        $response->assertOk();
        $this->assertDatabaseHas('messages', [
            'persona_id' => $persona->id,
            'sender_type' => 'user',
            'content' => 'Hello!',
        ]);
    }
}
```

#### Mocking External APIs

Always mock external services:

```php
use Illuminate\Support\Facades\Http;

public function test_gemini_api_failure_is_handled(): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);
    
    $service = app(GeminiBrainService::class);
    $result = $service->chat([], 'Test prompt');
    
    $this->assertNull($result);
}
```

### Running Tests

```bash
# All tests
php artisan test

# Specific test file
php artisan test tests/Feature/PersonaGalleryTest.php

# With coverage
php artisan test --coverage

# Stop on failure
php artisan test --stop-on-failure
```

### Test Quality Checklist

- [ ] Tests are isolated (no shared state between tests)
- [ ] External APIs are mocked
- [ ] Assertions are meaningful
- [ ] Test names describe what they test
- [ ] Edge cases are covered
- [ ] Happy path and error cases both tested

---

## 🔍 Pull Request Process

### Before Submitting

1. **Sync with upstream**:
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

2. **Run full test suite**:
   ```bash
   php artisan test
   ```

3. **Fix code style**:
   ```bash
   ./vendor/bin/pint
   ```

4. **Update documentation** if needed

5. **Squash commits** if you have many small commits:
   ```bash
   git rebase -i HEAD~5  # Last 5 commits
   ```

### Submitting PR

1. **Push to your fork**:
   ```bash
   git push origin feature/your-feature
   ```

2. **Open Pull Request** on GitHub from your fork to `main`

3. **Fill PR template** with:
   - **Description** - What does this PR do?
   - **Related Issue** - Fixes #123
   - **Type** - Feature, Bug Fix, Refactor, Docs
   - **Testing** - How did you test this?
   - **Screenshots** - If UI changes
   - **Checklist** - Confirm all requirements met

### PR Template Example

```markdown
## Description
Adds voice message support for personas using ElevenLabs TTS API.

## Related Issue
Closes #45

## Type of Change
- [x] New feature
- [ ] Bug fix
- [ ] Refactoring
- [ ] Documentation

## Testing
- Added unit tests for AudioService
- Added integration test for voice message generation
- Manually tested with live ElevenLabs API

## Checklist
- [x] Code follows project style guidelines
- [x] Tests added and passing
- [x] Documentation updated
- [x] No breaking changes
```

### Review Process

1. **Automated checks** run (tests, code style)
2. **Maintainer review** (usually within 48 hours)
3. **Address feedback** if requested
4. **Approval** from at least one maintainer
5. **Merge** to main

### After Merge

1. **Delete your branch**:
   ```bash
   git branch -d feature/your-feature
   git push origin --delete feature/your-feature
   ```

2. **Update your local main**:
   ```bash
   git checkout main
   git pull upstream main
   ```

---

## 🐛 Issue Guidelines

### Before Creating an Issue

1. **Search existing issues** - Your issue might already exist
2. **Check documentation** - Maybe it's not a bug?
3. **Reproduce the bug** - Can you consistently reproduce it?

### Bug Reports

Use the [Bug Report template](.github/ISSUE_TEMPLATE/bug_report.md):

**Include:**
- Clear description of the bug
- Steps to reproduce
- Expected vs actual behavior
- Environment details (PHP version, Laravel version, OS)
- Error logs if applicable
- Screenshots if UI-related

**Example:**
```markdown
### Bug Description
Gallery pagination returns 404 error when clicking "Next"

### Steps to Reproduce
1. Go to /personas/1/gallery
2. Click "Next" button at bottom
3. 404 error appears

### Expected Behavior
Should load next page of images

### Actual Behavior
404 error: Route not found

### Environment
- PHP: 8.2.13
- Laravel: 12.0
- Browser: Chrome 120

### Error Log
```
Route [personas.gallery.next] not defined
```
```

### Feature Requests

Use the [Feature Request template](.github/ISSUE_TEMPLATE/feature_request.md):

**Include:**
- Problem you're trying to solve
- Proposed solution
- Alternative solutions considered
- Additional context

---

## 🎯 Good First Issues

New contributors should start with issues labeled `good first issue`:

### Current Good First Issues

1. **[BUG-004] Add loading feedback for image generation**
   - **Difficulty:** Easy
   - **Time:** ~30 minutes
   - **Skills:** Basic Gemini API understanding
   - **Description:** Add persona-driven loading message ("Wait, just open my camera") before image generation
   - **File:** `app/Services/GeminiBrainService.php`

2. **[BUG-003] Fix gallery pagination errors**
   - **Difficulty:** Easy
   - **Time:** ~2 hours
   - **Skills:** Livewire, debugging
   - **Description:** Debug and fix PersonaGallery pagination
   - **File:** `app/Livewire/PersonaGallery.php`

3. **[BUG-005] Fix layout inconsistency**
   - **Difficulty:** Easy
   - **Time:** ~3 hours (can tackle page-by-page)
   - **Skills:** Tailwind CSS, Blade templates
   - **Description:** Standardize layout patterns across admin pages
   - **Files:** `resources/views/livewire/*.blade.php`

4. **[DEBT-006] Extract timeout constants to config**
   - **Difficulty:** Easy
   - **Time:** ~15 minutes
   - **Skills:** Laravel configuration
   - **Description:** Move hard-coded timeouts (15 min, 30 min, 180s) to config files
   - **Files:** Various services and jobs

5. **[TEST-001] Add basic service layer test**
   - **Difficulty:** Medium
   - **Time:** ~1 hour
   - **Skills:** PHPUnit, mocking
   - **Description:** Write first test for GeminiBrainService::sanitizePromptForImageGeneration()
   - **File:** Create `tests/Unit/Services/GeminiBrainServiceTest.php`

See [BUGS.md](BUGS.md) and [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md) for detailed descriptions.

---

## 📚 Additional Resources

### Documentation
- [Architecture Report](docs/architecture/ARCHITECTURE_REPORT.md)
- [Service API Reference](docs/architecture/SERVICES_README.md)
- [Development Setup Guide](docs/guides/development-setup.md)
- [Testing Guide](docs/guides/testing-guide.md)

### External Resources
- [Laravel Documentation](https://laravel.com/docs)
- [Livewire Documentation](https://livewire.laravel.com)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [PSR-12 Coding Style](https://www.php-fig.org/psr/psr-12/)
- [Conventional Commits](https://www.conventionalcommits.org/)

---

## 💬 Getting Help

- **GitHub Discussions** - Ask questions, share ideas
- **GitHub Issues** - Report bugs, request features
- **Documentation** - Check [docs/](docs/) first

---

## 🙏 Thank You!

Every contribution, no matter how small, helps make this project better. Thank you for being part of the AI Virtual Companion community!

---

**Questions about contributing?** Open a [GitHub Discussion](https://github.com/tohawk89/persona/discussions) or check existing issues for examples.
