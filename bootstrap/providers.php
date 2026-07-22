<?php

use App\Modules\Academic\Providers\AcademicServiceProvider;
use App\Modules\Admissions\Providers\AdmissionsServiceProvider;
use App\Modules\AI\Providers\AIServiceProvider;
use App\Modules\Facilities\Providers\FacilitiesServiceProvider;
use App\Modules\Finance\Providers\FinanceServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Institution\Providers\InstitutionServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\StudentRegistry\Providers\StudentRegistryServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\PermissionServiceProvider;
use Barryvdh\Debugbar\ServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    PermissionServiceProvider::class,
    AIServiceProvider::class,
    IdentityServiceProvider::class,
    InstitutionServiceProvider::class,
    StudentRegistryServiceProvider::class,
    AdmissionsServiceProvider::class,
    AcademicServiceProvider::class,
    FacilitiesServiceProvider::class,
    FinanceServiceProvider::class,
    NotificationServiceProvider::class,
    // App\Providers\TelescopeServiceProvider::class,
    ServiceProvider::class,
];
