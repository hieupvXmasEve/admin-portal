<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\IeltsCertificate;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentDecision;
use App\Modules\Finance\Services\FinanceChargeService;
use Illuminate\Support\Collection;

/**
 * Shared form-options builder for the lifecycle write surfaces.
 *
 * The Lifecycle Hub tab records Student Actions and EGC placement/progression
 * in place, reusing the same write endpoints (and therefore the same option
 * payloads) as the now-redirected standalone pages. Centralising the option
 * shape here keeps the Hub tab and the legacy controllers in lock-step.
 */
class LifecycleFormOptions
{
    /**
     * Options for the "Record Student Action" form.
     *
     * @return array<string, mixed>
     */
    public function actionOptions(?Student $student = null): array
    {
        $activeSemester = Semester::getActiveSemester();

        $options = [
            'actionTypes' => StudentActionType::options(),
            'semesters' => Semester::query()
                ->select('id', 'name', 'code', 'start_date', 'end_date')
                ->orderBy('start_date', 'desc')
                ->get(),
            'activeSemesterId' => $activeSemester?->id,
            'campuses' => Campus::query()
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get(),
            'deferScopeTypes' => [
                ['value' => 'FULL', 'label' => 'Toàn kỳ (Full Semester)'],
                ['value' => 'COURSES', 'label' => 'Theo môn (Specific Courses)'],
            ],
            'deferFeePolicies' => [
                ['value' => 'PRESERVE', 'label' => 'Bảo lưu học phí (Preserve Fee)'],
                ['value' => 'FORFEIT', 'label' => 'Mất học phí (Forfeit Fee)'],
                ['value' => 'PARTIAL', 'label' => 'Bảo lưu một phần (Partial Preserve)'],
            ],
            'egcDeferBlocks' => [
                ['value' => 1, 'label' => 'Block 1'],
                ['value' => 2, 'label' => 'Block 2'],
            ],
            'studentDecisions' => $this->decisionOptions(),
        ];

        if ($student) {
            $activeSemesterId = $activeSemester?->id;

            // The next actions a staff member may select for this student's
            // current status (single source of truth — the dropdown filters to
            // these so illogical picks like resume-from-course never appear).
            $options['allowedActionTypes'] = StudentStatusTransitionPolicy::selectableActionValues((string) $student->status);

            $options['courseRegistrations'] = $student->courseRegistrations()
                ->with(['courseOffering.unit', 'courseOffering.semester'])
                ->whereHas('courseOffering', function ($query) {
                    $query->whereHas('semester', function ($q) {
                        $q->where('is_active', true);
                    });
                })
                ->when($activeSemesterId, fn ($query) => $query->where('semester_id', $activeSemesterId))
                ->get()
                ->map(fn ($reg) => [
                    'id' => $reg->id,
                    'course_code' => $reg->courseOffering?->unit?->code ?? 'N/A',
                    'course_name' => $reg->courseOffering?->unit?->name ?? 'N/A',
                    'semester_name' => $reg->courseOffering?->semester?->name ?? 'N/A',
                    'semester_id' => $reg->courseOffering?->semester_id,
                    'registration_status' => $reg->registration_status,
                ]);

            $options['egcCharges'] = app(FinanceChargeService::class)
                ->listActiveEgcChargesForStudent((int) $student->id, $activeSemesterId);
        }

        return $options;
    }

    /**
     * Options for the EGC placement & progression forms.
     *
     * @return array<string, mixed>
     */
    public function placementOptions(): array
    {
        return [
            'eventTypes' => AcademicProgressionEventType::options(),
            'triggerSources' => ProgressionTriggerSource::options(),
            'semesters' => Semester::query()
                ->select('id', 'name', 'code', 'start_date', 'end_date')
                ->orderBy('start_date', 'desc')
                ->get(),
            'englishLevels' => [
                ['value' => 0, 'label' => 'Level 0'],
                ['value' => 1, 'label' => 'Level 1'],
                ['value' => 2, 'label' => 'Level 2'],
                ['value' => 3, 'label' => 'Level 3'],
                ['value' => 4, 'label' => 'Level 4'],
                ['value' => 5, 'label' => 'Level 5'],
            ],
            'ieltsScoreThreshold' => IeltsCertificate::SCORE_THRESHOLD_INTAKE_COURSE,
        ];
    }

    /**
     * Decisions available to authorize / backfill a transition.
     *
     * @return Collection<int, StudentDecision>
     */
    public function decisionOptions(): Collection
    {
        return StudentDecision::query()
            ->select('id', 'decision_name', 'decision_number', 'decision_signer', 'issued_at', 'expires_at')
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();
    }
}
