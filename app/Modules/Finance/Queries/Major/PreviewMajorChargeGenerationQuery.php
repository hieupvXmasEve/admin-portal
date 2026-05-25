<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Major;

use App\Models\FinanceCharge;
use App\Models\ScholarshipDefinition;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PreviewMajorChargeGenerationQuery
{
    public function __construct(
        private readonly StudentChargeTimingResolver $timingResolver,
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

    private function collectRows(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $ignoredStudentIds = collect($filters['ignore_student_ids'] ?? [])
            ->filter(fn (mixed $studentId): bool => is_string($studentId) && trim($studentId) !== '')
            ->map(fn (string $studentId): string => trim($studentId))
            ->values()
            ->all();

        $students = Student::query()
            ->with(['scholarshipAward.scholarshipDefinition'])
            ->where('status', 'intake_course')
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->when($ignoredStudentIds !== [], fn ($query) => $query->whereNotIn('student_id', $ignoredStudentIds))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($studentQuery) use ($search) {
                    $studentQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('student_id')
            ->get();

        return $students->map(fn (Student $student) => $this->classify($student, $semesterId));
    }

    private function classify(Student $student, int $semesterId): array
    {
        $base = $this->baseRow($student);

        if ($student->curriculum_version_id === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_curriculum_version',
            ]);
        }

        if ($student->intake_semester_id === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_intake_semester',
            ]);
        }

        if ($student->intake_major === null) {
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

        if (! $this->timingResolver->shouldGenerateTuitionForSemester($student, $semesterId)) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'tuition_not_due_this_semester',
            ]);
        }

        $termData = $this->timingResolver->getTuitionTermData($student, $semesterId);
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

        $scholarship = $this->resolveScholarship($student->scholarshipAward, (float) $amount);

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
            'net_amount' => max(0.0, (float) $amount - $scholarship['amount']),
        ]);
    }

    /**
     * @return array{name: string|null, type: string|null, raw_value: float|null, amount: float}
     */
    private function resolveScholarship(?StudentScholarshipAward $award, float $baseAmount): array
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
        $discount = $definition->type === 'percentage'
            ? ($baseAmount * $rawValue) / 100
            : $rawValue;

        $discount = max(0.0, min($baseAmount, $discount));

        if ($discount <= 0) {
            return $empty;
        }

        return [
            'name' => $definition->name,
            'type' => $definition->type,
            'raw_value' => $rawValue,
            'amount' => $discount,
        ];
    }

    private function baseRow(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'student_code' => $student->student_id,
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
