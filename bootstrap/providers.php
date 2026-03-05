<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\PermissionServiceProvider::class,
    App\Modules\Identity\Providers\IdentityServiceProvider::class,
    App\Modules\Academic\Providers\AcademicServiceProvider::class,
    App\Modules\Finance\Providers\FinanceServiceProvider::class,
    App\Modules\Notification\Providers\NotificationServiceProvider::class,
    // App\Providers\TelescopeServiceProvider::class,
    Barryvdh\Debugbar\ServiceProvider::class,
];
