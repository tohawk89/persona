# Development Setup Guide

Complete guide to setting up the AI Virtual Companion development environment from scratch.

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation](#installation)
3. [API Key Configuration](#api-key-configuration)
4. [Database Setup](#database-setup)
5. [Frontend Build Setup](#frontend-build-setup)
6. [Storage Configuration](#storage-configuration)
7. [Queue Worker Setup](#queue-worker-setup)
8. [Verification](#verification)
9. [Troubleshooting](#troubleshooting)
10. [IDE Setup](#ide-setup)
11. [Development Tools](#development-tools)

---

## System Requirements

### Core Dependencies
- **PHP 8.2+** with extensions:
  - OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo, GD
- **Composer 2.x** - PHP dependency manager
- **Node.js 18+** and **npm 9+** - Frontend build tools
- **MySQL 8.0+** or **MariaDB 10.3+** - Database
- **Redis** (optional) - For queue and cache drivers

### Local Development Environment
Choose one:
- **[Laravel Herd](https://herd.laravel.com/)** (Recommended for Windows/Mac) - Zero-config PHP environment
- **[Laravel Valet](https://laravel.com/docs/valet)** (Mac/Linux) - Lightweight development environment
- **[Laravel Sail](https://laravel.com/docs/sail)** (Docker-based) - Cross-platform containerized environment
- **XAMPP/WAMP** (Windows) - Traditional Apache/MySQL stack

### Recommended System Specs
- **RAM**: 8GB minimum, 16GB recommended
- **Storage**: 2GB free space for dependencies and media
- **OS**: Windows 10/11, macOS 12+, or Ubuntu 20.04+

---

## Installation

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/persona.git
cd persona
```

### 2. Quick Setup (Automated)
The project includes a setup script that handles most configuration:

```bash
composer run setup
```

This command will:
- Install PHP dependencies via Composer
- Generate application key
- Run database migrations
- Build frontend assets

### 3. Manual Setup (Alternative)
If you prefer step-by-step control:

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend assets
npm run build
```

---

## API Key Configuration

### Environment Variables
Edit `.env` file and add your API credentials:

```env
APP_NAME="AI Virtual Companion"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=persona
DB_USERNAME=root
DB_PASSWORD=

# Queue Configuration
QUEUE_CONNECTION=database
```

### Required API Keys

#### 1. Google Gemini API
**Purpose**: AI conversation and planning engine (Gemini 2.5 Flash)

**Get Your Key**:
1. Visit [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Sign in with Google account
3. Click "Create API Key" → "Create API key in new project"
4. Copy the key

**Configuration**:
```env
GEMINI_API_KEY=your_gemini_api_key_here
```

#### 2. Telegram Bot API
**Purpose**: Telegram chat integration

**Get Your Token**:
1. Open Telegram and search for [@BotFather](https://t.me/botfather)
2. Send `/newbot` command
3. Follow prompts to name your bot
4. Copy the HTTP API token

**Configuration**:
```env
TELEGRAM_BOT_TOKEN=123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook
```

**Note**: Webhook URL only needed for production. Local development uses polling (handled by queue worker).

**Get Your Chat ID** (for testing):
```bash
# Send a message to your bot in Telegram first, then run:
curl https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates
```

Look for `"chat":{"id":123456789}` in the response.

#### 3. Cloudflare Workers AI
**Purpose**: Image generation via Flux-1-Schnell model

**Get Your Credentials**:
1. Sign up at [Cloudflare](https://dash.cloudflare.com/)
2. Go to **Workers & Pages** → **Overview**
3. Find your **Account ID** in the sidebar
4. Go to **My Profile** → **API Tokens**
5. Create token with **Workers AI:Edit** permission

**Configuration**:
```env
CLOUDFLARE_ACCOUNT_ID=your_account_id_here
CLOUDFLARE_API_TOKEN=your_api_token_here
```

**Pricing**: [Free tier includes 10,000 Neurons/day](https://developers.cloudflare.com/workers-ai/platform/pricing/)

#### 4. ElevenLabs API
**Purpose**: Text-to-speech voice synthesis

**Get Your Credentials**:
1. Sign up at [ElevenLabs](https://elevenlabs.io/)
2. Go to **Profile** → **API Key**
3. Copy the API key
4. Go to **Voices** → Select a voice → Copy the Voice ID

**Configuration**:
```env
ELEVENLABS_API_KEY=your_elevenlabs_api_key
ELEVENLABS_VOICE_ID=21m00Tcm4TlvDq8ikWAM  # Example: Rachel voice
```

**Pricing**: [Free tier includes 10,000 characters/month](https://elevenlabs.io/pricing)

### Testing API Configuration
After adding all keys, verify connectivity:

```bash
php artisan app:check
```

Expected output:
```
✓ Database connection: OK
✓ Cloudflare Workers AI: OK
✓ ElevenLabs API: OK
✓ Gemini API: OK
✓ Telegram Bot API: OK
```

---

## Database Setup

### MySQL Configuration

#### Using Herd (Recommended)
Herd includes MySQL by default. Create your database:

```bash
# Open MySQL CLI (Herd includes MySQL in PATH)
mysql -u root

# In MySQL prompt:
CREATE DATABASE persona CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

#### Using MySQL Manually
```bash
# Linux/Mac
mysql -u root -p

# Windows (if MySQL installed separately)
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p
```

Then create database:
```sql
CREATE DATABASE persona CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'persona_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON persona.* TO 'persona_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Update `.env`:
```env
DB_DATABASE=persona
DB_USERNAME=persona_user
DB_PASSWORD=secure_password
```

### Run Migrations
Create all database tables:

```bash
php artisan migrate
```

This creates:
- `users` - User accounts and Telegram chat IDs
- `personas` - AI companion configurations
- `memory_tags` - Fact storage (user/persona memories)
- `event_schedules` - Scheduled message events
- `messages` - Chat history
- `media` - Generated images and voice notes (Spatie MediaLibrary)

### Seeders (Optional)
Generate sample data for testing:

```bash
php artisan db:seed
```

### Database Migrations Reference
View migration status:
```bash
php artisan migrate:status
```

Rollback last migration:
```bash
php artisan migrate:rollback
```

Fresh database (⚠️ destroys all data):
```bash
php artisan migrate:fresh
```

---

## Frontend Build Setup

### Install Node Dependencies
```bash
npm install
```

This installs:
- **Vite** - Fast build tool
- **Laravel Vite Plugin** - Laravel integration
- **Tailwind CSS** - Utility-first CSS framework
- **Livewire** - Frontend reactivity

### Development Build (Watch Mode)
Automatically rebuilds on file changes:

```bash
npm run dev
```

Keep this terminal open while developing. Vite will hot-reload CSS and JS changes.

### Production Build
Minified and optimized assets:

```bash
npm run build
```

Run this before deploying to production.

### Verify Frontend Assets
Check if Vite manifest exists:
```bash
# Windows PowerShell
Test-Path public/build/manifest.json

# Linux/Mac
ls public/build/manifest.json
```

---

## Storage Configuration

### Link Public Storage
Laravel stores uploaded files in `storage/app/public`. Link it to `public/storage`:

```bash
php artisan storage:link
```

**What This Does**:
- Creates symlink: `public/storage` → `storage/app/public`
- Allows public access to uploaded avatars and media

### Storage Directories
The application uses these storage paths:

```
storage/
├── app/
│   ├── public/          # Publicly accessible files
│   │   └── avatars/     # User-uploaded persona photos
│   └── media-library/   # Spatie MediaLibrary files
│       ├── generated_images/
│       ├── reference_images/
│       └── voice_notes/
├── logs/                # Application logs
└── framework/           # Laravel cache/sessions
```

### Permissions (Linux/Mac)
Ensure storage is writable:

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## Queue Worker Setup

The application uses queues for:
- Processing chat responses
- Extracting memory tags
- Generating images
- Sending scheduled messages

### Database Queue (Default)
Uses `jobs` table for queue storage. Already migrated if you ran migrations.

### Start Queue Worker
**Development (Single Command)**:
```bash
composer run dev
```

This runs in parallel:
- `php artisan serve` - HTTP server (port 8000)
- `php artisan queue:listen` - Queue worker
- `php artisan pail` - Real-time log viewer
- `npm run dev` - Vite build watcher

**Manual Queue Worker**:
```bash
php artisan queue:work --tries=3 --timeout=90
```

Options:
- `--tries=3` - Retry failed jobs 3 times
- `--timeout=90` - Job timeout (seconds)
- `--queue=high,default` - Process specific queues

### Queue Monitoring
View pending jobs:
```bash
php artisan queue:monitor database
```

Clear failed jobs:
```bash
php artisan queue:flush
```

Retry all failed jobs:
```bash
php artisan queue:retry all
```

### Production Queue Setup (Supervisor)
For production, use Supervisor to keep queue worker running:

```ini
[program:persona-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/persona/artisan queue:work --tries=3 --timeout=90
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/persona/storage/logs/worker.log
```

---

## Verification

### Create Admin User
Access the admin dashboard:

```bash
php artisan app:create-admin
```

Follow prompts to set name, email, password.

### Access Dashboard
1. Start development server:
   ```bash
   php artisan serve
   ```
2. Visit: [http://localhost:8000](http://localhost:8000)
3. Login with admin credentials
4. Navigate to dashboard to verify:
   - ✓ Persona configuration loads
   - ✓ Memory tags display
   - ✓ Schedule timeline shows events
   - ✓ Chat logs render

### Test Services (Tinker)
Laravel Tinker provides interactive PHP shell:

```bash
php artisan tinker
```

**Test GeminiBrain**:
```php
use App\Facades\GeminiBrain;

$response = GeminiBrain::chat("Hello!", []);
echo $response; // Should return AI response
```

**Test Telegram**:
```php
use App\Facades\Telegram;

Telegram::sendMessage('YOUR_CHAT_ID', 'Test message');
// Check your Telegram bot for message
```

**Test SmartQueue**:
```php
use App\Models\{Persona, EventSchedule};

$persona = Persona::first();
$events = \App\Facades\GeminiBrain::generateDailyPlan(
    $persona->memoryTags, 
    $persona->system_prompt,
    '08:00', 
    '23:00'
);

dump($events); // Should return array of 5 events
```

---

## Troubleshooting

### Common Issues

#### 1. `Class 'GeminiBrain' not found`
**Cause**: Service provider not registered or cached config outdated.

**Fix**:
```bash
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

#### 2. Queue Jobs Not Processing
**Symptoms**: Scheduled messages not sending, images not generating.

**Fix**:
```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# Restart queue worker
php artisan queue:restart

# Check failed jobs
php artisan queue:failed
```

#### 3. Vite Manifest Not Found
**Error**: `Vite manifest not found at: /public/build/manifest.json`

**Fix**:
```bash
# Development: Run Vite dev server
npm run dev

# Production: Build assets
npm run build
```

#### 4. Storage Symlink Error
**Error**: `The [public/storage] link already exists`

**Fix**:
```bash
# Remove existing link
rm public/storage

# Recreate symlink
php artisan storage:link
```

#### 5. Database Connection Failed
**Error**: `SQLSTATE[HY000] [2002] Connection refused`

**Fix**:
```bash
# Verify MySQL is running
# Herd users:
herd status

# Check .env credentials match MySQL
DB_HOST=127.0.0.1
DB_PORT=3306

# Test connection
php artisan db:show
```

#### 6. API Rate Limits
**Symptoms**: Gemini/Cloudflare errors after many requests.

**Fix**:
- Check API quotas in respective dashboards
- Implement exponential backoff (already in services)
- Use Redis cache for frequently accessed data

#### 7. Permission Denied (Linux/Mac)
**Error**: `The stream or file "storage/logs/laravel.log" could not be opened`

**Fix**:
```bash
sudo chown -R $USER:www-data storage
sudo chmod -R 775 storage bootstrap/cache
```

### Debug Mode
Enable detailed error messages in `.env`:
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

**⚠️ Never enable in production!**

### Logs Location
Check application logs:
```bash
# View latest logs
tail -f storage/logs/laravel.log

# Using Laravel Pail (recommended)
php artisan pail
```

---

## IDE Setup

### VS Code (Recommended)

#### Essential Extensions
Install from Extensions Marketplace (`Ctrl+Shift+X`):

1. **[PHP Intelephense](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client)** - PHP intelligence
2. **[Laravel Extension Pack](https://marketplace.visualstudio.com/items?itemName=onecentlin.laravel-extension-pack)** - Laravel tooling
3. **[Livewire Language Support](https://marketplace.visualstudio.com/items?itemName=cierra.livewire-vscode)** - Livewire syntax
4. **[Tailwind CSS IntelliSense](https://marketplace.visualstudio.com/items?itemName=bradlc.vscode-tailwindcss)** - Tailwind autocomplete
5. **[PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug)** - Xdebug integration
6. **[GitLens](https://marketplace.visualstudio.com/items?itemName=eamodio.gitlens)** - Git supercharged

#### Workspace Settings
Create `.vscode/settings.json`:

```json
{
  "php.suggest.basic": false,
  "php.validate.executablePath": "C:\\Program Files\\Herd\\php\\8.2\\php.exe",
  "intelephense.files.exclude": [
    "**/.git/**",
    "**/.svn/**",
    "**/.hg/**",
    "**/CVS/**",
    "**/.DS_Store/**",
    "**/node_modules/**",
    "**/vendor/**/{Tests,tests}/**"
  ],
  "files.associations": {
    "*.blade.php": "blade"
  },
  "emmet.includeLanguages": {
    "blade": "html"
  },
  "tailwindCSS.includeLanguages": {
    "blade": "html"
  }
}
```

#### Debug Configuration
Create `.vscode/launch.json`:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for Xdebug",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/app": "${workspaceFolder}"
      }
    }
  ]
}
```

### PHPStorm

#### Recommended Plugins
1. **Laravel Idea** (Paid) - Premium Laravel support
2. **Pest** - Pest testing framework
3. **Tailwind CSS** - Tailwind intelligence
4. **EnvFile** - .env support

#### Laravel Plugin Setup
1. Go to **Settings** → **Languages & Frameworks** → **PHP** → **Laravel**
2. Enable "Enable plugin for this project"
3. Set Laravel sources: `{project_root}/vendor/laravel/framework/src`

#### Code Style
1. **Settings** → **Editor** → **Code Style** → **PHP**
2. Click "Set from..." → "Predefined Style" → "Laravel"
3. Import: [Laravel Code Style XML](https://gist.github.com/laravel-shift/cab527923ed2a109dda047b97d53c200)

---

## Development Tools

### Laravel Pail (Real-time Logs)
Modern log viewer with filtering:

```bash
php artisan pail
```

**Features**:
- Color-coded log levels
- Filter by level: `php artisan pail --level=error`
- Filter by type: `php artisan pail --filter="GeminiBrain"`
- Keyboard shortcuts: `Ctrl+C` to exit

### Laravel Tinker (REPL)
Interactive PHP shell with Laravel context:

```bash
php artisan tinker
```

**Common Commands**:
```php
// Query models
User::first();
Persona::with('memoryTags')->find(1);

// Test services
\App\Facades\GeminiBrain::chat("Test", []);

// Clear caches
Artisan::call('cache:clear');

// View routes
Route::getRoutes()->count();
```

**Tinker Tips**:
- Press `Tab` for autocomplete
- Use `doc(ClassName)` to view documentation
- Use `ls` to list variables
- Use `help` for commands

### Laravel Telescope (Optional)
Advanced debugging dashboard. Install if needed:

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Access at: [http://localhost:8000/telescope](http://localhost:8000/telescope)

**Features**:
- Request monitoring
- Exception tracking
- Database query analysis
- Job monitoring
- Cache operations

**Production Warning**: Only install in development. Telescope stores extensive data.

### Database GUI Tools
Recommended database clients:

- **[TablePlus](https://tableplus.com/)** (Mac/Windows) - Modern, native GUI
- **[DBeaver](https://dbeaver.io/)** (Cross-platform) - Free, open-source
- **[MySQL Workbench](https://www.mysql.com/products/workbench/)** (Official) - Free, feature-rich

### API Testing Tools
Test Telegram webhooks and Gemini API:

- **[Insomnia](https://insomnia.rest/)** - REST client
- **[Postman](https://www.postman.com/)** - API platform
- **[HTTPie](https://httpie.io/)** - Command-line HTTP client

Example Gemini API test:
```bash
curl -X POST https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash-exp:generateContent \
  -H "Content-Type: application/json" \
  -H "x-goog-api-key: YOUR_API_KEY" \
  -d '{"contents":[{"parts":[{"text":"Hello"}]}]}'
```

---

## Next Steps

After completing setup:

1. **Configure Your Persona**
   - Visit Dashboard → Persona Manager
   - Set system prompt (personality traits)
   - Add physical traits for image generation
   - Upload reference avatar

2. **Add Memory Tags**
   - Visit Memory Brain
   - Create user facts (user's preferences, habits)
   - Create self facts (persona's daily outfit, routines)

3. **Test Chat Interface**
   - Visit Test Chat page
   - Send messages to test AI responses
   - Verify memory context is used

4. **Generate Daily Plan**
   - Run: `php artisan app:generate-daily-plan`
   - Check Schedule Timeline for generated events

5. **Set Up Telegram Bot**
   - Add bot token to `.env`
   - Set webhook (production) or use queue polling (local)
   - Send test message to bot

6. **Review Documentation**
   - [`docs/guides/SERVICES_README.md`](../reference/SERVICES_README.md) - Service API reference
   - [`docs/guides/ADMIN_DASHBOARD_GUIDE.md`](ADMIN_DASHBOARD_GUIDE.md) - Dashboard features
   - [`docs/guides/QUICK_REFERENCE.md`](QUICK_REFERENCE.md) - Common patterns

---

## Getting Help

### Resources
- **Project Documentation**: [`docs/`](../README.md) directory
- **Laravel Docs**: [laravel.com/docs](https://laravel.com/docs)
- **Livewire Docs**: [livewire.laravel.com](https://livewire.laravel.com)
- **Gemini API Docs**: [ai.google.dev](https://ai.google.dev)

### Issue Reporting
Found a bug? Check:
1. [`BUGS.md`](../../BUGS.md) - Known issues
2. [`TECHNICAL_DEBT.md`](../../TECHNICAL_DEBT.md) - Planned improvements

Report new issues with:
- Steps to reproduce
- Expected vs actual behavior
- Log output from `storage/logs/laravel.log`
- Environment details (`php artisan about`)

---

**Happy Coding! 🚀**

For contribution guidelines, see [`CONTRIBUTING.md`](../../CONTRIBUTING.md).
