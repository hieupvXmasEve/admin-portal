<?php

// Production providers - exclude development packages
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    // App\Providers\EventServiceProvider::class,
    // App\Providers\RouteServiceProvider::class,
    // Exclude Debugbar in production
    // Barryvdh\Debugbar\ServiceProvider::class,
];
