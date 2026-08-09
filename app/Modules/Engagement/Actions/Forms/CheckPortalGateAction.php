<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions\Forms;

use App\Models\CourseOffering;
use App\Models\StudentFormAssignment;
use App\Modules\Engagement\Models\FormResponse;
use Illuminate\Database\Eloquent\Builder;

class CheckPortalGateAction
{
    /**
     * Check if the student is blocked by any mandatory forms.
     *
     * @return array ['blocked' => bool, 'mandatory_forms' => Collection]
     */
    public function execute(int $studentId): array
    {
        $mandatoryAssignments = StudentFormAssignment::query()
            ->where('student_id', $studentId)
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
                        CourseOffering::class => ['unit'],
                    ]);
                },
            ])
            ->get();

        // Self-healing/Double-check: Filter out assignments that have already been fulfilled
        // but whose status hasn't been updated (dirty data or alternate target fulfillment)
        $filteredAssignments = $mandatoryAssignments->filter(function ($assignment) use ($studentId) {
            $target = $assignment->formTarget;

            // Check if there's any submitted response for this form and scope by this student
            $hasSubmitted = FormResponse::where('submitted_by_student_id', $studentId)
                ->where('form_id', $target->form_id)
                ->where('target_scope_type', $target->scope_type)
                ->where('target_scope_id', $target->scope_id)
                ->where('status', 'submitted')
                ->exists();

            if ($hasSubmitted) {
                // Background update for data consistency
                $assignment->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    // We don't necessarily have the precise response_id here without another query,
                    // but marking completed is enough for the gate.
                ]);

                return false;
            }

            return true;
        });

        return [
            'blocked' => $filteredAssignments->isNotEmpty(),
            'mandatory_assignments' => $filteredAssignments->values(),
        ];
    }
}
