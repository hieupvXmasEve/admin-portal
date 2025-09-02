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

