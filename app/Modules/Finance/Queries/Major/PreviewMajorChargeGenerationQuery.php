<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Major;

use App\Models\ScholarshipDefinition;
use App\Models\StudentScholarshipAward;
use App\Models\VoucherApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Queries\GetActiveScholarshipAdjustmentQuery;
use App\Modules\Finance\Queries\GetUnresolvedPriorAdjustmentQuery;
use App\Modules\Finance\Services\DeferChargeResolver;
use App\Modules\Finance\Support\ScholarshipDiscountResolver;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use App\Modules\Finance\Support\VoucherDiscountAmountResolver;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\PendingScholarshipAdjustmentReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PreviewMajorChargeGenerationQuery
{
    public function __construct(
        private readonly StudentChargeTimingResolver $timingResolver,
        private readonly VoucherDiscountAmountResolver $voucherDiscountAmountResolver,
        private readonly DeferChargeResolver $deferChargeResolver,
    ) {}

    public function handle(int $semesterId, array $filters = [], ?int $campusId = null): array
    {
        $rows = $this->collectRows($semesterId, $filters, $campusId);
        $eligible = $rows->where('eligibility_status', 'eligible')->values();
        $ineligible = $rows->where('eligibility_status', 'ineligible')->values();
        $warnings = $rows->where('eligibility_status', 'warning')->values();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        return [
            'eligible_students' => $this->paginateCollection($eligible, $perPage, $page),
            'ineligible_students' => $ineligible->all(),
            'warning_students' => $warnings->all(),
            'summary' => [
                'eligible_count' => $eligible->count(),
                'ineligible_count' => $ineligible->count(),
                'warning_count' => $warnings->count(),
                'total_count' => $rows->count(),
            ],
        ];
    }

    public function resolveEligibleStudents(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        return $this->collectRows($semesterId, $filters, $campusId)
            ->where('eligibility_status', 'eligible')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collectClassificationRows(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        return $this->collectRows($semesterId, $filters, $campusId);
    }

    private function collectRows(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $ignoredStudentIds = collect($filters['ignore_student_ids'] ?? [])
            ->filter(fn (mixed $studentId): bool => is_string($studentId) && trim($studentId) !== '')
            ->map(fn (string $studentId): string => trim($studentId))
            ->values()
            ->all();

        $studentIds = collect($filters['student_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $eligibleStudentIds = app(StudentCollectionEligibilityReader::class)->eligibleStudentIds(['tuition'], $campusId);
        if ($studentIds !== []) {
            $eligibleStudentIds = array_values(array_intersect($eligibleStudentIds, $studentIds));
        }
        if ($search !== '') {
            $eligibleStudentIds = array_values(array_intersect(
                $eligibleStudentIds,
                app(StudentReferenceReader::class)->idsMatchingSearch($search, $campusId),
            ));
        }

        $references = app(StudentReferenceReader::class)->findMany($eligibleStudentIds);
        $enrollments = app(ProgramEnrollmentReader::class)->forStudentIds(array_keys($references));
        $inFlightByStudent = app(PendingScholarshipAdjustmentReader::class)
            ->inFlightByStudent(array_keys($references), $semesterId);
        $awards = StudentScholarshipAward::query()
            ->with('scholarshipDefinition')
            ->whereIn('student_id', array_keys($references))
            ->get()
            ->keyBy('student_id');
        $vouchers = VoucherApplication::query()
            ->with('voucherDefinition')
            ->whereIn('student_id', array_keys($references))
            ->get()
            ->groupBy('student_id');

        return collect($references)
            ->filter(fn (StudentReference $reference): bool => ! in_array($reference->studentCode, $ignoredStudentIds, true))
            ->sortBy(fn (StudentReference $reference): string => $reference->studentCode)
            ->map(fn (StudentReference $reference): array => $this->classify(
                $reference,
                $enrollments[$reference->id] ?? app(ProgramEnrollmentReader::class)->forStudentId($reference->id),
                $awards->get($reference->id),
                $vouchers->get($reference->id, collect()),
                $semesterId,
                $inFlightByStudent[$reference->id] ?? false,
            ));
    }

    private function classify(
        StudentReference $student,
        ProgramEnrollmentSummary $enrollment,
        ?StudentScholarshipAward $award,
        Collection $voucherApplications,
        int $semesterId,
        bool $hasInFlightScholarshipDossier,
    ): array {
        $base = $this->baseRow($student);

        // FIN-REV-020-02 (M2): a fully deferred semester enrollment is non-billable,
        // so it must not surface as expected/missing tuition (regardless of policy).
        if ($this->deferChargeResolver->isSemesterEnrollmentDeferred($student->id, $semesterId)) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'deferred',
            ]);
        }

        if ($enrollment->curriculumVersionId === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_curriculum_version',
            ]);
        }

        if ($enrollment->intakeSemesterId === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_intake_semester',
            ]);
        }

        if ($enrollment->intakeMajorSemesterId === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_intake_major',
            ]);
        }

        // Already has an active HP (tuition_term) charge for this semester → block creation
        $existingCharge = FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->first();

        if ($existingCharge !== null) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'already_charged',
                'existing_charge_amount' => (float) $existingCharge->amount,
            ]);
        }

        // In-flight scholarship-adjustment dossier for this semester: tuition
        // generation must wait until the dossier resolves (timing invariant).
        if ($hasInFlightScholarshipDossier) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'scholarship_review_pending',
            ]);
        }

        if (! $this->timingResolver->shouldGenerateTuitionForSemester($enrollment, $semesterId)) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'tuition_not_due_this_semester',
            ]);
        }

        $termData = $this->timingResolver->getTuitionTermData($enrollment, $semesterId);
        $amount = $termData['amount'];
        $termNumber = $termData['term_number'];

        if ($termNumber === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'cannot_resolve_term_number',
            ]);
        }

        if ($amount === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_tuition_plan_term',
                'term_number' => $termNumber,
            ]);
        }

        if ($amount <= 0) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'zero_amount_term',
                'term_number' => $termNumber,
                'amount' => 0.0,
            ]);
        }

        $scholarship = $this->resolveScholarship($award, (float) $amount, $student->id, $semesterId);
        $voucher = $this->resolveVoucher($student->id, $voucherApplications, $semesterId);

        return array_merge($base, [
            'eligibility_status' => 'eligible',
            'eligibility_reason' => null,
            'term_number' => $termNumber,
            'chargeable_term_index' => $termData['chargeable_term_index'],
            'amount' => $amount,
            'scholarship_name' => $scholarship['name'],
            'scholarship_type' => $scholarship['type'],
            'scholarship_raw_value' => $scholarship['raw_value'],
            'scholarship_amount' => $scholarship['amount'],
            'voucher_codes' => $voucher['codes'],
            'voucher_amount' => $voucher['amount'],
            'net_amount' => max(0.0, (float) $amount - $scholarship['amount'] - $voucher['amount']),
        ]);
    }

    /**
     * Resolve auto-applicable (redeemed-but-unused) vouchers for one student in one semester.
     * Mirrors the action: prefer the redeemed discount_amount, fall back to a fresh resolution.
     *
     * @return array{codes: array<int, string>, amount: float}
     */
    private function resolveVoucher(int $studentId, Collection $voucherApplications, int $semesterId): array
    {
        $codes = [];
        $amount = 0.0;

        foreach ($voucherApplications as $voucherApp) {
            // Rule: invoice_id IS NULL => not yet consumed.
            if ($voucherApp->invoice_id) {
                continue;
            }

            $discountAmount = (float) $voucherApp->discount_amount;

            if ($discountAmount <= 0 && $voucherApp->voucherDefinition) {
                $resolved = $this->voucherDiscountAmountResolver->resolveAmounts(
                    $voucherApp->voucherDefinition,
                    $studentId,
                    $semesterId,
                );
                $discountAmount = (float) $resolved['discount_amount'];
            }

            if ($discountAmount <= 0) {
                continue;
            }

            $amount += $discountAmount;

            $code = $voucherApp->voucherDefinition?->code;
            if ($code !== null && $code !== '') {
                $codes[] = $code;
            }
        }

        return [
            'codes' => array_values(array_unique($codes)),
            'amount' => round($amount, 2),
        ];
    }

    /**
     * @return array{name: string|null, type: string|null, raw_value: float|null, amount: float}
     */
    private function resolveScholarship(?StudentScholarshipAward $award, float $baseAmount, int $studentId, int $semesterId): array
    {
        $empty = ['name' => null, 'type' => null, 'raw_value' => null, 'amount' => 0.0];

        if (! $award || $baseAmount <= 0) {
            return $empty;
        }

        $definition = $award->scholarshipDefinition;
        if (! $definition instanceof ScholarshipDefinition) {
            return $empty;
        }

        $rawValue = (float) $definition->amount;

        // Preview must mirror execution: honor the per-semester adjustment.
        $adjustment = app(GetActiveScholarshipAdjustmentQuery::class)
            ->handle($studentId, $semesterId);

        // Restoration gate (Phase 5) parity: no adjustment for THIS semester,
        // but an earlier `applied` adjustment carries forward unresolved.
        if ($adjustment === null) {
            $adjustment = app(GetUnresolvedPriorAdjustmentQuery::class)
                ->handle($studentId, $semesterId);
        }

        // FIN-04/07: shared resolver owns the capped scholarship math.
        $discount = app(ScholarshipDiscountResolver::class)
            ->resolveAdjusted($definition, $baseAmount, $adjustment);

        if ($discount <= 0 && $adjustment === null) {
            return $empty;
        }

        return [
            'name' => $definition->name,
            'type' => $definition->type,
            'raw_value' => $rawValue,
            'amount' => $discount,
        ];
    }

    private function baseRow(StudentReference $student): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->fullName,
            'student_code' => $student->studentCode,
            'student_email' => $student->email,
            'eligibility_status' => 'eligible',
            'eligibility_reason' => null,
            'term_number' => null,
            'chargeable_term_index' => null,
            'amount' => null,
            'existing_charge_amount' => null,
            'scholarship_name' => null,
            'scholarship_type' => null,
            'scholarship_raw_value' => null,
            'scholarship_amount' => 0.0,
            'voucher_codes' => [],
            'voucher_amount' => 0.0,
            'net_amount' => null,
        ];
    }

    private function paginateCollection(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;
        $page = max(1, $page);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
