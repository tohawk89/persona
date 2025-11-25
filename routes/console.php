<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily plan generation at each persona's wake time
// Note: This should ideally be dynamic based on persona wake times
// For now, runs at 6 AM, 7 AM, 8 AM, 9 AM to cover common wake times
Schedule::command('app:generate-daily-plan')->dailyAt('06:00');
Schedule::command('app:generate-daily-plan')->dailyAt('07:00');
Schedule::command('app:generate-daily-plan')->dailyAt('08:00');
Schedule::command('app:generate-daily-plan')->dailyAt('09:00');

// Process scheduled events every minute
Schedule::command('app:process-scheduled-events')->everyMinute();
