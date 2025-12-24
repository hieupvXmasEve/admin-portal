<?php

namespace App\Actions\Form;

use App\Models\Student;
use App\Models\StudentFormAssignment;
use Illuminate\Database\Eloquent\Builder;

class CheckPortalGateAction
{
    /**
     * Check if the student is blocked by any mandatory forms.
     *
     * @return array ['blocked' => bool, 'mandatory_forms' => Collection]
     */
    public function execute(Student $student): array
    {
        $mandatoryAssignments = StudentFormAssignment::query()
            ->where('student_id', $student->id)
            ->where('status', 'not_started')
            ->whereHas('formTarget', function (Builder $query) {
                $query->where('is_mandatory', true)
                    ->where('status', 'active')
                    ->where('start_at', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_at')->orWhere('end_at', '>=', now());
                    });
            })
            ->with([
                'formTarget.form',
                'formTarget.scope' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\CourseOffering::class => ['unit'],
                    ]);
                },
            ])
            ->get();

        return [
            'blocked' => $mandatoryAssignments->isNotEmpty(),
            'mandatory_assignments' => $mandatoryAssignments,
        ];
    }
}
