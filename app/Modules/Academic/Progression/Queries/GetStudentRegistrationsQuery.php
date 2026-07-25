<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\DTO\StudentHubCourseOutcomeEvidence;
use App\Shared\Contracts\Academic\DTO\StudentHubRegistrationEvidence;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;
use App\Shared\Contracts\Academic\StudentHubRegistrationEvidenceReader;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class GetStudentRegistrationsQuery
{
    public function __construct(
        private readonly StudentHubRegistrationEvidenceReader $deliveryEvidence,
        private readonly StudentHubCourseOutcomeEvidenceReader $legacyOutcomes,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(int $studentId, array $filters = []): array
    {
        $allRegistrations = collect($this->deliveryEvidence->forStudent($studentId));
        $outcomes = $this->outcomes($studentId, $allRegistrations->pluck('courseOfferingId')->all());
        $allRows = $allRegistrations->map(
            fn (StudentHubRegistrationEvidence $registration): array => $this->row(
                $registration,
                $outcomes[$registration->courseOfferingId] ?? null,
            ),
        );
        $filteredRows = $this->sort($this->filter($allRows, $filters), $filters);
        $perPage = max(1, (int) ($filters['per_page'] ?? 50));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $filteredRows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $pageRows = $filteredRows->forPage($page, $perPage)->values();

        return [
            'data' => $pageRows,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $pageRows->isEmpty() ? null : (($page - 1) * $perPage) + 1,
                'to' => $pageRows->isEmpty() ? null : min($page * $perPage, $total),
                'has_more_pages' => $page < $lastPage,
            ],
            'summary' => $this->summary($allRows),
            'semester_groups' => $this->semesterGroups($allRows),
            'status_breakdown' => $this->statusBreakdown($allRows),
            'filters' => $filters,
        ];
    }

    /**
     * @param  list<int>  $courseOfferingIds
     * @return array<int, array<string, mixed>>
     */
    private function outcomes(int $studentId, array $courseOfferingIds): array
    {
        if ($courseOfferingIds === []) {
            return [];
        }

        $legacy = collect($this->legacyOutcomes->forStudent($studentId, $courseOfferingIds))
            ->unique('courseOfferingId')
            ->keyBy('courseOfferingId');
        $transcripts = TranscriptEntry::query()
            ->where('student_id', $studentId)
            ->whereIn('course_offering_id', $courseOfferingIds)
            ->orderByDesc('finalized_at')
            ->orderByDesc('id')
            ->get()
            ->unique('course_offering_id')
            ->keyBy('course_offering_id');

        return collect($courseOfferingIds)->mapWithKeys(function (int $courseOfferingId) use ($legacy, $transcripts): array {
            /** @var StudentHubCourseOutcomeEvidence|null $legacyRecord */
            $legacyRecord = $legacy->get($courseOfferingId);
            $transcript = $transcripts->get($courseOfferingId);

            if ($transcript === null && $legacyRecord === null) {
                return [];
            }

            return [$courseOfferingId => [
                'final_grade' => $transcript === null ? $legacyRecord?->finalLetterGrade : $transcript->final_letter_grade,
                'final_percentage' => $transcript === null ? $legacyRecord?->finalPercentage : ($transcript->final_percentage === null ? null : (float) $transcript->final_percentage),
                'grade_points' => $transcript === null
                    ? $legacyRecord?->gradePoints
                    : (float) $transcript->grade_points,
                'grade_status' => $transcript === null ? $legacyRecord?->gradeStatus : 'final',
                'completion_status' => $transcript === null ? $legacyRecord?->completionStatus : $transcript->completion_status,
                'is_passed' => $transcript === null ? (bool) $legacyRecord?->isPassed : (bool) $transcript->is_passed,
                'credit_points' => $transcript === null ? $legacyRecord?->creditPoints : ($transcript->credit_points === null ? null : (float) $transcript->credit_points),
                'credit_points_earned' => $transcript === null ? $legacyRecord?->creditPointsEarned : ($transcript->credit_points_earned === null ? null : (float) $transcript->credit_points_earned),
                'attempt_number' => $transcript === null ? $legacyRecord?->attemptNumber : ($transcript->attempt_number === null ? null : (int) $transcript->attempt_number),
                'is_retake' => $transcript === null ? (bool) $legacyRecord?->isRepeatCourse : (int) $transcript->attempt_number > 1,
                'meets_attendance_requirement' => $legacyRecord?->meetsAttendanceRequirement,
            ]];
        })->all();
    }

    /** @param array<string, mixed>|null $outcome @return array<string, mixed> */
    private function row(StudentHubRegistrationEvidence $registration, ?array $outcome): array
    {
        $completionStatus = $outcome['completion_status'] ?? null;
        $finalGrade = $outcome['final_grade'] ?? $registration->finalGrade;
        $attemptNumber = $outcome['attempt_number'] ?? $registration->attemptNumber;
        $isRetake = (bool) ($outcome['is_retake'] ?? false) || $registration->isRetake || ($attemptNumber !== null && $attemptNumber > 1);
        $passFailStatus = $completionStatus === 'completed'
            ? (($outcome['is_passed'] ?? false) ? 'pass' : 'fail')
            : null;

        return [
            'course_offering_id' => $registration->courseOfferingId,
            'id' => $registration->id,
            'semester_id' => $registration->semesterId,
            'course_name' => $registration->courseName,
            'course_code' => $registration->courseCode,
            'section_code' => $registration->sectionCode,
            'unit_credit_points' => $registration->unitCreditPoints,
            'semester' => $registration->semesterName,
            'semester_code' => $registration->semesterCode,
            'academic_year' => $this->academicYear($registration),
            'semester_start_date' => $registration->semesterStartDate,
            'semester_end_date' => $registration->semesterEndDate,
            'registration_status' => $registration->registrationStatus,
            'registration_date' => $registration->registrationDate,
            'registration_method' => $registration->registrationMethod ?? 'N/A',
            'meets_attendance_requirement' => $outcome['meets_attendance_requirement'] ?? null,
            'grade_status' => $outcome['grade_status'] ?? null,
            'final_grade' => $finalGrade,
            'final_percentage' => $outcome['final_percentage'] ?? null,
            'grade_points' => $outcome['grade_points'] ?? $registration->gradePoints,
            'credit_points' => $outcome['credit_points'] ?? null,
            'credit_points_earned' => $outcome['credit_points_earned'] ?? null,
            'registration_credit_points' => $registration->creditPoints,
            'completion_status' => $completionStatus,
            'pass_fail_status' => $passFailStatus,
            'is_retake' => $isRetake,
            'attempt_number' => $attemptNumber,
            'completion_date' => $registration->completionDate,
            'drop_date' => $registration->dropDate,
            'withdrawal_date' => $registration->withdrawalDate,
            'retake_fee' => $registration->retakeFee,
            'is_retake_paid' => $registration->isRetakePaid,
            'notes' => $registration->notes,
            'status_badge_color' => $this->completionBadge($completionStatus),
            'grade_badge_color' => $this->gradeBadge($outcome['grade_status'] ?? null),
            'pass_fail_badge_color' => $this->passFailBadge($passFailStatus),
            'is_passing_grade' => $this->isPassingGrade($finalGrade),
            'formatted_registration_date' => $registration->registrationDate === null ? null : Carbon::parse($registration->registrationDate)->format('M j, Y'),
            'formatted_completion_date' => $registration->completionDate === null ? null : Carbon::parse($registration->completionDate)->format('M j, Y'),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows @param array<string, mixed> $filters */
    private function filter(Collection $rows, array $filters): Collection
    {
        return $rows
            ->when($filters['semester_id'] ?? null, fn (Collection $items, mixed $semesterId): Collection => $items->where('semester_id', (int) $semesterId))
            ->when($filters['academic_year'] ?? null, fn (Collection $items, mixed $academicYear): Collection => $items->where('academic_year', $academicYear))
            ->when(($filters['status'] ?? null) !== null && $filters['status'] !== 'all', fn (Collection $items): Collection => $items->where('registration_status', $filters['status']))
            ->when(in_array($filters['is_retake'] ?? null, ['true', 'false'], true), fn (Collection $items): Collection => $items->where('is_retake', $filters['is_retake'] === 'true'))
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows @param array<string, mixed> $filters */
    private function sort(Collection $rows, array $filters): Collection
    {
        $field = match ($filters['sort'] ?? null) {
            'course_name' => 'course_name',
            'course_code' => 'course_code',
            'semester' => 'semester',
            'registration_status' => 'registration_status',
            'final_grade' => 'final_grade',
            'pass_fail_status' => 'pass_fail_status',
            default => 'registration_date',
        };

        return ($filters['direction'] ?? 'desc') === 'asc'
            ? $rows->sortBy($field)->values()
            : $rows->sortByDesc($field)->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows @return array<string, int|float> */
    private function summary(Collection $rows): array
    {
        $total = $rows->count();

        return [
            'total_registrations' => $total,
            'completed' => $rows->where('registration_status', 'completed')->count(),
            'active' => $rows->whereIn('registration_status', ['enrolled', 'active', 'registered', 'confirmed'])->count(),
            'dropped' => $rows->where('registration_status', 'dropped')->count(),
            'withdrawn' => $rows->where('registration_status', 'withdrawn')->count(),
            'retakes' => $rows->where('is_retake', true)->count(),
            'total_credits_attempted' => (float) $rows->sum('registration_credit_points'),
            'total_credits_earned' => (float) $rows->where('pass_fail_status', 'pass')->sum('credit_points_earned'),
            'completion_rate' => $total === 0 ? 0.0 : round(($rows->where('registration_status', 'completed')->count() / $total) * 100, 2),
            'retake_rate' => $total === 0 ? 0.0 : round(($rows->where('is_retake', true)->count() / $total) * 100, 2),
            'average_grade_points' => (float) ($rows->pluck('grade_points')->filter()->avg() ?? 0),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows @return Collection<int, array<string, mixed>> */
    private function semesterGroups(Collection $rows): Collection
    {
        return $rows->groupBy('academic_year')->map(function (Collection $registrations, string $academicYear): array {
            return [
                'academic_year' => $academicYear,
                'semesters' => $registrations->map(fn (array $registration): array => [
                    'id' => $registration['semester_id'],
                    'name' => $registration['semester'],
                    'code' => $registration['semester_code'],
                    'academic_year' => $registration['academic_year'],
                ])->unique('id')->values(),
                'total_registrations' => $registrations->count(),
            ];
        })->sortByDesc('academic_year')->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows @return Collection<int, array<string, mixed>> */
    private function statusBreakdown(Collection $rows): Collection
    {
        $total = $rows->count();

        return $rows->groupBy('registration_status')->map(function (Collection $registrations, string $status) use ($total): array {
            return [
                'status' => $status,
                'count' => $registrations->count(),
                'percentage' => $total === 0 ? 0.0 : round(($registrations->count() / $total) * 100, 2),
                'badge_color' => $this->registrationBadge($status),
            ];
        })->values();
    }

    private function academicYear(StudentHubRegistrationEvidence $registration): string
    {
        if (preg_match('/(\d{4})/', $registration->semesterCode, $matches) === 1) {
            $year = (int) $matches[1];

            return str_contains(strtolower($registration->semesterCode), 'spr') ? ($year - 1).'-'.$year : $year.'-'.($year + 1);
        }

        if (preg_match('/(\d{4})/', $registration->semesterName, $matches) === 1) {
            $year = (int) $matches[1];

            return str_contains(strtolower($registration->semesterName), 'spring') ? ($year - 1).'-'.$year : $year.'-'.($year + 1);
        }

        if ($registration->semesterStartDate !== null) {
            $date = Carbon::parse($registration->semesterStartDate);

            return $date->month <= 7 ? ($date->year - 1).'-'.$date->year : $date->year.'-'.($date->year + 1);
        }

        return 'Unknown';
    }

    private function isPassingGrade(?string $grade): bool
    {
        return $grade !== null && in_array(strtoupper($grade), ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-'], true);
    }

    private function registrationBadge(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'enrolled', 'active', 'registered', 'confirmed' => 'primary',
            'dropped', 'defer' => 'warning',
            'withdrawn' => 'destructive',
            default => 'secondary',
        };
    }

    private function completionBadge(?string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'in_progress' => 'primary',
            'failed' => 'destructive',
            'withdrawn' => 'warning',
            default => 'secondary',
        };
    }

    private function gradeBadge(?string $status): string
    {
        return match ($status) {
            'passing' => 'success',
            'failing' => 'destructive',
            default => 'secondary',
        };
    }

    private function passFailBadge(?string $status): string
    {
        return match ($status) {
            'pass' => 'success',
            'fail' => 'destructive',
            default => 'secondary',
        };
    }
}
