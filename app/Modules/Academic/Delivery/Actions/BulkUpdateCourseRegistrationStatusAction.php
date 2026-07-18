<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use Illuminate\Support\Facades\DB;

final class BulkUpdateCourseRegistrationStatusAction
{
    /**
     * @param  array{course_offering_id: int, from_status: string, to_status: string, student_ids: list<int>}  $data
     */
    public static function run(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $offering = CourseOffering::query()->lockForUpdate()->findOrFail($data['course_offering_id']);
            $registrations = CourseRegistration::query()
                ->where('course_offering_id', $offering->id)
                ->where('registration_status', $data['from_status'])
                ->whereIn('student_id', $data['student_ids'])
                ->lockForUpdate()
                ->get();

            foreach ($registrations as $registration) {
                $registration->update(['registration_status' => $data['to_status']]);
            }

            $updatedCount = $registrations->count();
            $fromIsCounted = in_array($data['from_status'], ['registered', 'confirmed'], true);
            $toIsCounted = in_array($data['to_status'], ['registered', 'confirmed'], true);
            if ($fromIsCounted && ! $toIsCounted) {
                $offering->decrement('current_enrollment', $updatedCount);
            } elseif (! $fromIsCounted && $toIsCounted) {
                $offering->increment('current_enrollment', $updatedCount);
            }

            return $updatedCount;
        });
    }
}
