<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseRegistration;
use Illuminate\Support\Facades\DB;

final class UpdateCourseRegistrationStatusAction
{
    /** @param array{course_registration_id: int, registration_status: string, notes?: string|null} $data */
    public static function run(array $data): CourseRegistration
    {
        return DB::transaction(function () use ($data): CourseRegistration {
            $registration = CourseRegistration::query()
                ->lockForUpdate()
                ->findOrFail($data['course_registration_id']);
            $registration->update([
                'registration_status' => $data['registration_status'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $registration->fresh();
        });
    }
}
