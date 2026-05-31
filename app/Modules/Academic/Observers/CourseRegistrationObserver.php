<?php

declare(strict_types=1);

namespace App\Modules\Academic\Observers;

use App\Models\CourseRegistration;
use App\Modules\Academic\Actions\LinkPaidRetakeRegistrationToCourseRegistrationAction;
use Illuminate\Support\Facades\Log;

class CourseRegistrationObserver
{
    public function created(CourseRegistration $courseRegistration): void
    {
        $this->linkPaidRetakeRegistration($courseRegistration);
    }

    public function updated(CourseRegistration $courseRegistration): void
    {
        if (! $courseRegistration->wasChanged(['registration_status', 'course_offering_id', 'semester_id'])) {
            return;
        }

        $this->linkPaidRetakeRegistration($courseRegistration);
    }

    private function linkPaidRetakeRegistration(CourseRegistration $courseRegistration): void
    {
        try {
            app(LinkPaidRetakeRegistrationToCourseRegistrationAction::class)->run($courseRegistration);
        } catch (\Throwable $e) {
            Log::warning('Failed to link paid retake registration after course registration change', [
                'course_registration_id' => $courseRegistration->id,
                'student_id' => $courseRegistration->student_id,
                'course_offering_id' => $courseRegistration->course_offering_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
