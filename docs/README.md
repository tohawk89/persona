# AI Virtual Companion - Documentation

Welcome to the AI Virtual Companion documentation hub. This Laravel application creates intelligent AI-powered virtual companions using Gemini 2.5 Flash with memory-based conversations, intelligent event scheduling, and Telegram bot integration.

## 📚 Documentation Structure

### 🏗️ Architecture
Deep dives into system design, service patterns, and technical architecture.

- [Architecture Report](architecture/ARCHITECTURE_REPORT.md) - Comprehensive system architecture overview
- [Brain Service Structure](architecture/BRAIN_SERVICE_STRUCTURE.md) - GeminiBrain service organization and code structure
- [Service Layer Summary](architecture/SERVICE_LAYER_SUMMARY.md) - Service layer pattern implementation
- [Services README](architecture/SERVICES_README.md) - Complete API reference for core services

### 📖 Guides
Step-by-step guides for using and developing features.

- [Admin Dashboard Guide](guides/ADMIN_DASHBOARD_GUIDE.md) - Using the admin dashboard features
- [Quick Reference](guides/QUICK_REFERENCE.md) - Common usage patterns and quick tips
- [Multi-Persona Guide](guides/MULTI_PERSONA_GUIDE.md) - Working with multiple personas
- [JIT Event Generation](guides/JIT_EVENT_GENERATION.md) - Just-in-time event generation system
- [JIT Quick Reference](guides/JIT_QUICK_REFERENCE.md) - Quick reference for JIT features

### 🔍 Reference
Technical reference documentation and configuration guides.

- [Codebase Overview](reference/CODEBASE_OVERVIEW.md) - High-level codebase structure and conventions
- [Image Generator Drivers](reference/IMAGE_GENERATOR_DRIVERS.md) - Image generation integration details

### 🎨 Features
Feature specifications and UI designs.

- [Persona Gallery UI](PERSONA_GALLERY_UI.md) - Persona gallery feature specification

### 🚀 Sprint Artifacts
Sprint planning documents, epics, and stories.

- [Sprint Artifacts](sprint-artifacts/) - Current and historical sprint deliverables

### 📋 Project Management
Track bugs, technical debt, and good first issues.

- [Bug Catalog](project/BUGS.md) - Known issues with severity, impact, and status
- [Technical Debt](project/TECHNICAL_DEBT.md) - Architecture assessment and remediation roadmap
- [Good First Issues](project/GOOD_FIRST_ISSUES.md) - Beginner-friendly tasks to get started

## 🚀 Quick Start

New to the project? Start here:

1. **First Time Setup**: See the [root README](../README.md) for installation and setup
2. **Understanding Architecture**: Read [Architecture Report](architecture/ARCHITECTURE_REPORT.md)
3. **Using Admin Dashboard**: Check [Admin Dashboard Guide](guides/ADMIN_DASHBOARD_GUIDE.md)
4. **Development Patterns**: Review [Quick Reference](guides/QUICK_REFERENCE.md)

## 🤝 Contributing

Want to contribute? Check out:

- **CONTRIBUTING.md** - Coming soon (Epic-002, Story 4)
- **Development Setup Guide** - Coming soon (Epic-002, Story 4)
- **Issue Templates** - Coming soon (Epic-002, Story 4)

## 📦 Tech Stack

- **Framework**: Laravel 12
- **AI**: Google Gemini 2.5 Flash
- **Integrations**: Telegram Bot API, ElevenLabs TTS, Cloudflare Workers AI
- **Frontend**: Livewire, Alpine.js, Tailwind CSS
- **Media**: Spatie MediaLibrary
- **Database**: MySQL

## 🏛️ Core Services

The application is built around three singleton services:

1. **GeminiBrainService** - Gemini AI integration, memory extraction, image/voice generation
2. **TelegramService** - Telegram Bot API wrapper
3. **SmartQueueService** - Intelligent event scheduling with active conversation detection

Access via facades: `GeminiBrain::`, `Telegram::`, `SmartQueue::`

## 📞 Support

- **Issues**: Report bugs in GitHub Issues
- **Discussions**: Ask questions in GitHub Discussions
- **Documentation**: You're already here! 🎉

---

**Last Updated**: December 16, 2025  
**Epic**: EPIC-002 (Developer Onboarding & Technical Debt Cleanup)
