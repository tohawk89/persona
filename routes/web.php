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
        Route::get('/gallery', \App\Http\Livewire\PersonaGallery::class)->name('persona.gallery');
        
        // Serve media file (for viewing in gallery)
        Route::get('/media/{media}/view', function (\App\Models\Persona $persona, \Spatie\MediaLibrary\MediaCollections\Models\Media $media, \Illuminate\Http\Request $request) {
            // Authorization: ensure media belongs to this persona and user owns persona
            if ($media->model_id !== $persona->id || $media->model_type !== \App\Models\Persona::class) {
                abort(403, 'Unauthorized access to media.');
            }
            
            if ($persona->user_id !== auth()->id()) {
                abort(403, 'You do not own this persona.');
            }
            
            // Get conversion if specified
            $conversion = $request->query('conversion');
            $path = $conversion && $media->hasGeneratedConversion($conversion)
                ? $media->getPath($conversion)
                : $media->getPath();
            
            if (!file_exists($path)) {
                abort(404, 'Media file not found.');
            }
            
            return response()->file($path, [
                'Content-Type' => $media->mime_type,
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        })->name('persona.media.view');
        
        // Download media file
        Route::get('/media/{media}/download', function (\App\Models\Persona $persona, \Spatie\MediaLibrary\MediaCollections\Models\Media $media) {
            // Authorization: ensure media belongs to this persona
            if ($media->model_id !== $persona->id || $media->model_type !== \App\Models\Persona::class) {
                abort(403, 'Unauthorized access to media.');
            }
            
            // Return temporary signed URL for private storage, or direct URL for public
            if (config('filesystems.default') === 's3' || str_starts_with($media->disk, 'private')) {
                return redirect($media->getTemporaryUrl(now()->addMinutes(5)));
            }
            
            return response()->download($media->getPath(), $media->file_name);
        })->name('persona.media.download');
    });
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
