<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\PermissionServiceProvider::class,
    App\Modules\Identity\Providers\IdentityServiceProvider::class,
    // App\Providers\TelescopeServiceProvider::class,
    Barryvdh\Debugbar\ServiceProvider::class,
];
