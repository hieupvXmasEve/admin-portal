<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic deactivation of expired semesters
// Schedule::command('semester:deactivate-expired')
//     ->daily()
//     ->at('00:01')
//     ->withoutOverlapping()
//     ->onOneServer();

// Schedule automatic update of class session statuses
// Auto-creates attendance when sessions transition to 'in_progress'
Schedule::command('sessions:update-statuses')
    ->everyThirtyMinutes()
    ->withoutOverlapping(10) // Timeout after 10 minutes
    ->onOneServer()
    ->runInBackground();

// Schedule automatic event completions
Schedule::command('events:process-completions')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Schedule automatic event reminders
Schedule::command('events:send-reminders')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Schedule failed gold rewards processing
Schedule::command('events:process-failed-gold-rewards')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Schedule academic records sync (create new + update existing with latest attendance)
Schedule::command('academic-records:sync')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Schedule attendance sync to academic records every 2 hours
Schedule::command('attendance:sync-to-academic-records')
    ->everyTwoHours()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
