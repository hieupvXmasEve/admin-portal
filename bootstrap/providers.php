<?php

declare(strict_types=1);

use App\Modules\Academic\Providers\AcademicServiceProvider;
use App\Modules\Admissions\Providers\AdmissionsServiceProvider;
use App\Modules\AI\Providers\AIServiceProvider;
use App\Modules\Engagement\Providers\EngagementServiceProvider;
use App\Modules\Facilities\Providers\FacilitiesServiceProvider;
use App\Modules\Finance\Providers\FinanceServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Institution\Providers\InstitutionServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\Platform\Providers\PlatformServiceProvider;
use App\Modules\StudentRegistry\Providers\StudentRegistryServiceProvider;
use App\Modules\Upload\Providers\UploadServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    AIServiceProvider::class,
    IdentityServiceProvider::class,
    InstitutionServiceProvider::class,
    PlatformServiceProvider::class,
    StudentRegistryServiceProvider::class,
    AdmissionsServiceProvider::class,
    EngagementServiceProvider::class,
    AcademicServiceProvider::class,
    FacilitiesServiceProvider::class,
    FinanceServiceProvider::class,
    NotificationServiceProvider::class,
    UploadServiceProvider::class,
    // App\Providers\TelescopeServiceProvider::class,
];
