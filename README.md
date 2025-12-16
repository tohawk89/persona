# 🤖 AI Virtual Companion

> Build meaningful AI relationships with intelligent personas that remember, learn, and interact naturally via Telegram.

[![Laravel](https://img.shields.io/badge/Laravel-12-red)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

**AI Virtual Companion** is a Laravel 12 application that creates intelligent AI-powered personas using Gemini 2.5 Flash. Each persona has its own personality, memory system, and can communicate naturally via Telegram with text, images, and voice messages.

---

## ✨ What Makes This Special?

- 🧠 **Memory-Based Conversations** - Personas remember facts about you and themselves, creating continuity
- 📅 **Smart Event Scheduling** - AI generates daily plans and sends proactive messages throughout the day
- 🎭 **Multi-Persona Support** - Create multiple AI companions, each with unique personalities
- 📸 **Image Generation** - Personas can generate and send images of themselves using Cloudflare Workers AI
- 🎤 **Voice Messages** - Text-to-speech integration with ElevenLabs for natural voice responses
- ⚡ **Active Conversation Detection** - Smart queue reschedules events when you're actively chatting

---

## 🚀 Quick Start (5 Minutes)

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8+
- [Laravel Herd](https://herd.laravel.com) (recommended) or Laravel Valet

### Installation

```bash
# Clone the repository
git clone https://github.com/tohawk89/persona.git
cd persona

# Run setup script (installs deps, generates key, migrates DB, builds frontend)
composer run setup

# Create admin user
php artisan app:create-admin

# Link storage for avatars
php artisan storage:link

# Verify all services are configured
php artisan app:check
```

### Environment Configuration

Copy `.env.example` to `.env` and configure:

```env
# Required API Keys
GEMINI_API_KEY=your_gemini_api_key
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
CLOUDFLARE_ACCOUNT_ID=your_cloudflare_account_id
CLOUDFLARE_API_TOKEN=your_cloudflare_api_token
ELEVENLABS_API_KEY=your_elevenlabs_api_key
ELEVENLABS_VOICE_ID=your_voice_id
```

See [docs/guides/development-setup.md](docs/guides/development-setup.md) for detailed setup instructions.

### Run Development Server

```bash
# Runs: serve, queue:listen, pail (logs), and vite in parallel
composer run dev
```

Access the admin dashboard at `http://persona.test` (Herd) or `http://localhost:8000`

---

## 🎯 Core Features

### Intelligent Personas

Create AI companions with customizable:
- **Personality** - Define system prompts with character traits
- **Appearance** - Physical descriptions for consistent image generation
- **Schedule** - Wake/sleep times for daily routines
- **Memory** - Automatic fact extraction from conversations
- **Voice** - Unique ElevenLabs voice per persona

### Memory System

Personas automatically extract and remember:
- **User facts** - Your preferences, work, hobbies, relationships
- **Self facts** - Persona's daily outfit, mood, experiences
- **Context** - When and how facts were learned
- **Importance** - AI-ranked relevance (1-10 scale)

### Smart Scheduling

- **Daily Plans** - AI generates 5-7 contextual events each day
- **Just-in-Time Generation** - Events created on-demand for flexibility
- **Active Detection** - Reschedules if you're chatting (15-min window)
- **Time-Based Logic** - Daytime vs nighttime outfit awareness

### Media Generation

- **Images** - Flux-1-Schnell via Cloudflare Workers AI
- **Voice Notes** - Natural TTS with ElevenLabs
- **Character Consistency** - Physical traits + memory context in every generation

---

## 🏗️ Architecture Highlights

### Service Layer Pattern

Three core singleton services power the application:

```php
GeminiBrain::chat($messages, $systemPrompt);           // AI conversations
Telegram::sendMessage($chatId, $text);                 // Telegram Bot API
SmartQueue::processEvent($event, $callback);           // Intelligent scheduling
```

### Data Flow

```
Telegram → Update last_interaction_at → Queue Job → 
AI Response + Memory Context → Process [GENERATE_IMAGE:] tags → 
Send to Telegram → Save to messages → Extract Memory Tags
```

### Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 12, PHP 8.2 |
| **Frontend** | Livewire 3, Alpine.js, Tailwind CSS |
| **Database** | MySQL 8+ |
| **Queue** | Database (dev), Redis (production) |
| **AI** | Gemini 2.5 Flash |
| **Messaging** | Telegram Bot API |
| **Images** | Cloudflare Workers AI (Flux-1-Schnell) |
| **Voice** | ElevenLabs TTS |
| **Storage** | Spatie MediaLibrary |

---

## 📚 Documentation

Comprehensive documentation is available in the [`docs/`](docs/) directory:

### Getting Started
- **[Quick Reference](docs/guides/QUICK_REFERENCE.md)** - Common tasks and patterns
- **[Development Setup](docs/guides/development-setup.md)** - Detailed setup instructions
- **[Admin Dashboard Guide](docs/guides/ADMIN_DASHBOARD_GUIDE.md)** - Using the web interface

### Architecture
- **[Architecture Report](docs/architecture/ARCHITECTURE_REPORT.md)** - System design overview
- **[Service Layer Summary](docs/architecture/SERVICE_LAYER_SUMMARY.md)** - Service pattern details
- **[Services API Reference](docs/architecture/SERVICES_README.md)** - Complete method documentation

### Advanced Topics
- **[JIT Event Generation](docs/guides/JIT_EVENT_GENERATION.md)** - Just-in-time scheduling system
- **[Multi-Persona Guide](docs/guides/MULTI_PERSONA_GUIDE.md)** - Managing multiple personas
- **[Image Generator Drivers](docs/reference/IMAGE_GENERATOR_DRIVERS.md)** - Image generation options

### Project Management
- **[Bug Catalog](BUGS.md)** - Known issues and status
- **[Technical Debt](TECHNICAL_DEBT.md)** - Architecture assessment and roadmap

---

## 🤝 Contributing

We welcome contributions! This project is in active development and there are many opportunities to help.

### Quick Contribution Guide

1. **Fork** the repository
2. **Clone** your fork: `git clone https://github.com/YOUR_USERNAME/persona.git`
3. **Create a branch**: `git checkout -b feature/your-feature-name`
4. **Make changes** and commit: `git commit -m "feat: add new feature"`
5. **Push** to your fork: `git push origin feature/your-feature-name`
6. **Open a Pull Request** on GitHub

See **[CONTRIBUTING.md](CONTRIBUTING.md)** for detailed guidelines, code standards, and PR process.

### Good First Issues

New to the project? Start here:

1. **[BUG-004]** Add loading feedback for image generation (Easy, ~30min)
2. **[BUG-003]** Fix gallery pagination errors (Frontend debugging)
3. **[BUG-005]** Fix layout inconsistency between pages (Page-by-page refactoring)
4. **[DEBT-006]** Extract timeout constants to config (Easy refactoring)
5. **[TEST-001]** Add basic service layer test (Learn the codebase)

See [BUGS.md](BUGS.md) and [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md) for full details.

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/PersonaGalleryTest.php
```

**Current Coverage:** ~2% (34 tests, mostly auth + gallery)  
**Target:** 60% minimum for production

See [docs/guides/testing-guide.md](docs/guides/testing-guide.md) for testing best practices.

---

## 🚀 Deployment

⚠️ **Not production-ready yet.** See [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md) for blockers:

- Test coverage too low (<5%)
- Database queue not scalable
- No CI/CD pipeline
- No monitoring/error tracking

**Roadmap to Production:** 3-phase plan in [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md#15-remediation-roadmap)

---

## 📊 Project Status

**Current Phase:** Developer Onboarding & Technical Debt Cleanup (EPIC-002)

- ✅ Documentation reorganized
- ✅ Bug catalog created (7 bugs, 3 critical)
- ✅ Architecture assessed (Grade: B-, 35+ debt items)
- 🔄 Onboarding documentation (in progress)
- ⏳ Quick win bug fixes (next)

See [docs/sprint-artifacts/](docs/sprint-artifacts/) for sprint planning and progress.

---

## 🙏 Acknowledgments

Built with amazing open-source tools:

- [Laravel](https://laravel.com) - PHP framework
- [Livewire](https://livewire.laravel.com) - Reactive components
- [Gemini PHP SDK](https://github.com/google-gemini-php/laravel) - AI integration
- [Telegram Bot SDK](https://github.com/irazasyed/telegram-bot-sdk) - Messaging
- [Spatie MediaLibrary](https://spatie.be/docs/laravel-medialibrary) - File management

---

## 📜 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

---

## 🔗 Links

- **Documentation:** [docs/](docs/)
- **Contributing:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **Bug Reports:** [BUGS.md](BUGS.md)
- **Technical Debt:** [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md)
- **Issue Tracker:** [GitHub Issues](https://github.com/tohawk89/persona/issues)

---

**Questions?** Open a [GitHub Discussion](https://github.com/tohawk89/persona/discussions) or check the [docs/](docs/)!
