<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\CourseRegistration;
use App\Modules\Academic\Delivery\Observers\CourseRegistrationObserver;

/**
 * Delivery owns CourseRegistration, so it is the one context allowed to
 * import it directly. AcademicServiceProvider is the single cross-cutting
 * provider for the whole Academic module and calls this seam instead of
 * touching the model itself, since Eloquent's observer registration has no
 * DTO/contract substitute.
 */
class CourseRegistrationObserverRegistrar
{
    public function register(): void
    {
        CourseRegistration::observe(CourseRegistrationObserver::class);
    }
}
