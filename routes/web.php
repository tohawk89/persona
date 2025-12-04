<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;

Route::view('/', 'welcome');

// Telegram Webhook Routes (no auth required)
Route::post('/telegram/webhook/{token}', [TelegramWebhookController::class, 'webhook'])->name('telegram.webhook');
Route::get('/api/telegram/setup-webhook', [TelegramWebhookController::class, 'setupWebhook'])->middleware(['auth'])->name('telegram.setup');

Route::middleware(['auth', 'verified'])->group(function () {
    // Global Dashboard
    Route::get('dashboard', \App\Livewire\Dashboard::class)->name('dashboard');

    // Persona List
    Route::get('personas', \App\Livewire\PersonaList::class)->name('personas.index');

    // Persona-Specific Routes
    Route::prefix('personas/{persona}')->group(function () {
        Route::get('/overview', \App\Livewire\PersonaDashboard::class)->name('persona.dashboard');
        Route::get('/edit', \App\Livewire\PersonaManager::class)->name('persona.edit');
        Route::get('/avatar', \App\Livewire\PersonaAvatarEditor::class)->name('persona.avatar');
        Route::get('/memory', \App\Livewire\MemoryBrain::class)->name('persona.memory');
        Route::get('/schedule', \App\Livewire\ScheduleTimeline::class)->name('persona.schedule');
        Route::get('/logs', \App\Livewire\ChatLogs::class)->name('persona.logs');
        Route::get('/test', \App\Livewire\TestChat::class)->name('persona.test');
    });
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
