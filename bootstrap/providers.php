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
use App\Modules\Merchandise\Providers\MerchandiseServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\Platform\Providers\PlatformServiceProvider;
use App\Modules\StudentRegistry\Providers\StudentRegistryServiceProvider;
use App\Modules\Upload\Providers\UploadServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
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
    MerchandiseServiceProvider::class,
    NotificationServiceProvider::class,
    UploadServiceProvider::class,
    // App\Providers\TelescopeServiceProvider::class,
];
