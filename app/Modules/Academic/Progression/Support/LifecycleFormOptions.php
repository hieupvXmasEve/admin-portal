<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Enums\StudentActionType;
use App\Models\IeltsCertificate;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentDecision;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\StudentLifecycleCourseRegistrationGateway;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
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
    public function __construct(
        private readonly CampusReferenceReader $campusReferences,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly StudentLifecycleFinanceReader $finance,
        private readonly StudentLifecycleCourseRegistrationGateway $courseRegistrations,
    ) {}

    /**
     * Options for the "Record Student Action" form.
     *
     * @return array<string, mixed>
     */
    public function actionOptions(?Student $student = null): array
    {
        $activeSemesterId = $this->academicPeriods->current()?->id;

        $options = [
            'actionTypes' => StudentActionType::options(),
            'semesters' => Semester::query()
                ->select('id', 'name', 'code', 'start_date', 'end_date')
                ->orderBy('start_date', 'desc')
                ->get(),
            'activeSemesterId' => $activeSemesterId,
            'campuses' => array_map(
                fn ($campus): array => $campus->toArray(),
                $this->campusReferences->all(),
            ),
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
            // The next actions a staff member may select for this student's
            // current status (single source of truth — the dropdown filters to
            // these so illogical picks like resume-from-course never appear).
            $lifecycleStatus = $this->programEnrollments
                ->forStudentId((int) $student->id)
                ->legacyCompatibleStatus();
            $options['allowedActionTypes'] = StudentStatusTransitionPolicy::selectableActionValues($lifecycleStatus);

            $options['courseRegistrations'] = array_map(
                static fn ($registration): array => $registration->toArray(),
                $this->courseRegistrations->forStudentSemester(
                    (int) $student->id,
                    $activeSemesterId ?? 0,
                ),
            );

            $options['egcCharges'] = array_map(
                static fn ($charge): array => $charge->toArray(),
                $this->finance->activeEgcCharges((int) $student->id, $activeSemesterId),
            );
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
