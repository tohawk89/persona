<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('persona-manager', \App\Livewire\PersonaManager::class)->name('persona.manager');
    Route::get('memory-brain', \App\Livewire\MemoryBrain::class)->name('memory.brain');
    Route::get('schedule-timeline', \App\Livewire\ScheduleTimeline::class)->name('schedule.timeline');
    Route::get('chat-logs', \App\Livewire\ChatLogs::class)->name('chat.logs');
    Route::get('test-chat', \App\Livewire\TestChat::class)->name('test.chat');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
