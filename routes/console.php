<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic deactivation of expired semesters
Schedule::command('semester:deactivate-expired')
    ->daily()
    ->at('00:01')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule automatic update of class session statuses
Schedule::command('sessions:update-statuses')
    ->everyMinute()
    ->withoutOverlapping()
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
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Schedule failed gold rewards processing
Schedule::command('events:process-failed-gold-rewards')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
