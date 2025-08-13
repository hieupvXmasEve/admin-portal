<?php

namespace App\Providers;

use App\Events\AcademicHoldPlaced;
use App\Events\AssessmentDeadlineApproaching;
use App\Events\CourseRegistrationOpened;
use App\Events\EnrollmentConfirmed;
use App\Events\GradePublished;
use App\Listeners\AcademicHoldListener;
use App\Listeners\AssessmentDeadlineListener;
use App\Listeners\CourseRegistrationListener;
use App\Listeners\EnrollmentConfirmedListener;
use App\Listeners\GradePublishedListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Course Registration Events
        CourseRegistrationOpened::class => [
            CourseRegistrationListener::class,
        ],

        // Grade Publishing Events
        GradePublished::class => [
            GradePublishedListener::class,
        ],

        // Academic Hold Events
        AcademicHoldPlaced::class => [
            AcademicHoldListener::class,
        ],

        // Enrollment Events
        EnrollmentConfirmed::class => [
            EnrollmentConfirmedListener::class,
        ],

        // Assessment Deadline Events
        AssessmentDeadlineApproaching::class => [
            AssessmentDeadlineListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
