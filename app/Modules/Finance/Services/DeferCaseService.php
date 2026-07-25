<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\DeferCase;
use App\Models\DeferCaseItem;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Academic\StudentDeferLifecycleReader;
use App\Shared\Contracts\Academic\StudentLifecycleCourseRegistrationGateway;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeferCaseService
{
    public function __construct(
        protected FinanceChargeService $chargeService,
        private readonly StudentLifecycleCourseRegistrationGateway $courseRegistrations,
        private readonly StudentDeferLifecycleReader $deferLifecycle,
    ) {}

    /**
     * Create a defer case from a student action log.
     */
    public function createDeferCase(int $actionLogId, array $data): DeferCase
    {
        $action = $this->deferLifecycle->findDeferAction($actionLogId);
        if ($action === null) {
            throw new \InvalidArgumentException('Action log must be a defer action');
        }

        // Check if defer case already exists
        if (DeferCase::query()->where('student_action_log_id', $action->id)->exists()) {
            throw new RuntimeException('Defer case already exists for this action log');
        }

        return DB::transaction(function () use ($action, $data) {
            $deferCase = DeferCase::create([
                'student_action_log_id' => $action->id,
                'student_id' => $action->studentId,
                'semester_id' => $action->fromSemesterId,
                'applies_until_semester_id' => $data['applies_until_semester_id'] ?? $action->returnSemesterId,
                'scope_type' => $data['scope_type'] ?? DeferCase::SCOPE_FULL,
                'fee_policy' => $data['fee_policy'] ?? DeferCase::POLICY_FORFEIT,
                'applies_once' => $data['applies_once'] ?? true,
                'preserve_amount' => $data['preserve_amount'] ?? null,
                'effective_at' => $data['effective_at'] ?? $action->effectiveAt ?? now(),
                'signed_at' => $data['signed_at'] ?? $action->signedAt,
                'upload_record_id' => $data['upload_record_id'] ?? null,
                'changed_by_user_id' => $data['changed_by_user_id'] ?? $action->changedByUserId,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create course-level items if scope is COURSES
            if ($deferCase->scope_type === DeferCase::SCOPE_COURSES && ! empty($data['course_registration_ids'])) {
                $this->addDeferCaseItems($deferCase, $data['course_registration_ids'], $data['course_fee_policies'] ?? []);
            }

            // FIN-REV-020: a full-scope defer must also capture item-level course
            // evidence for the student's active enrollments in the defer semester,
            // mirroring the COURSES-scope path so those registrations become
            // non-billable (registration_status = 'defer').
            if ($deferCase->scope_type === DeferCase::SCOPE_FULL) {
                $this->itemizeFullScope($deferCase);
            }

            // Process fee policy and create credit charge if applicable
            $this->processFeePolicy($deferCase);

            return $deferCase;
        });
    }

    /**
     * Add course items to a defer case.
     */
    public function addDeferCaseItems(DeferCase $deferCase, array $courseRegistrationIds, array $feePolicies = []): Collection
    {
        $items = collect();
        $registrationIdsToUpdate = [];

        foreach ($courseRegistrationIds as $key => $value) {
            if (is_array($value)) {
                $registrationId = $value['course_registration_id'] ?? null;
                $feePolicy = $value['fee_policy'] ?? null;
            } else {
                $registrationId = $value;
                $feePolicy = $feePolicies[$value] ?? null;
            }

            if (! $registrationId) {
                continue;
            }

            $item = DeferCaseItem::create([
                'defer_case_id' => $deferCase->id,
                'course_registration_id' => $registrationId,
                'fee_policy' => $feePolicy,
                'preserve_amount' => null,
            ]);

            $items->push($item);
            $registrationIdsToUpdate[] = $registrationId;
        }

        if (! empty($registrationIdsToUpdate)) {
            $this->courseRegistrations->markDeferred(array_values(array_unique($registrationIdsToUpdate)));
        }

        return $items;
    }

    /**
     * Capture item-level course evidence for a full-scope defer case.
     *
     * Resolves the student's active enrollments in the defer semester and
     * itemizes them (creating defer_case_items and marking the registrations
     * non-billable). Idempotent: registrations already covered by an item are
     * skipped, so this is safe to run from both the runtime defer flow and the
     * historical backfill command.
     */
    public function itemizeFullScope(DeferCase $deferCase): Collection
    {
        $registrationIds = $this->resolveActiveRegistrationIdsForSemester(
            (int) $deferCase->student_id,
            (int) $deferCase->semester_id,
        );

        $existingIds = $deferCase->items()->pluck('course_registration_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missingIds = array_values(array_diff($registrationIds, $existingIds));

        if (empty($missingIds)) {
            return collect();
        }

        return $this->addDeferCaseItems($deferCase, $missingIds);
    }

    /**
     * Resolve the student's active course registration ids in a semester.
     *
     * Active enrollments mirror the Academic active-enrollment scope so a full-scope
     * defer preserves exactly the courses the student is currently enrolled in,
     * leaving already-dropped/withdrawn/completed registrations untouched.
     *
     * @return array<int, int>
     */
    protected function resolveActiveRegistrationIdsForSemester(int $studentId, int $semesterId): array
    {
        return $this->courseRegistrations->deferableIds($studentId, $semesterId);
    }

    /**
     * Process fee policy and create credit charge if applicable.
     */
    public function processFeePolicy(DeferCase $deferCase): ?FinanceCharge
    {
        if ($deferCase->fee_policy === DeferCase::POLICY_FORFEIT) {
            return null;
        }

        if (is_null($deferCase->preserve_amount)) {
            $preserveAmount = $this->calculatePreserveAmount($deferCase);
            $deferCase->update(['preserve_amount' => $preserveAmount]);
        }

        return null;
    }

    /**
     * Calculate the amount to preserve based on scope and policy.
     */
    public function calculatePreserveAmount(DeferCase $deferCase): float
    {
        // For FORFEIT, nothing is preserved
        if ($deferCase->fee_policy === DeferCase::POLICY_FORFEIT) {
            return 0;
        }

        // Get total active charges for the semester
        $totalCharges = $this->chargeService->getTotalCharges(
            $deferCase->student_id,
            $deferCase->semester_id
        );

        if ($deferCase->scope_type === DeferCase::SCOPE_FULL) {
            // Full semester defer - preserve based on policy percentage
            return $deferCase->fee_policy === DeferCase::POLICY_PRESERVE
                ? $totalCharges
                : $totalCharges * 0.5; // 50% for PARTIAL
        }

        // Course-level defer - calculate based on specific courses
        $courseCharges = $this->calculateCourseChargesAmount($deferCase);

        return $deferCase->fee_policy === DeferCase::POLICY_PRESERVE
            ? $courseCharges
            : $courseCharges * 0.5; // 50% for PARTIAL
    }

    /**
     * Calculate charges amount for deferred courses.
     */
    protected function calculateCourseChargesAmount(DeferCase $deferCase): float
    {
        $courseRegistrationIds = $deferCase->items()->pluck('course_registration_id');

        // Get charges linked to these course registrations
        $charges = FinanceCharge::where('student_id', $deferCase->student_id)
            ->where('semester_id', $deferCase->semester_id)
            ->where('source_type', 'App\\Models\\CourseRegistration')
            ->whereIn('source_id', $courseRegistrationIds)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->sum('amount');

        return (float) $charges;
    }

    /**
     * Update a defer case.
     */
    public function updateDeferCase(DeferCase $deferCase, array $data): DeferCase
    {
        return DB::transaction(function () use ($deferCase, $data) {
            // Void existing credit charge if policy changed
            $policyChanged = isset($data['fee_policy']) && $data['fee_policy'] !== $deferCase->fee_policy;

            if ($policyChanged && $deferCase->creditCharge) {
                $this->chargeService->voidCharge(
                    $deferCase->creditCharge->id,
                    'Defer case policy updated'
                );
            }

            // Update the case
            $deferCase->update([
                'scope_type' => $data['scope_type'] ?? $deferCase->scope_type,
                'fee_policy' => $data['fee_policy'] ?? $deferCase->fee_policy,
                'applies_until_semester_id' => $data['applies_until_semester_id'] ?? $deferCase->applies_until_semester_id,
                'applies_once' => $data['applies_once'] ?? $deferCase->applies_once,
                'preserve_amount' => $data['preserve_amount'] ?? null,
                'effective_at' => $data['effective_at'] ?? $deferCase->effective_at,
                'signed_at' => $data['signed_at'] ?? $deferCase->signed_at,
                'upload_record_id' => $data['upload_record_id'] ?? $deferCase->upload_record_id,
                'notes' => $data['notes'] ?? $deferCase->notes,
            ]);

            // Re-process fee policy if changed
            if ($policyChanged) {
                $this->processFeePolicy($deferCase);
            }

            return $deferCase->fresh();
        });
    }

    /**
     * Get defer cases for a student.
     */
    public function getStudentDeferCases(int $studentId): Collection
    {
        return DeferCase::where('student_id', $studentId)
            ->with(['actionLog', 'semester', 'items.courseRegistration', 'creditCharge'])
            ->orderBy('effective_at', 'desc')
            ->get();
    }

    /**
     * Get defer case with full details.
     */
    public function getDeferCaseDetails(int $deferCaseId): DeferCase
    {
        return DeferCase::with([
            'actionLog',
            'student',
            'semester',
            'items.courseRegistration.courseOffering.unit',
            'creditCharge',
            'uploadRecord',
            'changedBy',
        ])->findOrFail($deferCaseId);
    }
}
