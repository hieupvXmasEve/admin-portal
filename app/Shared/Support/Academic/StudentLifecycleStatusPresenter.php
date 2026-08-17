<?php

declare(strict_types=1);

namespace App\Shared\Support\Academic;

/**
 * Presentation (label/color) for a lifecycle status string.
 *
 * Kept off `App\Models\Student` and under `App\Shared\` so Finance and other
 * modules can label a status they resolved from the Progression-owned
 * `program_enrollments` projection without importing the Student Eloquent
 * model — several module-boundary arch tests forbid that import outright.
 */
final class StudentLifecycleStatusPresenter
{
    public static function label(?string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            // 'inactive' => 'Inactive',
            'suspended' => 'Suspended',
            'graduated' => 'Graduated',
            'intake_pre_uni_gc' => 'Intake Pre-Uni GC',
            'intake_course' => 'Intake Course',
            'intake_major' => 'Intake Major',
            'deferred' => 'Deferred',
            'dropout' => 'Dropout',
            'dropout_transfer' => 'Dropout Transfer',
            'pending' => 'Pending',
            'admission_deferred' => 'Admission Deferred',
            'pending_course_opening' => 'Pending Course Opening',
            default => 'Unknown',
        };
    }

    public static function color(?string $status): string
    {
        return match ($status) {
            'active' => 'green',
            // 'inactive' => 'gray',
            'suspended' => 'red',
            'graduated' => 'blue',
            'intake_pre_uni_gc' => 'yellow',
            'intake_course' => 'indigo',
            'intake_major' => 'green',
            'deferred' => 'orange',
            'dropout' => 'red',
            'dropout_transfer' => 'red',
            'pending' => 'yellow',
            'admission_deferred' => 'orange',
            'pending_course_opening' => 'orange',
            default => 'gray',
        };
    }
}
